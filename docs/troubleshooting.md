# Troubleshooting imports in 2.0.0

Open **Tools → Remote content → Builder** as an administrator. Enter the source URL and the same selection, removal, rewriting, fallback, and credential alias settings used in your post, then select **Test content**. The editor shows a preview and shortcode together, with sanitized HTML source available below the preview. Select **Copy shortcode** and paste it into a Shortcode block. Your settings remain filled after submission. Use **Generate shortcode** when you want the code without fetching the source.

The result shows the error code, HTTP status when available, matching element count, elapsed time, and a sanitized preview. Test requests fetch fresh content and bypass visitor caching. DNS, redirects, and transfer share the five-second test timeout. A post with a longer configured timeout can succeed when the diagnostic request times out.

## Common results

| Error code | What to check |
| --- | --- |
| `invalid_url` | Use a complete public HTTP or HTTPS URL. Remove embedded credentials. Private, loopback, link-local, and IPv6-only destinations are unsupported. Check the hostname's public IPv4 records and WordPress's safe HTTP port restrictions. |
| `selector_no_matches` | Test without a selector to confirm the response contains the expected HTML, then try a simple tag or class selector. Content inserted by the remote site's JavaScript is not present in the fetched HTML. |
| `invalid_selector` | Use a supported CSS selector. Replace jQuery-only expressions such as `:eq()` with CSS equivalents, checking which sibling elements are counted. |
| `html_too_complex` / `selector_too_complex` | Use a smaller source fragment or simpler selectors. CSS processing permits up to 2,000 elements and depth 32, with stricter work limits for descendant and sibling selectors. The limit applies to the document before removal. |
| `http_status_error` | Inspect the HTTP status. The endpoint must return a successful 2xx response. A 401/403 may need a configured credential alias; a 404 usually indicates a changed source path. |
| `http_request_failed` / `request_failed` | Check outbound connectivity, DNS, the remote TLS certificate, and the endpoint's content encoding. Some transport errors use a generic diagnostic code to protect internal details. |
| `dns_unavailable` | The plugin has no usable numeric resolver configuration. Check that PHP can read valid numeric nameserver addresses in `/etc/resolv.conf`. On Windows or hosts with an `open_basedir` restriction, ask the administrator to define `REMOTE_CONTENT_DNS_SERVERS` with up to three numeric resolver addresses. |
| `request_timeout` | Use a faster source or the final URL directly to avoid redirects. Confirm that the configured resolvers respond promptly. DNS, redirects, and transfer share the HTTP timeout. |
| `request_budget_exceeded` | The PHP request reached its eight-fetch limit or shared 30-second network deadline. Consolidate imports or spread add-on fetches across separate jobs. Caching eligible public GETs can reduce fetches. |
| `request_busy` | A matching fetch is already in progress or the site's four fetch slots are occupied. Retry after ongoing requests finish. Eligible cached GETs can use `stale_ttl`, and imports can show a configured fallback while the source is busy. |
| `render_budget_exceeded` | The PHP request reached its 32-render limit or shared deadline. Reduce or split up the shortcodes, blocks, and snapshot renders, including repeated imports in templates or patterns. Cached imports still count toward the render limit. |
| `redirect_not_allowed` / `invalid_redirect` | Use the endpoint's final URL directly. Public GET requests allow at most three redirects. Authenticated requests and POST requests cannot redirect. |
| `proxy_not_supported` | The endpoint requires a WordPress HTTP proxy. Imports require direct public connectivity. |
| `response_too_large` / `html_too_large` | Use an endpoint returning a smaller fragment. The received and decompressed body is subject to the 1 MiB ceiling before selection. Transformed output also has limits. |
| `unconfigured_credentials` | Ask an administrator to configure the alias in the Connections tab or trusted PHP. A shortcode cannot directly select an arbitrary option, constant, or metadata key. |
| `credential_origin_mismatch` / `credential_endpoint_mismatch` | Match the alias's HTTPS origin, normalized endpoint path, and allowed HTTP method. Query values can vary. |
| `invalid_credentials` | Check the configured storage type, key, and authentication format. Basic authentication expects `username:password`. Metadata storage needs a fixed post ID and a protected key that is not exposed through REST. |
| `secret_unavailable` | Check that the configured connection still has a saved secret and that authenticated encryption is available. If the WordPress salts changed or the saved secret was removed, re-enter it in **Connections**. |
| `post_render_not_allowed` | A POST shortcode requires a trusted PHP grant through `remote_content_shortcode_allow_post_render` for its exact URL and alias. Ask an administrator to grant the repeat-safe endpoint, or switch to GET if the service supports it. A credential's POST permission alone is insufficient. |
| `invalid_regex` / `regex_input_too_complex` / `regex_output_too_large` | Test without `find` and `replace`, then simplify the pattern or replacement. Regex input is limited to 256 KiB, with additional pattern, capture, replacement-count, and output limits. |
| `invalid_fallback` | Shorten the fallback to at most 4,096 bytes. If `decode_atts` is enabled, this limit applies after attribute decoding. |
| `cache_busy` | Wait briefly and retry clearing. Cache mutations skip work when another writer holds the lock; an abandoned lock expires after 60 seconds. |

