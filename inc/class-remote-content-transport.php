<?php
/** cURL adapter that bounds headers and decoded body bytes before Requests retains them. */
if ( ! defined( 'ABSPATH' ) ) { exit; }
class Remote_Content_Shortcode_Transport implements WpOrg\Requests\Transport {
	private const MAX_BYTES = 1048576;

	public function request( $url, $headers = array(), $data = array(), $options = array() ) {
		$transport = new WpOrg\Requests\Transport\Curl();
		$header_bytes = 0;
		$fields = 0;
		$complete = false;
		$encoding = null;
		$encoding_line = false;
		$inflater = null;
		$decoded_bytes = 0;
		$read_header = static function ( $curl, $line ) use ( $transport, &$header_bytes, &$fields, &$complete, &$encoding, &$encoding_line, &$inflater ) {
			$length = strlen( $line );
			$header_bytes += $length;
			if ( $header_bytes > 65536 || ++$fields > 128 ) { throw new WpOrg\Requests\Exception( 'Response headers exceeded the limit.', 'remote_content_headers_too_large' ); }
			if ( preg_match( '~^HTTP/[0-9.]+[ \t]+[1-5][0-9]{2}(?:[ \t\r\n]|$)~D', $line ) ) {
				// cURL delivers informational headers before the final response headers.
				$complete = false;
				$encoding = null;
				$encoding_line = false;
				$inflater = null;
			} elseif ( preg_match( '/^[ \t]*content-encoding[ \t]*:([^\r\n]*)/i', $line, $match ) ) {
				if ( $complete || null !== $encoding ) { throw self::encoded_response_error(); }
				$encoding = strtolower( trim( $match[1], " \t" ) );
				if ( 'identity' !== $encoding && ( ! in_array( $encoding, array( 'gzip', 'deflate' ), true ) || ! function_exists( 'inflate_init' ) ) ) { throw self::encoded_response_error(); }
				$encoding_line = true;
				// Passing even identity to Requests would let it try to inflate the body.
				return $length;
			} elseif ( preg_match( '/\n[ \t]*content-encoding[ \t]*:/i', $line ) ) {
				throw self::encoded_response_error();
			} elseif ( $complete ) {
				// cURL delivers chunked trailers through this callback. Keep the final headers.
				return $length;
			} elseif ( $encoding_line && strspn( $line, " \t" ) ) {
				if ( '' !== trim( $line, " \t\r\n" ) ) { throw self::encoded_response_error(); }
				return $length;
			}
			$encoding_line = false;
			if ( "\r\n" === $line ) {
				$complete = true;
				if ( null !== $encoding && 'identity' !== $encoding ) { $inflater = self::start_inflate( $encoding ); }
			}
			return $transport->stream_headers( $curl, $line );
		};
		$read_body = static function ( $curl, $chunk ) use ( $transport, &$encoding, &$inflater, &$decoded_bytes ) {
			if ( null === $inflater ) { return $transport->stream_body( $curl, $chunk ); }
			$length = strlen( $chunk );
			// Limit each inflate call's allocation as well as the cumulative decoded size.
			for ( $offset = 0; $offset < $length; $offset += $consumed ) {
				if ( ZLIB_STREAM_END === inflate_get_status( $inflater ) ) {
					if ( 'gzip' !== $encoding ) { throw self::encoded_response_error(); }
					$inflater = self::start_inflate( $encoding ); // Gzip can contain multiple members.
				}
				$before = inflate_get_read_len( $inflater );
				$decoded = @inflate_add( $inflater, substr( $chunk, $offset, 128 ), ZLIB_SYNC_FLUSH );
				$consumed = inflate_get_read_len( $inflater ) - $before;
				if ( false === $decoded || $consumed <= 0 ) { throw self::encoded_response_error(); }
				$decoded_bytes += strlen( $decoded );
				if ( $decoded_bytes >= self::MAX_BYTES ) { throw self::encoded_response_error(); }
				$transport->stream_body( $curl, $decoded );
			}
			return $length;
		};
		$options['hooks']->register( 'curl.before_send', static function ( $handle ) use ( $read_header, $read_body ) {
			// phpcs:ignore WordPress.WP.AlternativeFunctions.curl_curl_setopt_array -- Configure WordPress's own cURL transport for bounded headers and streaming decompression.
			if ( ! curl_setopt_array( $handle, array(
				CURLOPT_HTTP_CONTENT_DECODING => false,
				CURLOPT_HEADERFUNCTION => $read_header,
				CURLOPT_WRITEFUNCTION => $read_body,
			) ) ) { throw new WpOrg\Requests\Exception( 'Unable to bound the remote response.', 'remote_content_header_limit_failed' ); }
		} );
		$response = $transport->request( $url, $headers, $data, $options );
		if ( null === $inflater ) { return $response; }
		if ( ZLIB_STREAM_END !== inflate_get_status( $inflater ) ) { throw self::encoded_response_error(); }
		// cURL already removed chunk framing. The original length describes the compressed body.
		$end = strpos( $response, "\r\n\r\n" );
		$headers = preg_replace( '/\r?\n[ \t]*(?:content-length|transfer-encoding)[ \t]*:[^\r\n]*(?:\r?\n[ \t]+[^\r\n]*)*/i', '', substr( $response, 0, $end ) );
		return $headers . substr( $response, $end );
	}

	private static function start_inflate( $encoding ) {
		$inflater = inflate_init( 'gzip' === $encoding ? ZLIB_ENCODING_GZIP : ZLIB_ENCODING_DEFLATE );
		if ( false === $inflater ) { throw self::encoded_response_error(); }
		return $inflater;
	}

	private static function encoded_response_error() {
		return new WpOrg\Requests\Exception( 'Encoded responses must use a supported encoding and stay below the decoded size limit.', 'remote_content_encoded_response' );
	}

	public function request_multiple( $requests, $options ) { throw new WpOrg\Requests\Exception( 'Concurrent transport is unsupported.', 'remote_content_transport_unsupported' ); }
	public static function test( $capabilities = array() ) { return WpOrg\Requests\Transport\Curl::test( $capabilities ); }
}
