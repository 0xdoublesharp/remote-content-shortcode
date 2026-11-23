<?php
/** Remove remote presentation authority and require an administrator asset grant. */
if ( ! defined( 'ABSPATH' ) ) { exit; }
class Remote_Content_Shortcode_Output {
	const ORIGINS_OPTION = 'rcs_resource_origins_v1';
	const MAX_ORIGINS = 16;

	public static function normalize_origin( $origin ) {
		if ( ! is_string( $origin ) ) { return false; }
		$origin = trim( $origin, " \t\n\r\v\f" );
		if ( strlen( $origin ) > 512 || preg_match( '/[\x00-\x20\x7f\\\\]/', $origin ) ) { return false; }
		$parts = wp_parse_url( $origin );
		if ( ! $parts || isset( $parts['query'] ) || isset( $parts['fragment'] ) || ! in_array( $parts['path'] ?? '', array( '', '/' ), true ) ) { return false; }
		return self::origin_from_parts( $parts );
	}

	public static function stored_origins() {
		return self::valid_origins( get_option( self::ORIGINS_OPTION, array() ) );
	}

	public static function save_origins( $origins ) {
		if ( ! current_user_can( 'manage_options' ) ) { return new WP_Error( 'forbidden' ); }
		if ( is_string( $origins ) ) { $origins = preg_split( '/[,\r\n]/', $origins ); }
		if ( ! is_array( $origins ) ) { return new WP_Error( 'invalid_resource_origin' ); }
		$saved = array();
		foreach ( $origins as $entry ) {
			if ( is_string( $entry ) && '' === trim( $entry, " \t\n\r\v\f" ) ) { continue; }
			$origin = self::normalize_origin( $entry );
			if ( false === $origin ) { return new WP_Error( 'invalid_resource_origin' ); }
			$saved[ $origin ] = true;
		}
		if ( count( $saved ) > self::MAX_ORIGINS ) { return new WP_Error( 'resource_origin_limit' ); }
		$saved = array_keys( $saved );
		if ( ! update_option( self::ORIGINS_OPTION, $saved, false ) && $saved !== get_option( self::ORIGINS_OPTION ) ) {
			return new WP_Error( 'resource_origin_storage_failed' );
		}
		return $saved;
	}

	private static function valid_origins( $input, $origins = array() ) {
		if ( ! is_array( $input ) ) { return $origins; }
		foreach ( $input as $entry ) {
			if ( count( $origins ) >= self::MAX_ORIGINS ) { break; }
			$origin = self::normalize_origin( $entry );
			if ( false !== $origin && ! in_array( $origin, $origins, true ) ) { $origins[] = $origin; }
		}
		return $origins;
	}

	private static function canonical_host( $host ) {
		if ( str_starts_with( $host, '[' ) && str_ends_with( $host, ']' ) ) {
			$address = inet_pton( substr( $host, 1, -1 ) );
			return false !== $address && 16 === strlen( $address ) ? '[' . inet_ntop( $address ) . ']' : '';
		}
		if ( ! preg_match( '//u', $host ) ) { return ''; }
		if ( str_ends_with( $host, '.' ) ) { $host = substr( $host, 0, -1 ); }
		try { return WpOrg\Requests\IdnaEncoder::encode( strtolower( $host ) ); }
		catch ( Throwable $error ) { return ''; }
	}

	private static function origin_from_parts( $parts ) {
		$port = $parts['port'] ?? 443;
		if ( 'https' !== strtolower( $parts['scheme'] ?? '' ) || isset( $parts['user'] ) || isset( $parts['pass'] ) || ! in_array( $port, array( 443, 8080 ), true ) ) { return false; }
		$host = self::canonical_host( $parts['host'] ?? '' );
		$address = trim( $host, '[]' );
		if ( filter_var( $address, FILTER_VALIDATE_IP ) ) {
			$multicast = str_contains( $address, ':' ) ? str_starts_with( $address, 'ff' ) : (int) $address >= 224;
			if ( $multicast || ! filter_var( $address, FILTER_VALIDATE_IP, FILTER_FLAG_GLOBAL_RANGE ) ) { return false; }
		} elseif ( ! str_contains( $host, '.' ) || ! filter_var( $host, FILTER_VALIDATE_DOMAIN, FILTER_FLAG_HOSTNAME ) || preg_match( '/(?:^|\.)(?:localhost|local|internal|test|invalid|onion|home\.arpa|[0-9]+|0x[0-9a-f]*)$/D', $host ) ) {
			return false;
		}
		if ( $host === self::canonical_host( (string) wp_parse_url( home_url(), PHP_URL_HOST ) ) ) { return false; }
		return 'https://' . $host . ( 443 === $port ? '' : ':' . $port );
	}

	public static function sanitize( $html, $base ) {
		if ( ! is_string( $html ) ) { return new WP_Error( 'invalid_output' ); }
		if ( strlen( $html ) > 1048576 ) { return new WP_Error( 'output_too_large' ); }
		// KSES first: the processor sees only post-safe tags and normalized attributes.
		$html = wp_kses_post( $html );
		$processor = new WP_HTML_Tag_Processor( $html );
		$filtered_origins = apply_filters( 'remote_content_shortcode_resource_origins', array(), $base );
		$origins = self::valid_origins( $filtered_origins, self::stored_origins() );
		$resources = 0; $tags = 0;
		while ( $processor->next_tag() ) {
			if ( ++$tags > 20000 ) { return new WP_Error( 'html_too_complex' ); }
			foreach ( array( 'style', 'class', 'id', 'srcset', 'background', 'ping' ) as $attribute ) { $processor->remove_attribute( $attribute ); }
			foreach ( array( 'src', 'poster' ) as $attribute ) {
				$value = $processor->get_attribute( $attribute );
				if ( null === $value ) { continue; }
				$url = is_string( $value ) ? self::resource( $value, $base, $origins ) : false;
				if ( false === $url || ++$resources > 32 ) { $processor->remove_attribute( $attribute ); }
				else { $processor->set_attribute( $attribute, $url ); }
			}
		}
		$html = $processor->get_updated_html();
		return strlen( $html ) <= 1048576 ? wp_kses_post( $html ) : new WP_Error( 'output_too_large' );
	}
	private static function resource( $value, $base, $origins ) {
		if ( ! $origins || strlen( $value ) > 8192 || preg_match( '/[\x00-\x20\x7f\\\\]/', $value ) ) { return false; }
		try { $resolved = WpOrg\Requests\Iri::absolutize( $base, $value ); $url = $resolved ? (string) $resolved->uri : ''; }
		catch ( Throwable $error ) { return false; }
		$parts = wp_parse_url( $url );
		if ( strlen( $url ) > 8192 || empty( $parts['host'] ) ) { return false; }
		// Requests percent-encodes Unicode hosts when serializing an IRI to a URI.
		$parts['host'] = rawurldecode( $parts['host'] );
		$origin = self::origin_from_parts( $parts );
		if ( ! in_array( $origin, $origins, true ) ) { return false; }
		$url = $origin . ( $parts['path'] ?? '' ) . ( isset( $parts['query'] ) ? '?' . $parts['query'] : '' ) . ( isset( $parts['fragment'] ) ? '#' . $parts['fragment'] : '' );
		return strlen( $url ) <= 8192 ? $url : false;
	}
}