For hostname lookups, the plugin skips malformed entries and zone-ID addresses in `/etc/resolv.conf` and tries the configured numeric resolvers within the HTTP deadline. Check that the WordPress server can reach the listed resolvers.

The transport accepts `Content-Encoding: identity` and stream-decompresses `gzip` and `deflate` under a strict 1 MiB decompressed ceiling. If the endpoint sends `br`, `zstd`, or another unsupported encoding, configure the source to serve identity, gzip, or deflate. If the decoded response is too large, request a smaller fragment from the source.

## The preview works but visitors see old content

Administrator previews bypass caching. Open the post while logged out to check visitor behavior. Clear the exact source URL under **Tools → Remote content**, including any query values added by `params` or a URL filter. Leave the URL empty to clear all entries belonging to this plugin. Other WordPress caches are unaffected.

Check page caches or a CDN separately, since they may retain the rendered page after the plugin cache is cleared. If `stale_ttl` is enabled, a failed refresh can intentionally keep the last successful import visible until its original stale deadline.

## A managed connection stopped working

Check the alias, exact HTTPS origin, endpoint path and allowed method under **Connections**. Basic credentials need `username:password`; Bearer tokens cannot contain whitespace. A blank secret field retains the previous value. Enter a replacement to change it, or use **Remove saved secret** to erase it.

If the WordPress salts changed, existing sealed credentials cannot be decrypted. Re-enter the secrets using the new salts. If saving reports `secret_storage_unavailable`, make authenticated encryption available through Sodium or OpenSSL AES-256-GCM. A failed encrypted write does not replace an existing saved secret with plaintext.

PHP-defined aliases appear as read only and override matching managed aliases. Update those aliases in trusted PHP. Raw secret-storage details are excluded from visitor output.

## Links or images disappear after import

Set `rewrite_urls="true"` to resolve relative links (`href`, `cite`) and media URLs (`src`, `poster`) against the final URL after redirects. Imported `id`, `class`, and `style` attributes are removed for safety. A link such as `#details` stays on the WordPress page and works only if a matching local anchor exists.

The output strips remote `src` and `poster` by default. Check the image or media URL after resolution and ask an administrator to add its exact public HTTPS origin under **Tools → Remote content → Connections** or via the `remote_content_shortcode_resource_origins` PHP filter. Up to 16 origins can be granted. Use a permitted `src` URL for images because `srcset` stays disabled. See [the resource policy](security.md#html-and-resource-limits).

## Finding imports and sharing environment details

The **Usage** tab scans public post types, including custom post types, as well as synced patterns and block templates. It lists matches 20 per page without fetching remote sources or displaying post content. Check widgets and PHP-generated shortcodes separately. The **Help** tab's support summary includes WordPress and PHP versions and local capabilities without source URLs or secrets. Include it with a minimal example when reporting a problem.

All of these tabs are part of Free and work without Pro or a license service.

## The import is empty without an error

A successful endpoint can return an empty response, or sanitization can remove all its markup. Confirm the endpoint returns useful HTML without requiring JavaScript. Check any query forwarding (`params`), regular expression replacement (`find`, `replace`), or plain-text conversion (`strip_tags`) in the shortcode or Remote content block. The block also supports a connection alias and `htmlentities` to show escaped source. Configure `fallback` for empty output. A response containing `0` is valid and does not trigger fallback.

## Collecting a useful bug report

Include the plugin, WordPress, and PHP versions; the diagnostic error code and status; and a minimal shortcode or block configuration. Use a public sample endpoint or a small reproducible fixture. Remove tokens, passwords, and sensitive query values before sharing settings.

Developers can inspect the `remote_content_shortcode_error` action server-side. Its arguments can contain sensitive transport details and URLs, so diagnostic listeners must not print them to visitors. Report suspected vulnerabilities through the [private reporting instructions](../SECURITY.md).
