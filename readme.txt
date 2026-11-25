=== Remote Content Shortcode ===
Contributors: doublesharp
Tags: shortcode, content, import, scraper, remote
Requires at least: 6.6
Tested up to: 7.2
Requires PHP: 8.2
Stable tag: 2.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Embed live docs, changelogs, tables, and more with a WordPress block or shortcode. Select the HTML you need and cache imports.

== Description ==

Embed live documentation in a help center, bring a product changelog or announcements into a client's site, or publish a table or HTML snippet from an API or another website. Update the source in one place and let WordPress pick up changes as its cache refreshes.

* **Choose what to show.** Use CSS selectors to include the section you need and remove unwanted elements. Replace text, strip tags, or display source as text.
* **Build in the block editor.** Insert the Remote content Gutenberg block and preview the import in place, or use the shortcode in a post or page.
* **Keep pages useful during outages.** Cache public GET responses and optionally show the last successful import or a fallback message if the source is unavailable.
* **Connect to an API.** Use GET or opt in to POST for repeat-safe endpoints. Administrators can set up scoped Basic, Bearer, or API-key connections without putting secrets in posts.

The plugin checks public destinations before fetching and sanitizes the HTML before publishing it. Free is self-contained and runs without telemetry, a vendor account, or a license check. The optional Pro add-on uses its license for updates, so installed features keep working when update access ends.

= Usage =

`[remote_content url="https://example.com/article" selector="main" remove="script, .advertisement" cache="true"]`

Attributes:

* `url`: Absolute public HTTP or HTTPS URL. Local files, private networks, loopback addresses, credentials embedded in URLs, and unsafe ports are rejected. Standard WordPress safe HTTP ports apply.
* `method`: GET or POST, default GET. Enclosed shortcode content is the POST body. POST responses are never cached and POST redirects are refused.
* `timeout`: Total request budget for DNS, redirects, and transfer, from 1 to 30 seconds, default 10.
* `params`: Comma-separated query-string names, optionally with defaults, such as `id,language=en`. Only named GET values are forwarded. Values are URL-encoded; arrays are rejected. Missing values without defaults are omitted. Defaults may contain equals signs.
* `userpwd`: A configured credential alias, such as `docs_api`. See Authentication below. Raw passwords and arbitrary option, metadata, or constant names are no longer accepted in shortcodes.
* `selector`: Standard CSS selector for the elements to return. Defaults to the entire response. CSS processing accepts at most 2,000 elements, document depth 32, and selectors up to 256 bytes. A combined work budget accounts for generated XPath traversal, with stricter limits for descendant and sibling selectors than simple tag or class selections.
* `remove`: CSS selector for elements to remove before selection. Defaults to no removal. The same document and selector work budgets apply before removal.
* `rewrite_urls`: Set to true to resolve relative links (`href`, `cite`) and media URLs (`src`, `poster`) against the final URL after redirects. Default false. Media still requires a trusted origin, and `srcset` stays disabled. Fragment links stay local and can point to local page anchors because imported IDs are removed.
* `fallback`: HTML to display if the import fails or produces empty output, limited to 4,096 bytes. It is sanitized and is not interpreted as a shortcode. A response containing the string "0" remains valid content.
* `find`: PHP regular expression, such as `~old-domain[.]com~`. Invalid patterns return empty output. Patterns are limited to 1,024 bytes, 32 opening parentheses, and 1,000 replacements per render. The input to this transformation must not exceed 256 KiB.
* `replace`: Replacement text, including regular expression backreferences. Defaults to an empty string and is limited to 4,096 bytes. Use `%QUOT%` in find or replace to represent a double quote.
* `htmlentities`: Set to true to encode remote source for display. Default false.
* `strip_tags`: Set to true to remove HTML, script, and style elements and return text. Default false.
* `decode_atts`: Set to true for entity-encoded shortcode attributes from SyntaxHighlighter. Default false.
* `cache`: Set to false to disable caching. Default true for unauthenticated GET requests by logged-out visitors, including public REST-rendered content. Logged-in renders and editor previews bypass caching so they cannot evict public content. Each final URL, request configuration, and site has a separate cache key. Transformations and output filters run on every render. Failed requests are cached for up to 60 seconds, or the configured cache_ttl if shorter, so a broken remote is not retried on every render.
* `cache_ttl`: Fresh cache lifetime from 1 to 86,400 seconds, default 3,600. Uses a persistent WordPress object cache when installed, otherwise expiring database transients. Each site stores at most 32 entries and 8 MiB of serialized cached payloads; older entries can be evicted before their lifetime ends.
* `stale_ttl`: Additional time to retain the last successful response for use if a refresh fails, from 0 to 86,400 seconds. Default 0 disables stale fallback. Only cached, unauthenticated GET requests qualify. Failures never extend the original stale deadline. All transformations and final sanitization still run on stale content.

