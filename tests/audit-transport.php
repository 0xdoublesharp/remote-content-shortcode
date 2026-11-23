<?php
/** Actual pinned cURL/Requests checks against bounded isolated-loopback responses. */
if ( ! defined( 'ABSPATH' ) ) { exit; }
require_once getenv( 'RCS_PLUGIN_FILE' ) ?: dirname( __DIR__ ) . '/remote-content-shortcode.php';
$plugin = RemoteContentShortcode::get_instance(); $request = new ReflectionMethod( $plugin, 'pinned_request' );
$reply = static function ( $body, $encoding = '' ) {
	$header = '' === $encoding ? '' : "Content-Encoding: $encoding\r\n";
	return "HTTP/1.1 200 OK\r\n" . $header . 'Content-Length: ' . strlen( $body ) . "\r\nConnection: close\r\n\r\n" . $body;
};
$limit = str_repeat( 'x', 1048576 );
$encoded = $reply( gzencode( $limit ), 'gzip' );
$early = "HTTP/1.1 103 Early Hints\r\nLink: </inert>\r\n\r\n";
$small_gzip = gzencode( 'ok' );
$chunked_body = "2\r\nok\r\n0\r\n\r\n";
$chunked_gzip = "HTTP/1.1 200 OK\r\nContent-Encoding: gzip\r\nTransfer-Encoding: chunked\r\nConnection: close\r\n\r\n";
foreach ( str_split( gzencode( $chunked_body ), 7 ) as $chunk ) {
	$chunked_gzip .= dechex( strlen( $chunk ) ) . "\r\n" . $chunk . "\r\n";
}
$chunked_gzip .= "0\r\nX-Inert: trailer\r\n\r\n";
$cases = array(
	'ordinary response' => array( $reply( 'ok' ), '' ),
	'identity response' => array( $reply( 'ok', " \tIdEnTiTy \t" ), '' ),
	'identity preserves gzip-looking bytes' => array( $reply( $small_gzip, 'identity' ), '', $small_gzip ),
	'gzip response' => array( $reply( $small_gzip, 'gZiP' ), '' ),
	'gzip with multiple members' => array( $reply( gzencode( 'o' ) . gzencode( 'k' ), 'gzip' ), '' ),
	'chunked gzip is dechunked once' => array( $chunked_gzip, '', $chunked_body ),
	'deflate response' => array( $reply( gzcompress( 'ok' ), 'deflate' ), '' ),
	'gzip one byte below ceiling' => array( $reply( gzencode( substr( $limit, 1 ) ), 'gzip' ), '', substr( $limit, 1 ) ),
	'gzip at decompressed ceiling' => array( $encoded, 'Encoded responses' ),
	'truncated gzip' => array( $reply( substr( $small_gzip, 0, -1 ), 'gzip' ), 'Encoded responses' ),
	'layered encoding' => array( $reply( $small_gzip, 'gzip, identity' ), 'Encoded responses' ),
	'duplicate encoding fields' => array( $reply( $small_gzip, "gzip\r\nContent-Encoding: identity" ), 'Encoded responses' ),
	'informational and gzip response' => array( $early . $reply( $small_gzip, 'gzip' ), '' ),
	'informational and oversized gzip response' => array( $early . $encoded, 'Encoded responses' ),
	'informational and unsupported encoding' => array( $early . $reply( 'ok', 'br' ), 'Encoded responses' ),
	'aggregate header bytes' => array( "HTTP/1.1 200 OK\r\n" . str_repeat( 'X-Inert: ' . str_repeat( 'x', 1000 ) . "\r\n", 70 ) . "Content-Length: 1\r\n\r\nx", 'Response headers exceeded' ),
	'header field count' => array( "HTTP/1.1 200 OK\r\n" . str_repeat( "X-Inert: x\r\n", 129 ) . "Content-Length: 1\r\n\r\nx", 'Response headers exceeded' ),
);
foreach ( $cases as $name => $case ) {
	list( $raw, $error ) = $case;
	$body = $case[2] ?? 'ok';
	$process = proc_open( array( PHP_BINARY, __DIR__ . '/fixtures/audit-http-server.php' ), array( 0 => array( 'pipe', 'r' ), 1 => array( 'pipe', 'w' ), 2 => STDERR ), $pipes );
	if ( ! is_resource( $process ) ) { throw new RuntimeException( 'HTTP fixture did not start.' ); }
	try {
		fwrite( $pipes[0], $raw ); fclose( $pipes[0] ); stream_set_timeout( $pipes[1], 3 ); $port = (int) fgets( $pipes[1] );
		if ( ! $port ) { throw new RuntimeException( 'HTTP fixture did not bind.' ); }
		$result = $request->invoke( $plugin, 'http://fixture.invalid:' . $port . '/', array( 'method' => 'GET', 'timeout' => 2 ), '127.0.0.1', microtime( true ) + 2 );
		if ( '' === $error ? ( is_wp_error( $result ) || $body !== $result['body'] || '' !== wp_remote_retrieve_header( $result, 'content-encoding' ) ) : ( ! is_wp_error( $result ) || ! str_contains( $result->get_error_message(), $error ) ) ) {
			throw new RuntimeException( 'Transport case failed: ' . $name . ': ' . ( is_wp_error( $result ) ? $result->get_error_message() : 'unexpected response' ) );
		}
		echo "PASS $name\n";
	} finally { fclose( $pipes[1] ); proc_terminate( $process ); proc_close( $process ); }
}
