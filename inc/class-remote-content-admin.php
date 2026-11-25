<?php
/** Administrator-only builder, diagnostics, connections and cache controls. */
if ( ! defined( 'ABSPATH' ) ) { exit; }
class Remote_Content_Shortcode_Admin {
	private const SHORTCODE_DEFAULTS = array( 'method' => 'GET', 'timeout' => '10', 'cache' => 'true', 'cache_ttl' => '3600', 'stale_ttl' => '0', 'rewrite_urls' => 'false', 'strip_tags' => 'false', 'htmlentities' => 'false' );
	private const NUMBER_LIMITS = array( 'timeout' => array( 1, 30 ), 'cache_ttl' => array( 1, 86400 ), 'stale_ttl' => array( 0, 86400 ) );

	public static function register() {
		add_action( 'admin_menu', array( __CLASS__, 'menu' ) );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'assets' ) );
		add_action( 'admin_notices', array( __CLASS__, 'upgrade_notice' ) );
		add_action( 'admin_post_rcs_dismiss_upgrade_notice', array( __CLASS__, 'dismiss_upgrade_notice' ) );
	}
	public static function assets( $hook ) {
		if ( 'tools_page_remote-content-shortcode' !== $hook ) { return; }
		wp_enqueue_style( 'remote-content-shortcode-editor', plugins_url( 'admin.css', __FILE__ ), array(), (string) filemtime( __DIR__ . '/admin.css' ) );
	}
	public static function menu() { add_management_page( __( 'Remote content', 'remote-content-shortcode' ), __( 'Remote content', 'remote-content-shortcode' ), 'manage_options', 'remote-content-shortcode', array( __CLASS__, 'page' ) ); }
	public static function upgrade_notice() {
		if ( ! current_user_can( 'manage_options' ) || get_option( 'rcs_v2_upgrade_notice_dismissed' ) ) { return; }
		$checked = get_option( 'rcs_v2_upgrade_notice_checked' );
		if ( ! $checked ) {
			global $wpdb;
			$shortcode = '%' . $wpdb->esc_like( '[remote_content' ) . '%';
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Check for legacy usage once per site without loading post content.
			$match = $wpdb->get_var( $wpdb->prepare( "SELECT ID FROM {$wpdb->posts} WHERE post_type IN ('post','page') AND post_status NOT IN ('auto-draft','trash','inherit') AND post_content LIKE %s LIMIT 1", $shortcode ) );
			if ( $wpdb->last_error ) { return; }
			$checked = $match ? 'found' : 'none';
			update_option( 'rcs_v2_upgrade_notice_checked', $checked, false );
		}
		if ( 'found' !== $checked ) { return; }

		$tools = admin_url( 'tools.php?page=remote-content-shortcode' );
		echo '<div class="notice notice-warning"><p><strong>' . esc_html__( 'Remote Content Shortcode 2.0.0', 'remote-content-shortcode' ) . '</strong> ' . esc_html__( 'Review existing imports after upgrading. Remote HTML, CSS selectors, and credentials have new compatibility rules.', 'remote-content-shortcode' ) . '</p><p><a href="' . esc_url( add_query_arg( 'tab', 'usage', $tools ) ) . '">' . esc_html__( 'Review shortcode usage', 'remote-content-shortcode' ) . '</a> · <a href="' . esc_url( add_query_arg( 'tab', 'help', $tools ) ) . '">' . esc_html__( 'Open Remote content help', 'remote-content-shortcode' ) . '</a></p><form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '"><input type="hidden" name="action" value="rcs_dismiss_upgrade_notice">';
		wp_nonce_field( 'rcs_dismiss_upgrade_notice', 'rcs_upgrade_nonce' );
		echo '<p><button type="submit" class="button">' . esc_html__( 'Dismiss upgrade notice', 'remote-content-shortcode' ) . '</button></p></form></div>';
	}
	public static function dismiss_upgrade_notice() {
		if ( ! current_user_can( 'manage_options' ) ) { wp_die( esc_html__( 'Administrator access required.', 'remote-content-shortcode' ) ); }
		check_admin_referer( 'rcs_dismiss_upgrade_notice', 'rcs_upgrade_nonce' );
		update_option( 'rcs_v2_upgrade_notice_dismissed', '1', false );
		wp_safe_redirect( wp_get_referer() ?: admin_url( 'tools.php?page=remote-content-shortcode&tab=usage' ) );
		exit;
	}
	public static function field_help( $key ) {
		$help = array(
			'url' => __( 'Public HTTP(S) page or API endpoint to import.', 'remote-content-shortcode' ),
			'method' => __( 'GET reads content. POST sends the body below, is never cached, and requires a trusted grant for a repeat-safe endpoint.', 'remote-content-shortcode' ),
			'timeout' => __( 'Maximum wait in seconds, from 1 to 30.', 'remote-content-shortcode' ),
			'userpwd' => __( 'Use a saved connection alias from Connections or OAuth connections. Never enter a password here.', 'remote-content-shortcode' ),
			'params' => __( 'Forward only these visitor query parameters, separated by commas. Use language=en to provide a default.', 'remote-content-shortcode' ),
			'body' => __( 'Sent only with POST. This becomes part of your page content, so include public data only.', 'remote-content-shortcode' ),
			'selector' => __( 'Content to keep, such as main, .news or #summary. Leave blank for the whole response.', 'remote-content-shortcode' ),
			'remove' => __( 'CSS selector for elements to remove before selecting content, such as script, aside or .advertisement.', 'remote-content-shortcode' ),
			'find' => __( 'Optional regular expression, including delimiters, such as /old text/i. Leave blank to skip replacement.', 'remote-content-shortcode' ),
			'replace' => __( 'Text to insert for matches of Find pattern. An empty replacement removes the matches.', 'remote-content-shortcode' ),
			'fallback' => __( 'Content to show when the import fails and no usable saved response exists. Basic HTML is allowed.', 'remote-content-shortcode' ),
			'htmlentities' => __( 'Show the HTML source as readable text instead of rendering its tags.', 'remote-content-shortcode' ),
			'strip_tags' => __( 'Remove HTML tags to return plain text.', 'remote-content-shortcode' ),
			'rewrite_urls' => __( 'Convert relative links and image URLs to absolute URLs on the original website.', 'remote-content-shortcode' ),
			'cache' => __( 'Reuse public GET responses for logged-out visitors. Authenticated requests and POST always bypass this cache.', 'remote-content-shortcode' ),
			'cache_ttl' => __( 'How long a response stays fresh, in seconds. For example, 300 is five minutes and 3600 is one hour.', 'remote-content-shortcode' ),
			'stale_ttl' => __( 'Extra seconds a previous successful response may be used after fresh content expires if a refresh fails. Use 0 to disable.', 'remote-content-shortcode' ),
			'alias' => __( 'A short name used to select this connection in a source or shortcode. Use letters, numbers, dots, underscores or hyphens.', 'remote-content-shortcode' ),
			'origin' => __( 'The service origin, such as https://api.example.com. Do not include the endpoint path.', 'remote-content-shortcode' ),
			'path' => __( 'The exact permitted path, such as /v1/articles. The secret is not sent to other paths.', 'remote-content-shortcode' ),
			'header' => __( 'Used only for API key authentication. Enter the provider’s header name, such as X-API-Key.', 'remote-content-shortcode' ),
			'auth' => __( 'Choose the authentication type required by the service: Basic, Bearer token or an API key header.', 'remote-content-shortcode' ),
			'methods' => __( 'Allow only the request methods this connection needs. The credential is refused for other methods.', 'remote-content-shortcode' ),
		);
		return isset( $help[ $key ] ) ? $help[ $key ] : '';
	}
	public static function styles() {
		echo '<style>
		.rcs-admin{max-width:1400px}.rcs-admin .rcs-tabs{display:flex;flex-wrap:wrap;gap:4px;margin:12px 0 20px}.rcs-admin .rcs-tabs .nav-tab{float:none;margin:0}
		.rcs-section-switcher{display:none}.rcs-section-switcher>label{display:block;font-weight:600;margin-bottom:5px}.rcs-section-controls{display:flex;align-items:center;gap:8px}.rcs-section-controls select{flex:1;min-width:0;max-width:100%}
		.rcs-admin .rcs-copy-actions[hidden]{display:none}
		.rcs-config-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:16px;align-items:start}.rcs-panel{min-width:0;margin:0;background:#fff;border:1px solid #c3c4c7;padding:16px;box-sizing:border-box}.rcs-panel legend{font-size:15px;font-weight:600;padding:0 6px}.rcs-full{grid-column:1/-1}
		.rcs-admin .rcs-settings-form{margin:16px 0;background:#fff;border:1px solid #c3c4c7;padding:16px;max-width:1100px;box-sizing:border-box}
		.rcs-admin .form-table{margin:0}.rcs-admin .form-table tbody{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:14px 20px}.rcs-admin .form-table tr,.rcs-admin .form-table th,.rcs-admin .form-table td{display:block;width:auto;margin:0;padding:0}.rcs-admin .form-table th{margin-bottom:5px}.rcs-admin .regular-text,.rcs-admin textarea,.rcs-admin input[type=url],.rcs-admin input[type=email]{max-width:100%;box-sizing:border-box}.rcs-admin .form-table .regular-text{width:100%}
		.rcs-admin .description,.rcs-field .description{display:block;max-width:72ch;line-height:1.5;margin:5px 0 0}.rcs-field{min-width:0;margin:0}.rcs-field>label{display:block;font-weight:500;margin-bottom:5px}.rcs-field input:not([type=checkbox]),.rcs-field textarea,.rcs-field select{width:100%;max-width:100%;box-sizing:border-box}.rcs-inline-help{margin:12px 0}.rcs-inline-help summary{cursor:pointer;font-weight:500}.rcs-inline-help[open] summary{margin-bottom:8px}.rcs-form-actions{display:flex;flex-wrap:wrap;gap:8px;align-items:center;margin:16px 0}.rcs-checks{display:flex;flex-wrap:wrap;gap:10px 20px}
		.rcs-table-scroll{overflow-x:auto}.rcs-usage-table{table-layout:fixed;width:100%}.rcs-usage-table th,.rcs-usage-table td{vertical-align:middle;overflow-wrap:anywhere}.rcs-admin .widefat code{white-space:normal;overflow-wrap:anywhere}.rcs-usage-table .rcs-usage-title{width:55%;overflow-wrap:anywhere}.rcs-usage-mobile{display:none}.rcs-usage-meta{color:#50575e}.rcs-usage-footer{display:flex;flex-wrap:wrap;justify-content:space-between;align-items:center;gap:12px;margin-top:12px}
		.rcs-builder .rcs-essential tbody{grid-template-columns:minmax(0,2fr) minmax(0,1fr)}.rcs-builder .rcs-form-actions{margin:16px 0 8px}.rcs-builder-options{border-top:1px solid #dcdcde;margin-top:16px;padding-top:16px}.rcs-builder-options>summary{cursor:pointer;font-size:14px;font-weight:600;line-height:1.5;padding:4px 0}.rcs-builder-options>summary:focus-visible{outline:2px solid #3858e9;outline-offset:4px}.rcs-builder-options>summary .description{display:inline;font-size:13px;font-weight:400;margin-inline-start:8px}.rcs-builder-options[open]>summary{margin-bottom:16px}
		@media(max-width:782px){.rcs-config-grid,.rcs-admin .form-table tbody{grid-template-columns:1fr}.rcs-admin .rcs-tabs .nav-tab{font-size:13px;padding:7px 9px}.rcs-panel,.rcs-admin .rcs-settings-form{padding:12px}.rcs-usage-table .rcs-usage-title{width:auto}.rcs-usage-table .rcs-usage-type,.rcs-usage-table .rcs-usage-status{display:none}.rcs-usage-table .rcs-usage-actions{width:84px}.rcs-usage-mobile{display:inline}}
		.rcs-builder input[aria-invalid=true]{border-color:#d63638;box-shadow:0 0 0 1px #d63638}.rcs-field-error{color:#b32d2e;font-weight:600;margin:6px 0 4px}.rcs-url-notice-row{display:flex;flex-wrap:wrap;align-items:baseline;gap:4px 12px;margin:.5em 0;padding:2px}.rcs-url-notice-row a{white-space:nowrap}.rcs-url-notice-row summary{cursor:pointer}.rcs-builder-note{margin:0 0 16px}
		@media(min-width:783px){.rcs-builder .description,.rcs-builder .rcs-builder-options>summary .description{font-size:14px;line-height:1.5}}
		@media(max-width:782px){.rcs-builder .rcs-essential tbody{grid-template-columns:1fr}.rcs-builder-options{margin-top:12px;padding-top:12px}.rcs-builder-options>summary .description{display:block;margin:4px 0 0 15px}}
		@media(max-width:600px){.rcs-admin .rcs-tabs{display:none}.rcs-admin .rcs-section-switcher{display:block;margin:12px 0 20px}}
		</style>';
	}
	private static function fields() {
		return array( 'url' => 'Source URL', 'selector' => 'CSS selector', 'remove' => 'Remove selector', 'userpwd' => 'Credential alias', 'fallback' => 'Fallback HTML', 'timeout' => 'Timeout (seconds)', 'method' => 'HTTP method', 'find' => 'Find pattern', 'replace' => 'Replacement', 'params' => 'Forward query parameters', 'htmlentities' => 'Encode HTML entities', 'strip_tags' => 'Strip HTML tags', 'cache' => 'Enable public GET cache', 'cache_ttl' => 'Cache lifetime (seconds)', 'stale_ttl' => 'Stale lifetime (seconds)', 'rewrite_urls' => 'Rewrite relative URLs' );
	}
	/** Retain only bounded public form fields, never secrets or inline credentials. */
	private static function form_values( $input, $defaults ) {
		$values = $defaults;
		$choices = array( 'method' => array( 'GET', 'POST' ), 'auth' => array( 'basic', 'bearer', 'api_key' ) );
		foreach ( array( 'rewrite_urls', 'cache', 'strip_tags', 'htmlentities' ) as $key ) { $choices[ $key ] = array( 'true', 'false' ); }
		foreach ( $defaults as $key => $default ) {
			$value = $input[ $key ] ?? null;
			if ( ! is_string( $value ) || strlen( $value ) > ( 'body' === $key ? 65536 : 4096 ) ) { continue; }
			if ( isset( $choices[ $key ] ) && ! in_array( $value, $choices[ $key ], true ) ) { continue; }
			if ( in_array( $key, array( 'alias', 'userpwd' ), true ) && ! preg_match( '/^[A-Za-z0-9_.-]{0,64}$/D', $value ) ) { continue; }
			if ( in_array( $key, array( 'url', 'origin' ), true ) ) {
				$url = wp_parse_url( $value );
				if ( false === $url || isset( $url['user'] ) || isset( $url['pass'] ) || ( 'origin' === $key && ( isset( $url['query'] ) || isset( $url['fragment'] ) ) ) ) { continue; }
			}
			$values[ $key ] = $value;
		}
		return $values;
	}
	public static function process_action( $input ) {
		if ( ! current_user_can( 'manage_options' ) ) { return new WP_Error( 'forbidden', __( 'Administrator access required.', 'remote-content-shortcode' ) ); }
		$nonce = isset( $input['_wpnonce'] ) && is_string( $input['_wpnonce'] ) ? $input['_wpnonce'] : '';
		if ( ! wp_verify_nonce( $nonce, 'rcs_tools' ) ) { return new WP_Error( 'invalid_nonce', __( 'Security check failed.', 'remote-content-shortcode' ) ); }
		$atts = array();
		foreach ( self::fields() as $key => $label ) {
			if ( is_string( $input[ $key ] ?? null ) ) { $atts[ $key ] = $input[ $key ]; }
		}
		foreach ( self::NUMBER_LIMITS as $key => list( $min, $max ) ) {
			unset( $atts[ $key ] );
			if ( is_numeric( $input[ $key ] ?? null ) ) { $atts[ $key ] = (string) (int) max( $min, min( $max, (float) $input[ $key ] ) ); }
		}
		if ( isset( $atts['method'] ) ) { $atts['method'] = strtoupper( $atts['method'] ); }
		foreach ( array( 'rewrite_urls', 'cache', 'strip_tags', 'htmlentities' ) as $key ) { if ( isset( $atts[ $key ] ) && ! in_array( $atts[ $key ], array( 'true', 'false' ), true ) ) { unset( $atts[ $key ] ); } }
		if ( isset( $atts['userpwd'] ) && ! preg_match( '/^[A-Za-z0-9_.-]{0,64}$/D', $atts['userpwd'] ) ) { return new WP_Error( 'invalid_alias', __( 'Enter a configured credential alias.', 'remote-content-shortcode' ) ); }
		$action = isset( $input['rcs_action'] ) && is_string( $input['rcs_action'] ) ? $input['rcs_action'] : '';
		$body = is_string( $input['body'] ?? null ) ? $input['body'] : null;
		if ( 'test' === $action ) { return RemoteContentShortcode::get_instance()->diagnose( $atts, $body ); }
		if ( 'clear' === $action ) { return RemoteContentShortcode::get_instance()->clear_cache( $atts['url'] ?? '' ); }
		if ( 'generate' !== $action ) { return new WP_Error( 'invalid_action', __( 'Unknown action.', 'remote-content-shortcode' ) ); }
		if ( null !== $body && ( strlen( $body ) > 65536 || preg_match( '/\[\/?remote_content\b/i', $body ) ) ) { return new WP_Error( 'invalid_builder_body', __( 'Remove remote_content shortcode delimiters from the POST body. Use a source filter for these payloads.', 'remote-content-shortcode' ) ); }
		if ( 'POST' !== ( $atts['method'] ?? 'GET' ) ) { $body = null; }
		$url = wp_parse_url( $atts['url'] ?? '' );
		if ( ! $url || isset( $url['user'] ) || isset( $url['pass'] ) || ! in_array( $url['scheme'] ?? '', array( 'https', 'http' ), true ) || empty( $url['host'] ) ) { return new WP_Error( 'invalid_url' ); }
		$shortcode = '[remote_content decode_atts="true"';
		foreach ( $atts as $key => $value ) {
			if ( strlen( $value ) > 4096 ) { return new WP_Error( 'invalid_attributes' ); }
			if ( '' === $value || $value === ( self::SHORTCODE_DEFAULTS[ $key ] ?? null ) ) { continue; }
			$encoded = str_replace( array( '\\', '[', ']' ), array( '&#92;', '&#91;', '&#93;' ), htmlspecialchars( '"' . $value . '"', ENT_QUOTES | ENT_SUBSTITUTE | ENT_HTML5, 'UTF-8' ) );
			$shortcode .= ' ' . $key . '="' . $encoded . '"';
		}
		return $shortcode . ']' . ( null !== $body && '' !== $body ? $body . '[/remote_content]' : '' );
	}
	public static function page() {
		if ( ! current_user_can( 'manage_options' ) ) { wp_die( esc_html__( 'Administrator access required.', 'remote-content-shortcode' ) ); }
		$tabs = apply_filters( 'remote_content_shortcode_admin_tabs', array( 'builder' => __( 'Builder', 'remote-content-shortcode' ), 'connections' => __( 'Connections', 'remote-content-shortcode' ), 'cache' => __( 'Cache', 'remote-content-shortcode' ), 'usage' => __( 'Usage', 'remote-content-shortcode' ), 'help' => __( 'Help', 'remote-content-shortcode' ) ) );
		$tabs = is_array( $tabs ) ? $tabs : array();
		foreach ( $tabs as $slug => $label ) { if ( ! is_string( $slug ) || ! preg_match( '/^[a-z][a-z0-9_-]{0,63}$/D', $slug ) || ! is_string( $label ) ) { unset( $tabs[ $slug ] ); } }
		$ordered = array();
		foreach ( array( 'builder', 'sources', 'connections', 'oauth', 'cache', 'history', 'usage', 'permissions' ) as $slug ) { if ( isset( $tabs[ $slug ] ) ) { $ordered[ $slug ] = $tabs[ $slug ]; } }
		foreach ( $tabs as $slug => $label ) { if ( ! isset( $ordered[ $slug ] ) && ! in_array( $slug, array( 'license', 'help' ), true ) ) { $ordered[ $slug ] = $label; } }
		foreach ( array( 'license', 'help' ) as $slug ) { if ( isset( $tabs[ $slug ] ) ) { $ordered[ $slug ] = $tabs[ $slug ]; } }
		$tabs = $ordered;
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only navigation uses the validated tab allowlist.
		$requested_tab = is_string( $_GET['tab'] ?? null ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : '';
		$tab = isset( $tabs[ $requested_tab ] ) ? $requested_tab : 'builder';
		$result = null; $input = array();
		$method = is_string( $_SERVER['REQUEST_METHOD'] ?? null ) ? sanitize_text_field( wp_unslash( $_SERVER['REQUEST_METHOD'] ) ) : '';
		if ( 'POST' === $method ) {
			if ( 'connections' === $tab ) {
				check_admin_referer( 'rcs_connections' ); $input = wp_unslash( $_POST );
				if ( 'save' === ( $input['connection_action'] ?? '' ) ) { $result = Remote_Content_Shortcode_Connections::save( $input ); }
				elseif ( 'delete' === ( $input['connection_action'] ?? '' ) && is_string( $input['alias'] ?? null ) ) { $result = Remote_Content_Shortcode_Connections::delete( $input['alias'], $input ); }
				elseif ( 'save_origins' === ( $input['connection_action'] ?? '' ) ) {
					$origins = $input['resource_origins'] ?? '';
					$result = is_string( $origins ) ? Remote_Content_Shortcode_Output::save_origins( $origins ) : new WP_Error( 'invalid_resource_origin' );
				}
				else { $result = new WP_Error( 'invalid_action' ); }
			} elseif ( in_array( $tab, array( 'builder', 'cache' ), true ) ) { check_admin_referer( 'rcs_tools' ); $input = wp_unslash( $_POST ); $result = self::process_action( $input ); }
		}
		self::styles();
		echo '<div class="wrap rcs-admin' . ( 'builder' === $tab ? ' rcs-import-page' : '' ) . '"><h1>' . esc_html__( 'Remote content', 'remote-content-shortcode' ) . '</h1><form class="rcs-section-switcher" method="get" action="' . esc_url( admin_url( 'tools.php' ) ) . '"><input type="hidden" name="page" value="remote-content-shortcode"><label for="rcs-section">' . esc_html__( 'Section', 'remote-content-shortcode' ) . '</label><div class="rcs-section-controls"><select id="rcs-section" name="tab">';
		foreach ( $tabs as $slug => $label ) { echo '<option value="' . esc_attr( $slug ) . '"' . selected( $tab, $slug, false ) . '>' . esc_html( $label ) . '</option>'; }
		echo '</select><button class="button" type="submit">' . esc_html__( 'Open section', 'remote-content-shortcode' ) . '</button></div></form><nav class="nav-tab-wrapper rcs-tabs" aria-label="Remote content sections">';
		foreach ( $tabs as $slug => $label ) { echo '<a class="nav-tab' . ( $slug === $tab ? ' nav-tab-active' : '' ) . '"' . ( $slug === $tab ? ' aria-current="page"' : '' ) . ' href="' . esc_url( add_query_arg( array( 'page' => 'remote-content-shortcode', 'tab' => $slug ), admin_url( 'tools.php' ) ) ) . '">' . esc_html( $label ) . '</a>'; }
		echo '</nav>';
		$url_error = 'builder' === $tab && self::is_url_error( $result );
		if ( 'builder' !== $tab ) { self::result( $result, $tab, $url_error ); }
		switch ( $tab ) {
			case 'builder': self::workspace( $input, $result, $url_error ); break;
			case 'connections': self::connections( is_wp_error( $result ) ? $input : array() ); break;
			case 'cache': self::cache(); break;
			case 'usage': self::usage(); break;
			case 'help': self::help(); break;
			default: do_action( 'remote_content_shortcode_admin_tab_' . $tab );
		}
		self::pro_overview( $tab );
		echo '</div>';
	}
	private static function show_pro_overview() {
		return ! defined( 'RCS_PRO_VERSION' ) && apply_filters( 'remote_content_shortcode_show_pro_overview', true );
	}
	private static function pro_url() {
		return apply_filters( 'remote_content_shortcode_pro_url', 'https://www.doublesharp.com/remote-content-shortcode-pro/' );
	}
	private static function pro_overview( $tab ) {
		if ( ! in_array( $tab, array( 'builder', 'connections', 'cache', 'usage', 'help' ), true ) || ! self::show_pro_overview() ) { return; }
		echo '<section id="rcs-pro-overview" class="card" aria-labelledby="rcs-pro-heading"><h2 id="rcs-pro-heading">' . esc_html__( 'Remote Content Shortcode Pro', 'remote-content-shortcode' ) . '</h2><p>' . esc_html__( 'For recurring imports and teams managing multiple sources.', 'remote-content-shortcode' ) . '</p><ul class="ul-disc">';
		foreach ( array( __( 'Reusable saved sources and JSON templates', 'remote-content-shortcode' ), __( 'Scheduled refreshes, history and opt-in failure alerts', 'remote-content-shortcode' ), __( 'OAuth connections', 'remote-content-shortcode' ), __( 'Delegated source management and multisite tools', 'remote-content-shortcode' ) ) as $feature ) { echo '<li>' . esc_html( $feature ) . '</li>'; }
		echo '</ul><p><a class="button button-secondary" href="' . esc_url( self::pro_url() ) . '">' . esc_html__( 'Learn about Pro', 'remote-content-shortcode' ) . '</a></p><p class="description">' . esc_html__( 'Your shortcode, block, cache and Basic/Bearer/API-key connections remain included in Free.', 'remote-content-shortcode' ) . '</p><p class="description">' . esc_html__( 'No telemetry or license kill switches. Installed Pro features keep working without renewal.', 'remote-content-shortcode' ) . '</p></section>';
	}
	private static function is_url_error( $result ) {
		return ( is_wp_error( $result ) && 'invalid_url' === $result->get_error_code() ) || ( is_array( $result ) && 'invalid_url' === ( $result['error_code'] ?? null ) );
	}
	private static function url_error_notice() {
		echo '<div class="notice notice-error rcs-url-notice"><div class="rcs-url-notice-row"><strong>' . esc_html__( 'The source URL could not be used.', 'remote-content-shortcode' ) . '</strong> <a href="#rcs-url" onclick="var f=document.getElementById(\'rcs-url\');if(f){f.focus();f.select();return false;}">' . esc_html__( 'Correct the Source URL', 'remote-content-shortcode' ) . '</a> <details><summary>' . esc_html__( 'Error details', 'remote-content-shortcode' ) . '</summary>' . esc_html__( 'Error code:', 'remote-content-shortcode' ) . ' <code>invalid_url</code></details></div></div>';
	}
	private static function workspace( $input, $result, $url_error ) {
		wp_enqueue_script( 'remote-content-shortcode-admin', plugins_url( 'admin.js', __FILE__ ), array(), (string) filemtime( __DIR__ . '/admin.js' ), true );
		wp_enqueue_script( 'remote-content-shortcode-workspace', plugins_url( 'admin-workspace.js', __FILE__ ), array( 'remote-content-shortcode-admin' ), (string) filemtime( __DIR__ . '/admin-workspace.js' ), true );
		wp_localize_script( 'remote-content-shortcode-workspace', 'rcsEditorMessages', array(
			'stalePreview' => __( 'Needs refresh', 'remote-content-shortcode' ), 'staleCode' => __( 'Settings changed', 'remote-content-shortcode' ),
			'testing' => __( 'Fetching fresh content…', 'remote-content-shortcode' ), 'generating' => __( 'Generating shortcode…', 'remote-content-shortcode' ),
			'changedDuringRequest' => __( 'Settings changed while the request was running. Test or generate again to use your latest settings.', 'remote-content-shortcode' ),
			'ready' => __( 'Ready to copy', 'remote-content-shortcode' ), 'unverified' => __( 'Generated · preview not verified', 'remote-content-shortcode' ),
			'cannotGenerate' => __( 'Fix the error before copying', 'remote-content-shortcode' ),
			'testComplete' => __( 'Preview and shortcode updated.', 'remote-content-shortcode' ), 'generated' => __( 'Shortcode updated. Your preview is kept.', 'remote-content-shortcode' ),
			'networkError' => __( 'The request could not be completed. Your settings are kept. Try again, or reload if your WordPress session has expired.', 'remote-content-shortcode' ),
		) );
		$test = 'test' === ( $input['rcs_action'] ?? '' );
		$tested = $test && is_array( $result );
		$ok = $tested && ! empty( $result['ok'] );
		$code = is_string( $result ) ? $result : null; $generated = null;
		if ( $tested ) { $generate = $input; $generate['rcs_action'] = 'generate'; $generated = self::process_action( $generate ); if ( is_string( $generated ) ) { $code = $generated; } }
		$state = $ok ? 'ready' : ( $test ? 'error' : 'empty' );
		$shortcode_state = $tested && ! $ok ? 'unverified' : ( is_string( $code ) ? 'ready' : 'empty' );
		echo '<div class="rcs-editor-heading"><div><h2>' . esc_html__( 'Build your import', 'remote-content-shortcode' ) . '</h2><p>' . esc_html__( 'Test a source and copy a shortcode for your post.', 'remote-content-shortcode' ) . '</p></div><nav class="rcs-editor-jumps" aria-label="' . esc_attr__( 'Import editor', 'remote-content-shortcode' ) . '"><a href="#rcs-configure">' . esc_html__( 'Configure', 'remote-content-shortcode' ) . '</a><a href="#rcs-preview-panel">' . esc_html__( 'Preview', 'remote-content-shortcode' ) . '</a><a href="#rcs-shortcode-panel">' . esc_html__( 'Shortcode', 'remote-content-shortcode' ) . '</a></nav></div>';
		echo '<div class="rcs-workspace" aria-busy="false"><div class="rcs-configure" id="rcs-configure">';
		self::builder( $input, $url_error );
		echo '</div><div class="rcs-results"><div id="rcs-editor-feedback" role="status" aria-live="polite">';
		if ( $url_error ) { self::url_error_notice(); }
		elseif ( is_wp_error( $result ) || is_int( $result ) ) { self::result( $result, 'builder' ); }
		elseif ( $tested && ! $ok ) {
			$messages = array(
				'selector_no_matches'     => __( 'No matching content. Change the CSS selector or leave it blank to include the whole response.', 'remote-content-shortcode' ),
				'invalid_selector'        => __( 'Check the CSS selector, then test again.', 'remote-content-shortcode' ),
				'request_timeout'         => __( 'The source did not respond within five seconds. Try again or check the source website.', 'remote-content-shortcode' ),
				'http_status_error'       => __( 'The source returned an error. Check the address and any required connection, then try again.', 'remote-content-shortcode' ),
				'dns_unavailable'         => __( 'No DNS resolvers are available. Ask your host to configure DNS or set REMOTE_CONTENT_DNS_SERVERS in wp-config.php.', 'remote-content-shortcode' ),
				'secret_unavailable'      => __( 'The connection secret is missing or unreadable. Re-enter and save it in Connections, especially if the WordPress salts were rotated.', 'remote-content-shortcode' ),
				'post_render_not_allowed' => __( 'POST rendering requires a trusted grant for a repeat-safe endpoint. Use GET or configure the remote_content_shortcode_allow_post_render filter.', 'remote-content-shortcode' ),
				'html_too_complex'        => __( 'The source HTML is too complex to process. Use an endpoint that returns a smaller HTML fragment.', 'remote-content-shortcode' ),
				'selector_too_complex'    => __( 'The CSS selector is too expensive for this response. Try a simpler tag, class, or ID selector, or use a smaller response.', 'remote-content-shortcode' ),
				'request_busy'            => __( 'A request for this source is already in progress. Wait a moment, then test again.', 'remote-content-shortcode' ),
				'render_budget_exceeded'  => __( 'This page reached the limit for rendering remote imports. Use fewer imports on the page, or test one import at a time.', 'remote-content-shortcode' ),
				'request_budget_exceeded' => __( 'The request ran out of time or the page reached its request limit. Try again, use a faster source, or reduce the number of imports.', 'remote-content-shortcode' ),
				'response_too_large'      => __( 'The response reached the 1 MiB limit. Use an endpoint that returns a smaller response.', 'remote-content-shortcode' ),
			);
			echo '<div class="notice notice-error"><p>' . esc_html( $messages[ $result['error_code'] ?? '' ] ?? __( 'This import could not be verified. Check the source and request settings, then test again. Open Diagnostics for details.', 'remote-content-shortcode' ) ) . '</p></div>';
		}
		echo '</div><section id="rcs-preview-panel" class="rcs-editor-panel" aria-labelledby="rcs-preview-heading" data-state="' . esc_attr( $state ) . '"><header class="rcs-panel-heading"><h2 id="rcs-preview-heading"><span class="dashicons dashicons-visibility" aria-hidden="true"></span> ' . esc_html__( 'Content preview', 'remote-content-shortcode' ) . '</h2><span id="rcs-preview-status" class="rcs-status" role="status" aria-live="polite">' . esc_html( $ok ? __( 'Up to date', 'remote-content-shortcode' ) : ( $tested && ! empty( $result['html'] ) ? __( 'Fallback preview', 'remote-content-shortcode' ) : __( 'Not tested', 'remote-content-shortcode' ) ) ) . '</span></header><div id="rcs-preview-content">';
		if ( $tested ) {
			self::preview_result( $result );
			if ( $ok && str_contains( (string) ( $result['content_type'] ?? '' ), 'json' ) && self::show_pro_overview() ) {
				echo '<p class="rcs-pro-tip">' . esc_html__( 'Pro can display JSON using escaped JSON templates with bounded loops.', 'remote-content-shortcode' ) . ' <a href="' . esc_url( self::pro_url() ) . '">' . esc_html__( 'Learn about Pro', 'remote-content-shortcode' ) . '</a></p>';
			}
		}
		else { echo '<div class="rcs-preview-empty"><span class="dashicons dashicons-welcome-view-site" aria-hidden="true"></span><h3>' . esc_html__( 'Preview an import', 'remote-content-shortcode' ) . '</h3><p>' . esc_html__( 'Enter a source URL and choose Test content to preview your import here.', 'remote-content-shortcode' ) . '</p><p class="description">' . esc_html__( 'Testing fetches the source without using the visitor cache.', 'remote-content-shortcode' ) . '</p></div>'; }
		echo '</div></section><section id="rcs-shortcode-panel" class="rcs-editor-panel" aria-labelledby="rcs-shortcode-heading" data-state="' . esc_attr( $shortcode_state ) . '"><header class="rcs-panel-heading"><h2 id="rcs-shortcode-heading"><span class="dashicons dashicons-shortcode" aria-hidden="true"></span> ' . esc_html__( 'Shortcode', 'remote-content-shortcode' ) . '</h2><span id="rcs-shortcode-status" class="rcs-status" role="status" aria-live="polite">' . esc_html( is_string( $code ) ? ( $ok ? __( 'Ready to copy', 'remote-content-shortcode' ) : __( 'Generated · preview not verified', 'remote-content-shortcode' ) ) : __( 'No shortcode yet', 'remote-content-shortcode' ) ) . '</span></header><div id="rcs-shortcode-content">';
		if ( is_string( $code ) ) { self::result( $code, 'builder' ); }
		elseif ( is_wp_error( $generated ) && ! $url_error ) { self::result( $generated, 'builder' ); }
		else { echo '<p>' . esc_html__( 'Test content to get a preview and shortcode together, or generate a shortcode without fetching the source.', 'remote-content-shortcode' ) . '</p>'; }
		echo '</div></section></div></div>';
	}
	private static function preview_result( $result ) {
		$ok = ! empty( $result['ok'] );
		if ( is_string( $result['html'] ?? null ) && '' !== $result['html'] ) {
			$html = wp_kses_post( $result['html'] );
			$preview = '<!doctype html><html lang="' . esc_attr( get_bloginfo( 'language' ) ) . '" dir="' . ( is_rtl() ? 'rtl' : 'ltr' ) . '"><head><meta charset="utf-8"><meta http-equiv="Content-Security-Policy" content="default-src \'none\'; style-src \'unsafe-inline\'; img-src http: https: data:; base-uri \'none\'; form-action \'none\'"><meta name="viewport" content="width=device-width, initial-scale=1"><title>' . esc_html__( 'Remote content preview', 'remote-content-shortcode' ) . '</title><style>body{font:16px/1.6 system-ui,sans-serif;color:#1d2327;margin:24px;overflow-wrap:anywhere}img,video,table{max-width:100%}pre{white-space:pre-wrap}h1,h2,h3{line-height:1.2}</style></head><body>' . $html . '</body></html>';
			echo '<iframe sandbox title="' . esc_attr__( 'Remote content preview', 'remote-content-shortcode' ) . '" referrerpolicy="no-referrer" srcdoc="' . esc_attr( $preview ) . '"></iframe>';
		}
		$summary = __( 'Import not verified', 'remote-content-shortcode' );
		if ( $ok ) {
			$matches = $result['selector_matches'] ?? null;
			/* translators: %d: Number of elements matching the CSS selector. */
			$summary = null === $matches ? __( 'Full response', 'remote-content-shortcode' ) : sprintf( _n( '%d matching element', '%d matching elements', $matches, 'remote-content-shortcode' ), $matches );
		}
		echo '<div class="rcs-preview-meta"><span class="dashicons ' . ( $ok ? 'dashicons-yes-alt' : 'dashicons-warning' ) . '" aria-hidden="true"></span><span>' . esc_html( $summary ) . '</span><span>' . esc_html__( 'Fresh test · visitor cache bypassed', 'remote-content-shortcode' ) . '</span></div><details class="rcs-diagnostics"><summary>' . esc_html__( 'Diagnostics', 'remote-content-shortcode' ) . '</summary><dl>';
		$labels = array( 'ok' => __( 'Successful import', 'remote-content-shortcode' ), 'error_code' => __( 'Error code', 'remote-content-shortcode' ), 'http_status' => __( 'HTTP status', 'remote-content-shortcode' ), 'cache_status' => __( 'Cache', 'remote-content-shortcode' ), 'selector_matches' => __( 'Matching elements', 'remote-content-shortcode' ), 'duration_ms' => __( 'Elapsed time (ms)', 'remote-content-shortcode' ) );
		foreach ( $labels as $key => $label ) {
			$value = isset( $result[ $key ] ) && is_scalar( $result[ $key ] ) ? (string) $result[ $key ] : __( 'Not available', 'remote-content-shortcode' );
			if ( 'ok' === $key ) { $value = $ok ? __( 'Yes', 'remote-content-shortcode' ) : __( 'No', 'remote-content-shortcode' ); }
			echo '<dt>' . esc_html( $label ) . '</dt><dd>' . esc_html( $value ) . '</dd>';
		}
		echo '</dl></details>';
		if ( isset( $html ) ) { echo '<details class="rcs-diagnostics"><summary>' . esc_html__( 'Sanitized HTML', 'remote-content-shortcode' ) . '</summary><textarea class="large-text code" readonly rows="6" aria-label="' . esc_attr__( 'Sanitized HTML', 'remote-content-shortcode' ) . '">' . esc_textarea( $html ) . '</textarea></details>'; }
	}
	private static function result( $result, $tab, $url_error = false ) {
		if ( $url_error ) { self::url_error_notice(); }
		if ( is_wp_error( $result ) ) {
			if ( $url_error ) { return; }
			echo '<div class="notice notice-error"><p>' . esc_html__( 'Request failed.', 'remote-content-shortcode' ) . ' ' . esc_html( sanitize_key( $result->get_error_code() ) );
			if ( 'invalid_builder_body' === $result->get_error_code() ) { echo ' ' . esc_html__( 'Remove remote_content shortcode delimiters from the POST body. Use a source filter for these payloads.', 'remote-content-shortcode' ); }
			if ( 'invalid_resource_origin' === $result->get_error_code() ) { echo ' ' . esc_html__( 'Enter a public HTTPS origin on each line. Remove paths, queries, fragments, and credentials.', 'remote-content-shortcode' ); }
			if ( 'resource_origin_limit' === $result->get_error_code() ) {
				/* translators: %d: Maximum number of trusted image origins. */
				echo ' ' . esc_html( sprintf( __( 'Save up to %d trusted image origins.', 'remote-content-shortcode' ), Remote_Content_Shortcode_Output::MAX_ORIGINS ) );
			}
			echo '</p></div>';
		} elseif ( is_int( $result ) ) {
			/* translators: %d: Number of removed cache entries. */
			$message = 0 === $result ? __( 'Plugin cache cleared (no matching entries found).', 'remote-content-shortcode' ) : sprintf( _n( 'Plugin cache cleared (%d entry removed).', 'Plugin cache cleared (%d entries removed).', $result, 'remote-content-shortcode' ), $result );
			echo '<div class="notice notice-success"><p>' . esc_html( $message ) . '</p></div>';
		} elseif ( 'connections' === $tab && ( is_array( $result ) || is_string( $result ) || true === $result ) ) {
			echo '<div class="notice notice-success"><p>' . esc_html( is_array( $result ) ? __( 'Trusted image origins saved.', 'remote-content-shortcode' ) : __( 'Connection updated.', 'remote-content-shortcode' ) ) . '</p></div>';
		} elseif ( is_string( $result ) ) {
			wp_enqueue_script( 'remote-content-shortcode-admin', plugins_url( 'admin.js', __FILE__ ), array(), (string) filemtime( __DIR__ . '/admin.js' ), true );
			echo '<h2>' . esc_html__( 'Generated shortcode', 'remote-content-shortcode' ) . '</h2><p>' . esc_html__( 'Paste this into a Shortcode block. Keep the encoded characters as shown.', 'remote-content-shortcode' ) . '</p><p class="rcs-form-actions rcs-copy-actions" hidden><button class="button" type="button" data-rcs-copy aria-controls="rcs-generated-shortcode" data-status="rcs-copy-status" data-success="' . esc_attr__( 'Shortcode copied.', 'remote-content-shortcode' ) . '" data-fallback="' . esc_attr__( 'Shortcode selected. Use your browser’s Copy command.', 'remote-content-shortcode' ) . '">' . esc_html__( 'Copy shortcode', 'remote-content-shortcode' ) . '</button><span id="rcs-copy-status" role="status" aria-live="polite"></span></p><details class="rcs-code-details"><summary>' . esc_html__( 'Inspect shortcode', 'remote-content-shortcode' ) . '</summary><textarea id="rcs-generated-shortcode" class="large-text code" rows="6" readonly aria-label="' . esc_attr__( 'Generated shortcode', 'remote-content-shortcode' ) . '">' . esc_textarea( $result ) . '</textarea></details>';
		} elseif ( is_array( $result ) ) {
			echo '<h2>' . esc_html__( 'Diagnostics', 'remote-content-shortcode' ) . '</h2><dl>';
			$labels = array( 'ok' => __( 'Successful import', 'remote-content-shortcode' ), 'error_code' => __( 'Error code', 'remote-content-shortcode' ), 'http_status' => __( 'HTTP status', 'remote-content-shortcode' ), 'cache_status' => __( 'Cache', 'remote-content-shortcode' ), 'selector_matches' => __( 'Matching elements', 'remote-content-shortcode' ), 'duration_ms' => __( 'Elapsed time (ms)', 'remote-content-shortcode' ) );
			foreach ( $labels as $key => $label ) {
				$value = isset( $result[ $key ] ) && is_scalar( $result[ $key ] ) ? (string) $result[ $key ] : __( 'Not available', 'remote-content-shortcode' );
				if ( 'ok' === $key && isset( $result[ $key ] ) && is_bool( $result[ $key ] ) ) { $value = $result[ $key ] ? __( 'Yes', 'remote-content-shortcode' ) : __( 'No', 'remote-content-shortcode' ); }
				echo '<dt>' . esc_html( $label ) . '</dt><dd>' . esc_html( $value ) . '</dd>';
			}
			echo '</dl>';
			if ( is_string( $result['html'] ?? null ) ) {
				$html = wp_kses_post( $result['html'] );
				$preview = '<!doctype html><html lang="' . esc_attr( get_bloginfo( 'language' ) ) . '" dir="' . ( is_rtl() ? 'rtl' : 'ltr' ) . '"><head><meta charset="utf-8"><meta http-equiv="Content-Security-Policy" content="default-src \'none\'; style-src \'unsafe-inline\'; img-src http: https: data:; base-uri \'none\'; form-action \'none\'"><meta name="viewport" content="width=device-width, initial-scale=1"><title>' . esc_html__( 'Remote content preview', 'remote-content-shortcode' ) . '</title></head><body>' . $html . '</body></html>';
				echo '<h2>' . esc_html__( 'Preview', 'remote-content-shortcode' ) . '</h2><iframe sandbox title="' . esc_attr__( 'Remote content preview', 'remote-content-shortcode' ) . '" referrerpolicy="no-referrer" style="width:100%;height:20rem;box-sizing:border-box;border:1px solid #c3c4c7;background:#fff" srcdoc="' . esc_attr( $preview ) . '"></iframe><details><summary>' . esc_html__( 'Sanitized HTML', 'remote-content-shortcode' ) . '</summary><textarea class="large-text code" readonly rows="6" aria-label="' . esc_attr__( 'Sanitized HTML', 'remote-content-shortcode' ) . '">' . esc_textarea( $html ) . '</textarea></details>';
			}
		}
	}
	private static function builder( $input = array(), $url_error = false ) {
		$defaults = self::SHORTCODE_DEFAULTS + array_fill_keys( array_keys( self::fields() ), '' ) + array( 'body' => '' );
		$values = self::form_values( $input, $defaults );
		echo '<form id="rcs-builder-form" class="rcs-settings-form rcs-builder" method="post"><h2 class="rcs-configure-heading"><span class="dashicons dashicons-admin-links" aria-hidden="true"></span> ' . esc_html__( 'Source & content', 'remote-content-shortcode' ) . '</h2>'; wp_nonce_field( 'rcs_tools' );
		echo '<table class="form-table rcs-essential" role="presentation"><tbody>';
		foreach ( array( 'url', 'selector' ) as $key ) { self::builder_field( $key, $values, 'url' === $key && $url_error ); }
		echo '</tbody></table><p class="rcs-form-actions"><button class="button button-primary" type="submit" name="rcs_action" value="test">' . esc_html__( 'Test content', 'remote-content-shortcode' ) . '</button> <button class="button" type="submit" name="rcs_action" value="generate">' . esc_html__( 'Generate shortcode', 'remote-content-shortcode' ) . '</button></p><p class="description">' . esc_html__( 'Tests fetch fresh content and stop after five seconds.', 'remote-content-shortcode' ) . '</p>';
		$groups = array(
			array( __( 'Request settings', 'remote-content-shortcode' ), __( 'Method, timeout, connections and POST data', 'remote-content-shortcode' ), array( 'method', 'timeout', 'userpwd', 'params', 'body' ), __( 'Test requests fetch fresh content and bypass visitor caching. Cache settings belong in the shortcode or block.', 'remote-content-shortcode' ) . ' ' . __( 'Shortcode timeouts can be up to 30 seconds. Test requests are capped at five seconds.', 'remote-content-shortcode' ) ),
			array( __( 'Content and display', 'remote-content-shortcode' ), __( 'Remove elements, replace text and set fallback content', 'remote-content-shortcode' ), array( 'remove', 'find', 'replace', 'fallback', 'rewrite_urls', 'strip_tags', 'htmlentities' ) ),
			array( __( 'Caching', 'remote-content-shortcode' ), __( 'Cache lifetime, outage fallback and clearing', 'remote-content-shortcode' ), array( 'cache', 'cache_ttl', 'stale_ttl' ) ),
		);
		foreach ( $groups as $group ) {
			list( $heading, $description, $keys ) = $group;
			$changed = false;
			foreach ( $keys as $key ) { if ( $values[ $key ] !== $defaults[ $key ] ) { $changed = true; } }
			echo '<details class="rcs-builder-options"' . ( $changed ? ' open' : '' ) . '><summary>' . esc_html( $heading ) . '<span class="description">' . esc_html( $description ) . '</span></summary>' . ( isset( $group[3] ) ? '<p class="description rcs-builder-note">' . esc_html( $group[3] ) . '</p>' : '' ) . '<table class="form-table" role="presentation"><tbody>';
			foreach ( $keys as $key ) { self::builder_field( $key, $values ); }
			echo '</tbody></table>';
			if ( in_array( 'cache', $keys, true ) ) {
				echo '<p class="rcs-form-actions"><button class="button" name="rcs_action" value="clear">' . esc_html__( 'Clear cache', 'remote-content-shortcode' ) . '</button><span class="description">' . esc_html__( 'Clear the cache for the source URL above, or leave it blank to clear all plugin entries.', 'remote-content-shortcode' ) . '</span></p>';
			}
			echo '</details>';
		}
		echo '</form>';
	}
	private static function builder_field( $key, $values, $error = false ) {
		$labels = self::fields() + array( 'body' => __( 'POST body', 'remote-content-shortcode' ) );
		echo '<tr><th><label for="rcs-' . esc_attr( $key ) . '">' . esc_html( $labels[ $key ] ) . '</label></th><td>';
		$help_id = 'rcs-help-' . $key;
		$described = $error ? 'rcs-error-' . $key . ' ' . $help_id : $help_id;
		if ( in_array( $key, array( 'rewrite_urls', 'cache', 'strip_tags', 'htmlentities', 'method' ), true ) ) {
			echo '<select id="rcs-' . esc_attr( $key ) . '" name="' . esc_attr( $key ) . '" aria-describedby="' . esc_attr( $help_id ) . '">';
			foreach ( 'method' === $key ? array( 'GET' => 'GET', 'POST' => 'POST' ) : array( 'true' => __( 'Yes', 'remote-content-shortcode' ), 'false' => __( 'No', 'remote-content-shortcode' ) ) as $value => $label ) { echo '<option value="' . esc_attr( $value ) . '"' . selected( $values[ $key ], $value, false ) . '>' . esc_html( $label ) . '</option>'; }
			echo '</select>';
		} elseif ( 'body' === $key ) {
			echo '<textarea class="large-text code" id="rcs-body" name="body" rows="3" maxlength="65536" aria-describedby="' . esc_attr( $help_id ) . '">' . esc_textarea( $values['body'] ) . '</textarea>';
		} else {
			$limits = self::NUMBER_LIMITS[ $key ] ?? null;
			echo '<input class="regular-text" id="rcs-' . esc_attr( $key ) . '" name="' . esc_attr( $key ) . '" type="' . ( $limits ? 'number' : 'text' ) . '" aria-describedby="' . esc_attr( $described ) . '"' . ( $error ? ' aria-invalid="true"' : '' );
			if ( $limits ) { echo ' min="' . esc_attr( $limits[0] ) . '" max="' . esc_attr( $limits[1] ) . '"'; }
			if ( 'userpwd' === $key ) { echo ' list="rcs-connection-aliases"'; }
			echo ' value="' . esc_attr( $values[ $key ] ) . '">';
			if ( 'userpwd' === $key ) {
				echo '<datalist id="rcs-connection-aliases">';
				foreach ( array_keys( Remote_Content_Shortcode_Connections::all() ) as $alias ) { echo '<option value="' . esc_attr( $alias ) . '"></option>'; }
				echo '</datalist>';
			}
		}
		if ( $error ) { echo '<p class="rcs-field-error" id="' . esc_attr( 'rcs-error-' . $key ) . '">' . esc_html__( 'Use a public https:// or http:// URL without a username or password. Local and private addresses are blocked.', 'remote-content-shortcode' ) . '</p>'; }
		echo '<p class="description" id="' . esc_attr( $help_id ) . '">' . esc_html( self::field_help( $key ) ) . '</p></td></tr>';
	}
	private static function connections( $input = array() ) {
		wp_enqueue_script( 'remote-content-shortcode-admin', plugins_url( 'admin.js', __FILE__ ), array(), (string) filemtime( __DIR__ . '/admin.js' ), true );
		$action = $input['connection_action'] ?? '';
		$connection = 'save' === $action ? $input : array();
		echo '<h2>' . esc_html__( 'Connections', 'remote-content-shortcode' ) . '</h2><p>' . esc_html__( 'Credentials apply only to the exact HTTPS origin, path and methods below. Saved secrets are encrypted with the WordPress salts. Back up those salts before changing them.', 'remote-content-shortcode' ) . '</p>';
		$all = Remote_Content_Shortcode_Connections::all();
		foreach ( $all as $alias => $row ) {
			$alias = (string) $alias;
			if ( $row['read_only'] ) { echo '<p><code>' . esc_html( $alias ) . '</code> ' . esc_html__( 'Configured in PHP, read only.', 'remote-content-shortcode' ) . '</p>'; }
			else { self::connection_form( $alias, $row, $alias === ( $connection['alias'] ?? null ) ? $connection : array() ); }
		}
		if ( count( array_filter( $all, static fn( $row ) => ! $row['read_only'] ) ) < Remote_Content_Shortcode_Connections::LIMIT ) { echo '<h3>' . esc_html__( 'Add connection', 'remote-content-shortcode' ) . '</h3>'; self::connection_form( '', array(), is_string( $connection['alias'] ?? null ) && isset( $all[ $connection['alias'] ] ) && ! $all[ $connection['alias'] ]['read_only'] ? array() : $connection ); }
		if ( self::show_pro_overview() ) { echo '<p class="rcs-pro-tip">' . esc_html__( 'Pro supports OAuth2 connections with PKCE and client credentials.', 'remote-content-shortcode' ) . '</p>'; }
		self::resource_origins( 'save_origins' === $action ? ( $input['resource_origins'] ?? null ) : null );
	}
	private static function connection_nonce() {
		echo '<input type="hidden" name="_wpnonce" value="' . esc_attr( wp_create_nonce( 'rcs_connections' ) ) . '">';
		wp_referer_field( true );
	}
	private static function resource_origins( $draft ) {
		$value = is_string( $draft ) ? $draft : implode( "\n", Remote_Content_Shortcode_Output::stored_origins() );
		/* translators: %d: Maximum number of trusted image origins. */
		$description = sprintf( __( 'Remote src and poster assets are stripped by default. You can trust up to %d public HTTPS origins per site. The srcset attribute stays disabled.', 'remote-content-shortcode' ), Remote_Content_Shortcode_Output::MAX_ORIGINS );
		echo '<h2>' . esc_html__( 'Trusted HTTPS image origins', 'remote-content-shortcode' ) . '</h2><form method="post" class="rcs-settings-form rcs-origins-form">';
		self::connection_nonce();
		echo '<p class="description" id="rcs-resource-origins-help">' . esc_html( $description ) . '</p><p><label for="rcs-resource-origins">' . esc_html__( 'HTTPS origins, one per line', 'remote-content-shortcode' ) . '</label></p><textarea class="large-text code" id="rcs-resource-origins" name="resource_origins" rows="5" aria-describedby="rcs-resource-origins-help">' . esc_textarea( $value ) . '</textarea><p><button type="submit" class="button button-primary" name="connection_action" value="save_origins">' . esc_html__( 'Save trusted image origins', 'remote-content-shortcode' ) . '</button></p></form>';
	}
	private static function connection_form( $alias, $row, $input = array() ) {
		$values = self::form_values( $input, array( 'alias' => $alias, 'origin' => $row['origin'] ?? '', 'path' => $row['path'] ?? '', 'header' => $row['header'] ?? 'X-API-Key', 'auth' => $row['auth'] ?? 'bearer' ) );
		$methods = $input ? array_values( array_intersect( array( 'GET', 'POST' ), is_array( $input['methods'] ?? null ) ? array_filter( $input['methods'], 'is_string' ) : array() ) ) : ( $row['methods'] ?? array( 'GET' ) );
		$suffix = '' === $alias ? '' : '-' . $alias;
		$has_secret = ! empty( $row['has_secret'] );
		echo '<form method="post" class="rcs-settings-form">'; self::connection_nonce(); echo '<table class="form-table">';
		$labels = array( 'alias' => __( 'Alias', 'remote-content-shortcode' ), 'origin' => __( 'HTTPS origin', 'remote-content-shortcode' ), 'path' => __( 'Exact endpoint path', 'remote-content-shortcode' ), 'header' => __( 'API key header', 'remote-content-shortcode' ) );
		foreach ( $labels as $key => $label ) {
			$id = 'rcs-connection-' . $key . $suffix;
			echo '<tr><th><label for="' . esc_attr( $id ) . '">' . esc_html( $label ) . '</label></th><td><input type="text" class="regular-text" id="' . esc_attr( $id ) . '" name="' . esc_attr( $key ) . '" value="' . esc_attr( $values[ $key ] ) . '"' . ( 'alias' === $key && '' !== $alias ? ' readonly' : '' ) . ' aria-describedby="' . esc_attr( $id . '-help' ) . '"><p class="description" id="' . esc_attr( $id . '-help' ) . '">' . esc_html( self::field_help( $key ) ) . '</p></td></tr>';
		}
		$auth_id = 'rcs-connection-auth' . $suffix;
		echo '<tr><th><label for="' . esc_attr( $auth_id ) . '">' . esc_html__( 'Authentication', 'remote-content-shortcode' ) . '</label></th><td><select name="auth" id="' . esc_attr( $auth_id ) . '" aria-describedby="' . esc_attr( $auth_id . '-help' ) . '">';
		foreach ( array( 'basic' => 'Basic', 'bearer' => 'Bearer', 'api_key' => 'API key' ) as $key => $label ) { echo '<option value="' . esc_attr( $key ) . '"' . selected( $values['auth'], $key, false ) . '>' . esc_html( $label ) . '</option>'; }
		echo '</select><p class="description" id="' . esc_attr( $auth_id . '-help' ) . '">' . esc_html( self::field_help( 'auth' ) ) . '</p></td></tr><tr><th>' . esc_html__( 'Allowed methods', 'remote-content-shortcode' ) . '</th><td>';
		foreach ( array( 'GET', 'POST' ) as $method ) { echo '<label><input type="checkbox" name="methods[]" value="' . esc_attr( $method ) . '"' . checked( in_array( $method, $methods, true ), true, false ) . '> ' . esc_html( $method ) . '</label> '; }
		$secret_id = 'rcs-connection-secret' . $suffix;
		echo '<p class="description">' . esc_html( self::field_help( 'methods' ) ) . '</p></td></tr><tr><th><label for="' . esc_attr( $secret_id ) . '">' . esc_html__( 'Secret', 'remote-content-shortcode' ) . '</label></th><td><input type="password" autocomplete="new-password" name="secret" value="" id="' . esc_attr( $secret_id ) . '" aria-describedby="' . esc_attr( $secret_id . '-help' ) . '"><p class="description" id="' . esc_attr( $secret_id . '-help' ) . '">' . esc_html__( 'Basic uses username:password. For Bearer or API key, enter the token only. Leave blank to keep an existing secret.', 'remote-content-shortcode' ) . '</p>';
		if ( '' !== $alias ) { echo '<p class="rcs-secret-status">' . esc_html( $has_secret ? __( 'Saved secret present', 'remote-content-shortcode' ) : __( 'No secret saved', 'remote-content-shortcode' ) ) . '</p>'; }
		if ( $has_secret ) { echo '<label><input type="checkbox" name="delete_secret" value="1"> ' . esc_html__( 'Remove saved secret', 'remote-content-shortcode' ) . '</label>'; }
		echo '</td></tr></table><p><button class="button button-primary" name="connection_action" value="save">' . esc_html__( 'Save connection', 'remote-content-shortcode' ) . '</button> ';
		if ( '' !== $alias ) { echo '<button class="button" name="connection_action" value="delete">' . esc_html__( 'Delete connection', 'remote-content-shortcode' ) . '</button>'; }
		echo '</p></form>';
	}
	private static function cache() {
		$index = get_option( Remote_Content_Shortcode_Cache::INDEX, array() ); $count = 0; $bytes = 0;
		foreach ( is_array( $index ) ? $index : array() as $id => $row ) { if ( is_string( $id ) && preg_match( '/^rcs_v2_[a-f0-9]{64}$/D', $id ) && is_array( $row ) && is_int( $row['expires'] ?? null ) && $row['expires'] > time() && is_int( $row['bytes'] ?? null ) && $row['bytes'] >= 0 ) { ++$count; $bytes += $row['bytes']; } }
		/* translators: 1: Number of active cache entries, 2: Stored response bytes. */
		echo '<h2>' . esc_html__( 'Cache', 'remote-content-shortcode' ) . '</h2><p>' . esc_html( sprintf( __( 'Active indexed entries: %1$d. Stored bytes: %2$d.', 'remote-content-shortcode' ), $count, $bytes ) ) . '</p><p>' . esc_html__( 'The visitor cache stores public GET responses for logged-out visitors. An external object cache may evict responses before the listed expiry.', 'remote-content-shortcode' ) . '</p><form class="rcs-settings-form" method="post">'; wp_nonce_field( 'rcs_tools' );
		echo '<p><label>' . esc_html__( 'Source URL, or blank for all plugin entries', 'remote-content-shortcode' ) . ' <input name="url" class="regular-text" type="text" value=""></label><span class="description">' . esc_html__( 'Clear one source to fetch it again on its next use, or leave blank to clear all visitor response-cache entries. Saved Pro source snapshots are separate.', 'remote-content-shortcode' ) . '</span></p><p><button class="button" name="rcs_action" value="clear">' . esc_html__( 'Clear cache', 'remote-content-shortcode' ) . '</button></p></form>';
	}
	private static function usage() {
		global $wpdb;
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Bounded read-only pagination does not mutate content.
		$page = is_scalar( $_GET['usage_page'] ?? null ) ? min( 10000, max( 1, absint( $_GET['usage_page'] ) ) ) : 1;
		$public_types = get_post_types( array( 'public' => true ), 'names' );
		$types = array_values( array_unique( array_merge( array_diff( $public_types, array( 'attachment' ) ), array( 'wp_block', 'wp_template', 'wp_template_part' ) ) ) );
		$placeholders = implode( ',', array_fill( 0, count( $types ), '%s' ) );
		// Fetch one extra row instead of counting all matches.
		$shortcode = '%' . $wpdb->esc_like( '[remote_content' ) . '%'; $block = '%' . $wpdb->esc_like( '<!-- wp:remote-content-shortcode/remote-content' ) . '%';
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber -- The interpolated list contains only %s placeholders matching the prepared array. Read IDs and titles without fetching remote content.
		$rows = $wpdb->get_results( $wpdb->prepare( "SELECT ID, post_title, post_type, post_status FROM {$wpdb->posts} WHERE post_type IN ($placeholders) AND post_status NOT IN ('auto-draft','trash','inherit') AND (post_content LIKE %s OR post_content LIKE %s) ORDER BY ID ASC LIMIT 21 OFFSET %d", array_merge( $types, array( $shortcode, $block, ( $page - 1 ) * 20 ) ) ) );
		$rows = is_array( $rows ) ? $rows : array();
		echo '<h2>' . esc_html__( 'Usage', 'remote-content-shortcode' ) . '</h2><p>' . esc_html__( 'Find remote content shortcodes and blocks in posts, pages, public custom post types, synced patterns, templates, and template parts. Open an item to review or edit its imports.', 'remote-content-shortcode' ) . '</p><div class="rcs-table-scroll"><table class="widefat striped rcs-usage-table"><thead><tr><th scope="col" class="rcs-usage-title">' . esc_html__( 'Title', 'remote-content-shortcode' ) . '</th><th scope="col" class="rcs-usage-type">' . esc_html__( 'Content type', 'remote-content-shortcode' ) . '</th><th scope="col" class="rcs-usage-status">' . esc_html__( 'Status', 'remote-content-shortcode' ) . '</th><th scope="col" class="rcs-usage-actions">' . esc_html__( 'Actions', 'remote-content-shortcode' ) . '</th></tr></thead><tbody>';
		foreach ( array_slice( $rows, 0, 20 ) as $row ) {
			$title = '' !== $row->post_title ? $row->post_title : __( '(Untitled)', 'remote-content-shortcode' );
			$type = get_post_type_object( $row->post_type );
			$status = get_post_status_object( $row->post_status );
			$type_label = $type ? $type->labels->singular_name : $row->post_type;
			$status_label = $status ? $status->label : $row->post_status;
			$edit = get_edit_post_link( $row->ID );
			$view = $status && $status->public && $type && is_post_type_viewable( $type ) ? get_permalink( $row->ID ) : false;
			echo '<tr data-rcs-usage="' . esc_attr( $row->ID ) . '"><th scope="row" class="rcs-usage-title">';
			if ( $edit ) { echo '<a href="' . esc_url( $edit ) . '">'; }
			echo '<strong>' . esc_html( $title ) . '</strong>';
			if ( $edit ) { echo '</a>'; }
			echo '<div class="rcs-usage-meta"><span class="rcs-usage-mobile">' . esc_html( $type_label ) . ' · ' . esc_html( $status_label ) . ' · </span>#' . esc_html( $row->ID ) . '</div></th><td class="rcs-usage-type">' . esc_html( $type_label ) . '</td><td class="rcs-usage-status">' . esc_html( $status_label ) . '</td><td class="rcs-usage-actions">';
			/* translators: %s: Title of an item using the plugin. */
			if ( $edit ) { echo '<a href="' . esc_url( $edit ) . '" aria-label="' . esc_attr( sprintf( __( 'Edit %s', 'remote-content-shortcode' ), $title ) ) . '">' . esc_html__( 'Edit', 'remote-content-shortcode' ) . '</a>'; }
			if ( $edit && $view ) { echo ' · '; }
			/* translators: %s: Title of an item using the plugin. */
			if ( $view ) { echo '<a href="' . esc_url( $view ) . '" aria-label="' . esc_attr( sprintf( __( 'View %s', 'remote-content-shortcode' ), $title ) ) . '">' . esc_html__( 'View', 'remote-content-shortcode' ) . '</a>'; }
			echo '</td></tr>';
		}
		if ( ! $rows ) { echo '<tr><td colspan="4"><p><strong>' . esc_html__( 'No matching content on this page.', 'remote-content-shortcode' ) . '</strong></p><p>' . esc_html__( 'Add a Remote content block or shortcode to find it here.', 'remote-content-shortcode' ) . '</p></td></tr>'; }
		echo '</tbody></table></div><div class="rcs-usage-footer"><span class="rcs-usage-meta">';
		if ( $rows ) {
			/* translators: 1: First row number, 2: Last row number, 3: Page number. */
			echo esc_html( sprintf( __( 'Showing %1$d–%2$d · Page %3$d', 'remote-content-shortcode' ), ( $page - 1 ) * 20 + 1, ( $page - 1 ) * 20 + min( 20, count( $rows ) ), $page ) );
		} else {
			/* translators: %d: Page number. */
			echo esc_html( sprintf( __( 'Page %d', 'remote-content-shortcode' ), $page ) );
		}
		echo '</span><nav class="rcs-form-actions" aria-label="' . esc_attr__( 'Usage pagination', 'remote-content-shortcode' ) . '">';
		foreach ( array( -1 => __( 'Previous', 'remote-content-shortcode' ), 1 => __( 'Next', 'remote-content-shortcode' ) ) as $step => $label ) {
			if ( ( -1 === $step && $page > 1 ) || ( 1 === $step && count( $rows ) > 20 && $page < 10000 ) ) { echo '<a class="button" href="' . esc_url( add_query_arg( array( 'page' => 'remote-content-shortcode', 'tab' => 'usage', 'usage_page' => $page + $step ), admin_url( 'tools.php' ) ) ) . '">' . esc_html( $label ) . '</a>'; }
			else { echo '<span class="button disabled" aria-disabled="true">' . esc_html( $label ) . '</span>'; }
		}
		echo '</nav></div>';
		if ( $rows && self::show_pro_overview() ) { echo '<p class="rcs-pro-tip">' . esc_html__( 'Saved Sources in Pro let you reuse one import configuration across multiple posts and blocks.', 'remote-content-shortcode' ) . '</p>'; }
	}
	private static function help() {
		echo '<h2>' . esc_html__( 'Help', 'remote-content-shortcode' ) . '</h2><p>' . esc_html__( 'Enter a public source URL, use a CSS selector to narrow the result, then test and copy the shortcode. For a public HTTPS endpoint that requires credentials, save a connection and use its name in the shortcode.', 'remote-content-shortcode' ) . '</p><p>' . esc_html__( 'Use public data in shortcode POST bodies because WordPress stores them in the post. Supply private request data through trusted PHP. Support summaries omit source URLs, query parameters, credential names, and imported content.', 'remote-content-shortcode' ) . '</p>';
		$summary = array(
			'Plugin: 2.0.0',
			'WordPress: ' . get_bloginfo( 'version' ),
			'PHP: ' . PHP_VERSION,
			'DOM: ' . ( extension_loaded( 'dom' ) ? 'yes' : 'no' ),
			'cURL: ' . ( extension_loaded( 'curl' ) ? 'yes' : 'no' ),
			'Authenticated encryption: ' . ( function_exists( 'sodium_crypto_secretbox' ) || ( function_exists( 'openssl_encrypt' ) && in_array( 'aes-256-gcm', openssl_get_cipher_methods(), true ) ) ? 'available' : 'unavailable' ),
			'External object cache: ' . ( wp_using_ext_object_cache() ? 'yes' : 'no' ),
			'DNS resolvers: ' . ( Remote_Content_Shortcode_DNS::has_resolvers() ? 'detected' : 'unavailable' ),
			'Trusted image origins: ' . count( Remote_Content_Shortcode_Output::stored_origins() ),
		);
		echo '<h3>' . esc_html__( 'Support summary', 'remote-content-shortcode' ) . '</h3><textarea readonly class="large-text code" rows="' . esc_attr( count( $summary ) ) . '" aria-label="' . esc_attr__( 'Support summary', 'remote-content-shortcode' ) . '">' . esc_textarea( implode( "\n", $summary ) ) . '</textarea>';
	}
}
