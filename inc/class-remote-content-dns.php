<?php
/** Deadline-bound DNS without blocking PHP/system resolver calls. */
if ( ! defined( 'ABSPATH' ) ) { exit; }

class Remote_Content_Shortcode_DNS {
	public static function is_hostname( $host ) {
		return is_string( $host ) && strlen( $host ) <= 253 && 1 === preg_match( '/^(?:[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?\.)*[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?\.?$/iD', $host );
	}

	public static function has_resolvers() { return ! empty( self::servers() ); }

	public static function resolve( $host, $deadline ) {
		if ( ! self::is_hostname( $host ) ) { return false; }
		$servers = self::servers();
		if ( ! $servers ) { return false; }
		$addresses = array();
		foreach ( array( 1, 28 ) as $type ) {
			$name = strtolower( rtrim( $host, '.' ) );
			for ( $depth = 0; $depth < 8; ++$depth ) {
				$result = false;
				foreach ( $servers as $index => $server ) {
					$now = microtime( true );
					if ( $now >= $deadline ) { return false; }
					$remaining_servers = count( $servers ) - $index;
					$attempt_deadline = $remaining_servers > 1 ? $now + min( 2.5, ( $deadline - $now ) / $remaining_servers ) : $deadline;
					$result = self::query( $name, $type, $server, $attempt_deadline );
					if ( false !== $result ) {
						// Prefer the responding resolver for the next record type or CNAME hop.
						if ( $index ) {
							unset( $servers[ $index ] );
							array_unshift( $servers, $server );
						}
						break;
					}
				}
				if ( false === $result ) { return false; }
				$seen = array();
				while ( isset( $result['aliases'][ $name ] ) ) {
					if ( isset( $seen[ $name ] ) || count( $seen ) >= 8 ) { return false; }
					$seen[ $name ] = true;
					$name = $result['aliases'][ $name ];
				}
				if ( isset( $result['addresses'][ $name ] ) ) {
					$addresses = array_merge( $addresses, $result['addresses'][ $name ] );
					break;
				}
				if ( ! $seen ) { break; } // Authoritative empty answer, including no AAAA.
				if ( 7 === $depth ) { return false; }
			}
		}
		return $addresses && count( $addresses ) <= 64 ? array_values( array_unique( $addresses ) ) : false;
	}

	private static function servers() {
		if ( defined( 'REMOTE_CONTENT_DNS_SERVERS' ) ) {
			$servers = REMOTE_CONTENT_DNS_SERVERS;
			if ( ! is_array( $servers ) || count( $servers ) > 3 ) { return array(); }
			foreach ( $servers as $server ) {
				if ( ! is_string( $server ) || ! filter_var( $server, FILTER_VALIDATE_IP ) ) { return array(); }
			}
			return array_values( $servers );
		}
		// This administrator-owned file supplies numeric resolver addresses, never target URLs.
		$config = @file_get_contents( '/etc/resolv.conf', false, null, 0, 4096 );
		return self::parse_resolv_conf( (string) $config );
	}

	private static function parse_resolv_conf( $config ) {
		preg_match_all( '/^[ \t]*nameserver[ \t]+([^ \t\r\n#;]+)[ \t]*(?:[#;][^\r\n]*)?\r?$/m', $config, $matches );
		$servers = array_filter( $matches[1], static fn( $ip ) => false !== filter_var( $ip, FILTER_VALIDATE_IP ) );
		return array_slice( $servers, 0, 3 );
	}

	private static function wait( $socket, $deadline, $write = false ) {
		$remaining = $deadline - microtime( true );
		if ( $remaining <= 0 ) { return false; }
		$read = $write ? array() : array( $socket ); $writes = $write ? array( $socket ) : array(); $except = array();
		$microseconds = (int) ceil( $remaining * 1000000 );
		return 1 === @stream_select( $read, $writes, $except, intdiv( $microseconds, 1000000 ), $microseconds % 1000000 );
	}

	private static function exchange( $server, $packet, $deadline, $tcp ) {
		$remaining = $deadline - microtime( true );
		if ( $remaining <= 0 ) { return false; }
		$address = str_contains( $server, ':' ) ? '[' . $server . ']' : $server;
		$socket = @stream_socket_client( ( $tcp ? 'tcp' : 'udp' ) . '://' . $address . ':53', $errno, $error, $remaining, STREAM_CLIENT_CONNECT );
		if ( ! $socket ) { return false; }
		try {
			stream_set_blocking( $socket, false );
			$wire = $tcp ? pack( 'n', strlen( $packet ) ) . $packet : $packet;
			while ( '' !== $wire ) {
				if ( ! self::wait( $socket, $deadline, true ) ) { return false; }
				// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fwrite -- Send the DNS query over an open network socket.
				$sent = @fwrite( $socket, $wire );
				if ( ! $sent ) { return false; }
				$wire = substr( $wire, $sent );
			}
			if ( ! self::wait( $socket, $deadline ) ) { return false; }
			if ( ! $tcp ) {
				// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fread -- Read one bounded UDP response from the DNS socket.
				$raw = @fread( $socket, 16385 );
				return is_string( $raw ) && strlen( $raw ) <= 16384 ? $raw : false;
			}
			$raw = ''; $needed = 2;
			while ( strlen( $raw ) < $needed ) {
				if ( ! self::wait( $socket, $deadline ) ) { return false; }
				// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fread -- Read a bounded chunk from the TCP DNS socket.
				$piece = @fread( $socket, $needed - strlen( $raw ) );
				if ( ! is_string( $piece ) || '' === $piece ) { return false; }
				$raw .= $piece;
				if ( 2 === $needed && 2 === strlen( $raw ) ) {
					$needed = 2 + unpack( 'n', $raw )[1];
					if ( $needed < 14 || $needed > 16386 ) { return false; }
				}
			}
			return substr( $raw, 2 );
		} finally {
			// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose -- Close the DNS socket on success or failure.
			fclose( $socket );
		}
	}