= Block editor and diagnostics =

Insert the Remote content block and set its source URL, selectors, removal rule, fallback, URL rewriting, and cache options. The block can forward named query parameters (`params`), apply regular expression replacements (`find`, `replace`), display escaped source (`htmlentities`), and return plain text (`strip_tags`). Select a configured connection alias in the block settings to authenticate a request. The block imports with GET. Use the shortcode for POST requests. Editor previews can fetch the source and use the same security restrictions as frontend rendering.

Administrators can open Tools > Remote content > Builder and select Test content to get a sanitized preview and a shortcode together, then use Copy shortcode. Generate shortcode creates the code without fetching the source. Entered settings remain filled after submission. Diagnostics show HTTP status, cache status, selector match count, elapsed time, and sanitized HTML source. Administrator tests bypass caching and show a disabled cache status. Raw request error messages, credential values, and destination URLs are excluded from diagnostic output. The five-second test timeout includes DNS resolution. The generated shortcode contains the public settings entered for copying. Keep secrets out of URLs, attributes, and POST bodies.

The Connections tab manages encrypted credential aliases and trusted image and media origins. The Cache tab displays active indexed entries and byte totals and can clear an exact source URL, including its query string, or all of this plugin's cache entries. Other WordPress caches are unaffected. Use the actual URL after any shortcode URL filter and forwarded parameters.

The Usage tab scans public post types, including custom post types, as well as synced patterns and block templates. It lists items containing the shortcode or block, 20 at a time, without fetching sources or displaying post content. Help includes a copyable environment summary without source URLs, credential names, or content. Every tab is administrator-only.

= Free and Pro =

The shortcode, block, selection/removal, replacements, persistent public GET caching, stale fallback, relative URL rewriting, Basic/Bearer/API-key authentication and all five Tools tabs remain in Free. Free works on its own and contains no licensing client or licensing-service dependency.

