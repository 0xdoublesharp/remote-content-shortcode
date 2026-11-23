<?php
/** Dynamic block using the shortcode renderer. */
if ( ! defined( 'ABSPATH' ) ) { exit; }
class Remote_Content_Shortcode_Block {
	public static function register() {
		add_action( 'init', array( __CLASS__, 'register_block' ) );
		add_action( 'enqueue_block_editor_assets', array( __CLASS__, 'localize_editor' ) );
	}
	public static function register_block() {
		register_block_type( dirname( __DIR__ ) . '/blocks/remote-content', array( 'render_callback' => array( __CLASS__, 'render' ) ) );
	}
	public static function localize_editor() {
		$aliases = array();
		if ( current_user_can( 'edit_posts' ) ) {
			$sources = apply_filters( 'remote_content_shortcode_credentials', array() );
			foreach ( is_array( $sources ) ? array_keys( $sources ) : array() as $alias ) {
				$alias = (string) $alias;
				if ( 1 === preg_match( '/^[A-Za-z0-9_.-]{1,64}$/D', $alias ) ) {
					$aliases[] = $alias;
				}
			}
		}
		$pro_url = apply_filters( 'remote_content_shortcode_pro_url', 'https://www.doublesharp.com/remote-content-shortcode-pro/' );
		wp_localize_script( 'remote-content-shortcode-remote-content-editor-script', 'rcsBlockEditor', array(
			'aliases' => $aliases,
			'toolsUrl' => current_user_can( 'manage_options' ) ? admin_url( 'tools.php?page=remote-content-shortcode' ) : '',
			'showProHint' => ! defined( 'RCS_PRO_VERSION' ) && (bool) apply_filters( 'remote_content_shortcode_show_pro_overview', true ),
			'proUrl' => is_string( $pro_url ) ? esc_url_raw( $pro_url, array( 'http', 'https' ) ) : '',
		) );
	}
	public static function render( $attributes ) {
		$atts = array();
		foreach ( array( 'url', 'selector', 'remove', 'fallback', 'userpwd', 'params', 'find', 'replace' ) as $key ) {
			if ( isset( $attributes[ $key ] ) && is_string( $attributes[ $key ] ) ) { $atts[ $key ] = $attributes[ $key ]; }
		}
		foreach ( array( 'cache' => 'cache', 'rewriteUrls' => 'rewrite_urls', 'htmlentities' => 'htmlentities', 'stripTags' => 'strip_tags' ) as $key => $target ) {
			if ( isset( $attributes[ $key ] ) && is_bool( $attributes[ $key ] ) ) { $atts[ $target ] = $attributes[ $key ] ? 'true' : 'false'; }
		}
		foreach ( array( 'cacheTtl' => 'cache_ttl', 'staleTtl' => 'stale_ttl', 'timeout' => 'timeout' ) as $key => $target ) {
			if ( isset( $attributes[ $key ] ) && is_numeric( $attributes[ $key ] ) ) { $atts[ $target ] = (string) max( 0, (float) $attributes[ $key ] ); }
		}
		return RemoteContentShortcode::get_instance()->remote_content_shortcode( $atts );
	}
}