	private static function name( $raw, &$offset ) {
		$name = array(); $cursor = $offset; $next = null; $seen = array();
		for ( $i = 0; $i < 128; ++$i ) {
			if ( $cursor >= strlen( $raw ) || isset( $seen[ $cursor ] ) ) { return false; }
			$seen[ $cursor ] = true; $size = ord( $raw[ $cursor++ ] );
			if ( 0 === $size ) { $offset = $next ?? $cursor; return strtolower( implode( '.', $name ) ); }
			if ( 192 === ( $size & 192 ) ) {
				if ( $cursor >= strlen( $raw ) ) { return false; }
				$next = $next ?? ( $cursor + 1 );
				$cursor = ( ( $size & 63 ) << 8 ) | ord( $raw[ $cursor ] );
				continue;
			}
			if ( $size > 63 || $cursor + $size > strlen( $raw ) ) { return false; }
			$label = substr( $raw, $cursor, $size );
			if ( ! preg_match( '/^[a-z0-9_-]+$/iD', $label ) ) { return false; }
			$name[] = $label; $cursor += $size;
			if ( strlen( implode( '.', $name ) ) > 253 ) { return false; }
		}
		return false;
	}

	private static function query( $host, $type, $server, $deadline ) {
		$id = random_int( 0, 65535 ); $question = '';
		foreach ( explode( '.', $host ) as $label ) { $question .= chr( strlen( $label ) ) . $label; }
		$question .= "\0" . pack( 'nn', $type, 1 );
		$packet = pack( 'nnnnnn', $id, 256, 1, 0, 0, 0 ) . $question;
		$raw = self::exchange( $server, $packet, $deadline, false );
		if ( ! is_string( $raw ) || strlen( $raw ) < 12 ) { return false; }
		$header = unpack( 'nid/nflags/nquestions/nanswers/nauthority/nadditional', $raw );
		if ( $header['id'] !== $id || 32768 !== ( $header['flags'] & 63488 ) || 1 !== $header['questions'] ) { return false; }
		if ( $header['flags'] & 512 ) {
			$raw = self::exchange( $server, $packet, $deadline, true );
			if ( ! is_string( $raw ) || strlen( $raw ) < 12 ) { return false; }
			$header = unpack( 'nid/nflags/nquestions/nanswers/nauthority/nadditional', $raw );
			if ( $header['id'] !== $id || 32768 !== ( $header['flags'] & 64000 ) || 1 !== $header['questions'] ) { return false; }
		}
		if ( 0 !== ( $header['flags'] & 15 ) || $header['answers'] + $header['authority'] + $header['additional'] > 128 ) { return false; }
		$offset = 12;
		if ( self::name( $raw, $offset ) !== $host || substr( $raw, $offset, 4 ) !== pack( 'nn', $type, 1 ) ) { return false; }
		$offset += 4; $result = array( 'addresses' => array(), 'aliases' => array() );
		for ( $i = 0; $i < $header['answers']; ++$i ) {
			$name = self::name( $raw, $offset );
			if ( false === $name || $offset + 10 > strlen( $raw ) ) { return false; }
			$record = unpack( 'ntype/nclass/Nttl/nlength', substr( $raw, $offset, 10 ) ); $offset += 10;
			$end = $offset + $record['length'];
			if ( $end > strlen( $raw ) || 1 !== $record['class'] ) { return false; }
			if ( $record['type'] === $type ) {
				if ( $record['length'] !== ( 1 === $type ? 4 : 16 ) ) { return false; }
				$result['addresses'][ $name ][] = inet_ntop( substr( $raw, $offset, $record['length'] ) );
			} elseif ( 5 === $record['type'] ) {
				$alias = self::name( $raw, $offset );
				if ( false === $alias || $offset !== $end || isset( $result['aliases'][ $name ] ) ) { return false; }
				$result['aliases'][ $name ] = $alias;
			}
			$offset = $end;
		}
		return $result;
	}
}