[Remote Content Shortcode Pro](https://www.doublesharp.com/remote-content-shortcode-pro/) installs separately and adds reusable sources, scheduled refreshes, OAuth, and source management. It uses Free's protected transport and sanitized HTML pipeline. Installed Pro features continue to work without an active update license.

= POST example =

`[remote_content url="https://example.com/public-search" method="POST"]search=example[/remote_content]`

Configure authenticated POST access explicitly if an endpoint requires credentials. Use read-only service credentials wherever possible.

= Authentication =

Administrators can save up to 32 connections per site in Tools > Remote content > Connections. Choose Basic, Bearer or API-key authentication, an HTTPS origin, an exact endpoint path and GET and/or POST. Secrets are never shown in the form. Leave a secret blank to retain it, replace it by entering a new value, or select Remove saved secret. Basic authentication expects username:password. Save and delete require administrator permission and a valid form nonce.

Saved secrets use authenticated encryption, with Sodium preferred and AES-256-GCM as a fallback. The encryption key is derived from the WordPress salts. Rotating those salts makes existing sealed credentials unreadable; back up the salts with the database and re-enter connection secrets after rotation. Storage fails when authenticated encryption is unavailable; secrets are never saved as plaintext.

Administrators can also configure aliases in trusted server-side code, for example an mu-plugin. Code-managed aliases take precedence and appear as read only; their option/constant names and endpoint scope are not shown in the Connections list. Each alias grants shortcode authors access to one HTTPS endpoint. Its fetched content will be visible to anyone who can view the post, including logged-out visitors. Authentication does not make imported content private.

Example configuration, with a `DOCS_API_CREDENTIALS` constant set separately to a `username:password` string:

    add_filter( 'remote_content_shortcode_credentials', function ( $sources ) {
        $sources['docs_api'] = array(
            'origin' => 'https://docs.example.com',
            'path'   => '/public-summary',
            'type'   => 'constant',
            'key'    => 'DOCS_API_CREDENTIALS',
        );
        return $sources;
    } );

Then use:

`[remote_content url="https://docs.example.com/public-summary" userpwd="docs_api"]`

The origin must contain only an HTTPS scheme, host, and optional port. The path must exactly match the endpoint's normalized URL path. Query parameters may vary. Redirects and response caching are disabled for authenticated requests. Aliases allow GET by default. To grant POST as well, add `'methods' => array( 'GET', 'POST' )` to that alias.

Supported storage types are `constant`, `option`, and `post_meta`. The configured key identifies the constant, option, or protected metadata key. There is no automatic fallback between storage types. Never put a password in a shortcode.

For post_meta storage, the alias must specify a fixed integer post_id and a protected metadata key, such as `'post_id' => 123, 'key' => '_remote_api_secret'`. Metadata exposed through the WordPress REST API is refused. The current loop, widget, or synced pattern never chooses the credential's post. Existing aliases with ordinary custom-field keys or no explicit post_id must migrate; constant or option storage is preferred.

Basic authentication is the default. To send a stored token as a Bearer credential, add `'auth' => 'bearer'` to the alias. To send an API key, add `'auth' => 'api_key'` and optionally `'header' => 'X-Service-Key'`; the default header is X-API-Key. API-key header names must be X-* or Api-Key, and routing, proxy, cookie, and method-override headers are refused. The same HTTPS origin, endpoint path, and method restrictions apply to every authentication type. Authenticated responses are never cached or served stale.

= External requests and limits =

The shortcode is registered in frontend, administration, and REST contexts. Requests happen when it is rendered, including editor previews and REST-rendered post content. The plugin contacts the configured URL and sends explicitly forwarded query values, a POST body, or scoped credentials when configured. POST rendering requires a trusted PHP grant for a repeat-safe endpoint. The plugin sends no telemetry and requires no developer service.

The site owner is responsible for authorization to reproduce remote content and for checking the destination service's terms and privacy policy. Visitors' cookies and incoming Authorization headers are not forwarded.

Only public destinations are supported. Resolved addresses are checked and pinned for each request, with TLS certificate verification enabled. Public GET redirects are followed up to three times and every destination is validated. WordPress HTTP proxy routing is refused; environment proxy settings are disabled for these requests. HTTPS-only outbound proxies and private intranet imports are not supported.

Source hostnames need a public IPv4 record. IPv6-only DNS names and literal IPv6 URLs are unsupported. All returned IPv4 and IPv6 addresses are checked before connecting. URL or HTTP-method changes by late Requests hooks are rejected before the transport runs. The selected peer address is checked after connecting.

DNS uses numeric resolvers from `/etc/resolv.conf`, skips malformed entries and addresses with zone IDs, and tries the configured resolvers within the HTTP deadline. If no usable numeric resolvers are available, hostname lookups report `dns_unavailable`. On Windows or when PHP's `open_basedir` blocks the file, define `REMOTE_CONTENT_DNS_SERVERS` with up to three numeric resolver addresses.

= Sanitization and processing limits =

WordPress's post HTML rules remove scripts, event handlers, and other disallowed markup, including markup added by replacements or output filters. Imported `id`, `class`, and `style` attributes are removed. The output strips remote `src` and `poster` resources by default. Administrators can grant up to 16 exact public HTTPS image and media origins in **Tools → Remote content → Connections** or with the `remote_content_shortcode_resource_origins` PHP filter. A visitor's browser can contact those origins when the page displays an allowed resource. The `srcset` attribute is always removed.

The transport accepts uncompressed responses, including `Content-Encoding: identity`, and stream-decompresses `gzip` and `deflate` responses under a strict 1 MiB decompressed size limit. Unsupported encodings such as `br` and `zstd` and bodies exceeding the limit after decompression are rejected. CSS selector output and replacement output are limited to 1 MiB. Unsupported selectors, failed requests, invalid input, and HTTP errors yield the configured fallback or empty output.

CSS work limits are conservative. With no removal selector, `main p` permits at most 894 total elements at document depth 20; `h2 + p` permits at most 200 elements. Counts include HTML/body wrappers added by the parser. General and adjacent sibling selectors, and some pseudo-classes, are intended for small fragments. Selection and removal share the work budget, so combining them can lower these limits. Simple tag and class selections can use the full 2,000-element document limit, subject to depth and selector limits.

URL rewriting ignores imported base elements and drops URLs containing literal whitespace. Form actions, CSS backgrounds, the background attribute, and longdesc are not rewritten.

The plugin requires WordPress 6.6 or later and PHP 8.2 or later with DOM and cURL 7.49 or later. CSS selection uses the bundled, namespace-isolated Symfony CssSelector component. It supports most standard CSS3 selectors. jQuery-only selectors such as `:eq()` are unsupported.

The installed plugin includes `docs/security.md` with the full security and compatibility reference. Uninstalling deletes its cached data, metadata and administrator-managed connection records on each site, including multisite. It retains PHP-configured constants, external credential options and protected metadata, and unrelated WordPress caches. Deactivation preserves settings.

= Developer hooks =

See the [integration reference](https://github.com/0xdoublesharp/remote-content-shortcode/blob/main/docs/integrations.md) for PHP fetch and render APIs, credential providers, administrator tabs, and transformation hooks. The reference is also included in the installed plugin at `docs/integrations.md`.

Final output always passes through WordPress HTML sanitization. The SyntaxHighlighter integration processes only `remote_content` shortcodes. TLS verification and public-destination checks are mandatory. The former SSL verification filters and the `REMOTE_CONTENT_ALLOW_ALL_URLS` and `REMOTE_CONTENT_ALLOW_PRIVATE_IPS` constants are unsupported.

== Installation ==

1. Upload the remote-content-shortcode directory to /wp-content/plugins/.
2. Verify PHP 8.2 or later with DOM and cURL 7.49 or later.
3. Activate Remote Content Shortcode in WordPress.
4. Add a shortcode with a full URL, for example `[remote_content url="https://example.com/"]`.

== Frequently Asked Questions ==

= Why is my shortcode empty? =

Open Tools > Remote content as an administrator and test the source URL and selectors. Check the HTTP status, error code, match count, and sanitized preview. Administrator diagnostics fetch fresh content and bypass visitor caching. The installed docs/troubleshooting.md explains common error codes and remedies. For authenticated requests, check the alias's origin, path, method, and storage configuration.

= How do I upgrade from 1.x? =

Follow docs/upgrading.md in the installed plugin or the GitHub repository. Check PHP/WordPress requirements, replace unsupported selectors, configure explicit credential aliases, and test imports on staging and as a logged-out visitor. Existing posts and credential storage are preserved; aliases are not created automatically.

= How do I report a security issue? =

Use the private vulnerability reporting form at https://github.com/0xdoublesharp/remote-content-shortcode/security/advisories/new. Include a minimal proof of concept using test data, affected versions, and required permissions. Do not post live credentials or exploit details in public issues. See SECURITY.md in the plugin for details.

= Can I import private intranet content or execute remote scripts? =

No. Version 2.0.0 blocks private destinations and renders only allowed post HTML. Use entity encoding to display remote source as text.

= Do I need Composer on my WordPress server? =

No. The CSS selector dependency is bundled in the release. Its source and MIT license are included under inc/vendor/css-selector. See third-party.txt for the version and source information.

== Screenshots ==

1. Test an import and copy its shortcode from one workspace, with source settings beside the sanitized preview.
2. Configure an import on a phone with compact section navigation, source and selector fields, and Test and Generate actions.

== Upgrade Notice ==

= 2.0.0 =
Security update. Requires WordPress 6.6 and PHP 8.2 with DOM and cURL. Check imports that use private URLs, remote scripts, jQuery-only selectors, or inline credentials. See docs/upgrading.md.

== Changelog ==

= 2.0.0 =
* Add a Free shortcode builder, encrypted connection manager, cache summary, usage lookup and support summary under Tools > Remote content.
* Expose protected fetch and snapshot-rendering APIs and administrator tab hooks for separate add-ons, with no Free licensing dependency.
* Fix CVE-2023-45652, local file inclusion; CVE-2024-2089, stored cross-site scripting; and CVE-2024-2090, server-side request forgery.
* Decode entity-encoded fallback attributes consistently, simplify administrator test controls, and add troubleshooting and upgrade guides.
* Sanitize all rendered remote HTML, including replacement and filter output.
* Replace direct cURL fetches with WordPress safe HTTP requests and pin validated public addresses to prevent DNS rebinding.
* Validate every redirect, verify TLS, and limit request time, response size, output expansion, and replacement count.
* Accept identity responses and stream-decompress gzip and deflate within the 1 MiB response ceiling. Reject unsupported encodings, local files, private networks, and proxy routing.
* Replace obsolete phpQuery with a scoped Symfony CssSelector dependency and native DOM.
* Fix cache context collisions and query parameter encoding. Do not cache POST or authenticated responses.
* Add bounded persistent caching without an external cache, targeted invalidation, and opt-in stale content on failed refreshes.
* Add relative URL rewriting against the final redirect URL and sanitized fallback content.
* Strip imported styling and IDs and let administrators grant up to 16 exact HTTPS image and media origins. Keep srcset disabled.
* Add administrator-only diagnostics and a dynamic Gutenberg block with a protected server-rendered preview, connection selection, query forwarding, replacements, and text transformations.
* Scan public post types, synced patterns, and block templates in Usage.
* Support scoped Bearer tokens and API keys alongside Basic authentication.
* Repair credential retrieval through explicit aliases restricted by HTTPS origin, exact path, and HTTP method.
* Handle invalid selectors, patterns, input, and HTTP errors without exposing internal diagnostics.
* Preserve zero-length and zero-valued responses and register the shortcode for WordPress previews.
* Limit SyntaxHighlighter processing to this plugin's shortcode.
* Bound document and selector work before XPath evaluation, abort oversized response transfers through cURL's progress callback, and cache public GET failures briefly.
* Reject request-hook URL or method changes and enforce the pinned destination and TLS options after ordinary cURL hooks.
* Share the HTTP deadline across numeric DNS resolvers, redirects, and transfer. Report unavailable resolver configuration in diagnostics.
* Document IPv4 DNS requirements and frontend, administration, and REST rendering behavior.

= 1.5 =
* PHP7 compatibility.
  * use `explode()` instead of `split()` for `phpquery`.
* Follow redirects with `CURLOPT_FOLLOWLOCATION`

= 1.4.2 =
* Add filters for third party modification of values.
  * `remote_content_shortcode_url`, arguments: `$url`
    * Modify the URL before it is fetched
  * `remote_content_shortcode_postfields`, arguments: `$tag_content`, `$url`
    * Modify the tag content before it is POSTed
  * `remote_content_shortcode_ssl_verifyhost`, arguments: `false`, `$url`
    * Verify host SSL certificates
  * `remote_content_shortcode_ssl_verifypeer`, arguments: `false`, `$url`
    * Verify peer SSL certificates
  * `remote_content_shortcode_phpQuery`, arguments: `$response`, `$url`, `$selector`, `$remove`
    * Modify response content before it is sent to `phpQuery`
  * `remote_content_shortcode_return`, arguments: `$response`, `$url`
    * Modify response before it is returned

= 1.4 =
* Convert `&amp;` to `&` in URLs to account for Visual/Text editor encoding.

= 1.3.3 =
* Fix for multiple `params` attributes.

= 1.3.2 =
* Fix for `params` attribute.

= 1.3.1 =
* Add support for passing through querystring parameters.

= 1.3 =
* Use double quotes in find/replace by using `%QUOT%` in its place, for example `find="~ (src|href)=(%QUOT%|')?(/)?~" replace=" \\1=\\2http://www.example.com/"`
* Bug fix for regex/non-empty replace.

= 1.2 =
* Support case insensitive `true/false` and `GET/POST` values.
* Bug fix for remote content caching.

= 1.1 =
* CSS selectors for filtering and removal.
* Regular expression replacement support.
* Support for `strip_tags`.
* Added attribute to allow caching to be disabled.

= 1.0 =
Initial version.
