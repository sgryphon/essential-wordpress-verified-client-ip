Verified Client IP - wordpress plugin
=====================================

A WordPress plugin that correctly verifies Forwarded For and other headers, to determine the verified client IP address, accepting only steps through trusted proxies, stopping at the first non-trusted address.

Requirements
------------

- **Minimum PHP version:** 8.1
- **Minimum WordPress version:** 6.4
- **License:** GPLv2 or later (required for WordPress.org plugin directory)
- **Plugin slug:** `verified-client-ip`
- **Text domain:** `verified-client-ip` (for internationalization)

Behaviour
---------

- Handle different ways of forwarding, e.g. official Forwarded For headers, X-Forwarded-For, custom behaviours like HTTP_CF_CONNECTING_IP, HTTP_CLIENT_IP, or HTTP_X_REAL_IP.

- The general behaviour is as specified in RFC 7239

1. Start with the connected remote client address, as reported by the connection, i.e. REMOTE_ADDR.
2. If that is in the list of valid proxies (either by IP address range or specific address), then the next Forwarded For or similar header is verified (we trust the valid proxy).
3. The next address is then checked, and if it is a valid proxy, we follow the chain back another step, and so on.
4. Eventually we will reach a header that contains an address outside the valid proxy range. Because the previous one was a valid proxy, we trust this header, and report it as the verified client IP.
5. If no scheme matches REMOTE_ADDR (i.e. it is not a known trusted proxy), then no header processing occurs and REMOTE_ADDR is left unchanged. The plugin is effectively a no-op for that request.
6. If every address in the entire chain is a trusted proxy (e.g., all private IPs with no external client address in headers), then the last (outermost) forwarded address is used as the verified client IP, since it is the furthest address from the server.
7. To do this we replace REMOTE_ADDR with the verified client IP, storing the original value in another header.

One approach is to first build a list of all the potential IP addresses in reverse order (starting with REMOTE_ADDR), and then find the first non-trusted one.

### Forward Limit

The Forward Limit (default: 1) defines the maximum number of proxy hops the algorithm will traverse. A limit of 1 means "trust one proxy hop" — if REMOTE_ADDR is a trusted proxy, look back one step in the forwarded headers. A limit of 2 would allow traversing two trusted proxies, and so on. This acts as a safety cap preventing unbounded chain traversal, even if all addresses happen to be trusted. If the chain exceeds the Forward Limit, processing stops and the address at the limit boundary becomes the verified client IP.

### Multiple headers of the same type

When multiple headers of the same type exist (e.g. multiple `X-Forwarded-For` headers, or multiple `Forwarded` headers), they should be concatenated in order (per RFC 7230 §3.2.2 — multiple header fields with the same name are equivalent to a single header with values joined by commas). Within a single header, values are also comma-separated; the rightmost value is the most recently added. The full list should be processed right-to-left (most recent first).

### Scheme matching priority

When checking an address against schemes, the first matching enabled scheme (by configured priority order) is used. Only one scheme applies per hop. If REMOTE_ADDR matches multiple schemes, only the highest-priority match is used.

### Malformed header values

If a header value is not a valid IP address (e.g. `unknown`, `_hidden`, hostnames, garbage strings, or empty values), it should be treated as an untrusted, non-proxy address. Since the previous hop was a trusted proxy, this malformed value becomes the verified client IP (it is not skipped). This is the safest behaviour — skipping values could allow attackers to bypass the chain by injecting invalid entries.

### IPv4-mapped IPv6 addresses

IPv4-mapped IPv6 addresses (e.g. `::ffff:192.168.1.1`) must be normalised and matched equivalently to their IPv4 counterpart (`192.168.1.1`). This is essential for dual-stack environments where the web server may report REMOTE_ADDR in either form depending on the socket type.

### Port numbers in addresses

Port numbers should be stripped before matching against proxy lists. The RFC 7239 `Forwarded` header may include ports (e.g. `for="192.0.2.1:8080"`), and IPv6 addresses with ports use bracket notation (e.g. `for="[2001:db8::1]:8080"`). Proxy lists should contain addresses/ranges only, not port-specific entries.

Some of this may require special handling, e.g. if there is one known address, that is forwarded from a known cloudflare address, then we would be expecting the next valid value to be the special HTTP_CF_CONNECTING_IP address.

In general for a single header we know they order that header was received, but don't know how it relates to other headers.

So, if the connecting address and the first two proxies are known valid, then the third forwarded-for is valid (and any others ignored).

If the connection and one proxy are valid, but the second forwarded-for matches cloudflare, then we skip any further forward headers and instead take the first (in reverse order) CF address. If there are additional CF addresses or additional Forwarded for then they are discarded (i.e. fake injections).

Configuration
-------------

Settings should be configured in the standard WordPress Settings menu, as a sub-menu item 'Verified Client IP'

There should be a main switch for enable/disable (so can disable parsing, whilst still having it installed, e.g. for diagnostics). There should also be a maximum number of forwards (Forward Limit), defaulting to 1 (see Behaviour > Forward Limit section for semantics).

The configuration should consist of a number of schemes, that can be prioritised in order. Scheme priority should be managed via up/down arrow buttons in the UI, reordering the list. Each scheme should also be able to be turned on/off.

Each scheme should show a configuration panel that has a list of the valid Known Proxies addresses or address ranges, both IPv4 and IPv6, and a setting for the accepted header for that scheme. Address ranges should use CIDR notation (e.g. `10.0.0.0/8`, `172.16.0.0/12`, `192.168.0.0/16`, `fc00::/7`). Individual addresses can be specified without a prefix length.

Inputs should be validated, e.g. IP addresses, and have suitable character limits, e.g. for scheme name.

The behaviour should check the initial REMOTE_ADDR against each of the schemes. When it finds a match, the accepted header is used to determine the next address (searching backwards). 

This next address is then run through the algorithm again, e.g. it may no longer match the original scheme, but might match a different scheme, determining the different header for the next address.

### Custom Schemes

The system should allow adding new custom schemes, with the following fields:

- **Name**: A descriptive label for the scheme (e.g. "AWS ALB", "Internal Proxy")
- **Enabled**: On/off toggle
- **Proxy addresses/ranges**: A list of trusted IP addresses or CIDR ranges for this scheme
- **Header name**: The HTTP header to read the forwarded address from (e.g. `X-Forwarded-For`, `X-Real-IP`)
- **Token** (optional): For headers that contain structured data (like RFC 7239 `Forwarded`), the specific token to extract (e.g. `for`). If blank, the entire header value is treated as the address list.
- **Notes** (optional): Allow the user to add any comments about the scheme.

Custom schemes support the same token-based parsing as the RFC 7239 scheme, so users can define schemes for any structured header format.

### Storage

All settings should be stored using the WordPress Options API (`wp_options` table) with a prefixed option name (e.g. `vcip_settings`). This is the standard approach for WordPress plugins and works per-site in multisite installations.

### Default configurations

The default configurations should include:

1. RFC 7239 Forwarded header
   - This consists of one header 'Forwarded:' with elements of tokens for, proto, by, etc.
   - Subsequent proxies may append after a comma, or add a new header after
   - Slightly different from others as we aren't using an entire header, but just one token i.e. Header = Forwarded, Token = For.
   - Default addresses to IPv4 private ranges, localhost, and IPv6 ULA addresses (often used for internal networks). None of these would be routable from the external internet.
   - External proxy addresses should not be in the default config; the user would have to manually add them as explicit allowed values.
2. X-Forwarded-For headers
   - Uses separate headers for X-Forwarded-For and other elements
   - Can't necessarily rely on the matching of related X-Forwarded-By or other fields, so can probably only validate addresses, not other elements.
   - Default addresses to IPv4 private ranges, localhost, and IPv6 ULA addresses (often used for internal networks). None of these would be routable from the external internet.
3. CloudFlare
   - Default have cloudflare disabled
   - When an address matches a cloudflare proxy, then use the CF header (`HTTP_CF_CONNECTING_IP`).
   - This will have the known list of existing Cloudflare proxies (these are public addresses, which is why it is turned off by default)
   - The default Cloudflare IPv4 ranges are: `173.245.48.0/20`, `103.21.244.0/22`, `103.22.200.0/22`, `103.31.4.0/22`, `141.101.64.0/18`, `108.162.192.0/18`, `190.93.240.0/20`, `188.114.96.0/20`, `197.234.240.0/22`, `198.41.128.0/17`, `162.158.0.0/15`, `104.16.0.0/13`, `104.24.0.0/14`, `172.64.0.0/13`, `131.0.72.0/22`
   - The default Cloudflare IPv6 ranges are: `2400:cb00::/32`, `2606:4700::/32`, `2803:f800::/32`, `2405:b500::/32`, `2405:8100::/32`, `2a06:98c0::/29`, `2c0f:f248::/32`
   - **Cloudflare IP list management**: These ranges are shipped as defaults and can be manually edited. Cloudflare publishes their current IP ranges at `https://www.cloudflare.com/ips-v4/` and `https://www.cloudflare.com/ips-v6/`. The **Notes** entry for the Cloudflare scheme should have a note linking to these URLs so administrators can verify and update the list. Automatic updating is not in scope for the initial version to avoid external network dependencies and potential security concerns with fetching remote data.

If there are other major/well known public proxies (e.g. f5, maybe aws/Microsoft, etc, that have well known proxy address ranges), then they can also be added (defaulted to disabled)

Note that Microsoft .NET has some good configuration options in their Forward For handling documentation. https://learn.microsoft.com/en-us/aspnet/core/host-and-deploy/proxy-load-balancer?view=aspnetcore-10.0

The Microsoft configuration has configurable values for the headers (default to X-Forwarded-For, X-ForwardedProto, X-Forwarded-Host, X-Forwarded-Prefix). While these can be options for X-Forwarded-For, if we want something different we don't have to change them, but could create a new custom scheme (and disable X-Forwarded-For if we wanted).

The top level configuration could have checkbox options for the different fields that are processed, defaulting to "For" and "Proto", i.e. don't process "Host" or "By" as the default config.

#### Proto processing

When "Proto" processing is enabled, the protocol reported by the proxy (via `X-Forwarded-Proto` or the `proto` token in `Forwarded`) is used to set `$_SERVER['HTTPS']` to `'on'` (if proto is `https`) and `$_SERVER['REQUEST_SCHEME']` to the protocol value. The original values should be stored in `X-Original-HTTPS` and `X-Original-Request-Scheme`.

#### Host processing

When "Host" processing is enabled (off by default), the host reported by the proxy is used to set `$_SERVER['HTTP_HOST']` and `$_SERVER['SERVER_NAME']`. The original value should be stored in `X-Original-Host`. Since host processing is off by default, the AllowedHosts configuration (inspired by Microsoft's implementation) is not in scope for the initial version, but may be added later when host processing is more commonly used.

Also add configuration options for original values; when REMOTE_ADDR is replaced, store the original in X-Original-Remote-Addr.

Diagnostics
-----------

Diagnostics should be available even if schemes are deactivated. This can be on a separate tab of the settings.

There should be a number of requests input, default to 10 (max 100), and a Start Diagnostics button.

This will record the next 10 incoming requests (or whatever the configured number is). It doesn't expand or rotate or anything, just record the next 10 requests and then stop recording. The button will stay disabled, keeping the diagnostics there.

This limits any performance impact, as after 10 requests recording stops.

### What is recorded

Diagnostic recording captures **all** incoming HTTP requests to WordPress (including admin pages, AJAX calls, REST API requests, and cron). Static assets served directly by the web server (not through PHP) are naturally excluded. Each recorded request stores a timestamp, the request URI, all `$_SERVER` headers (even those not relevant, in case a new scheme is being implemented), and the algorithm's step-by-step calculation.

### Diagnostics when plugin is disabled

When the main enable/disable switch is off but diagnostics are recording, the plugin's early hook (see Architecture > Execution Timing) must still run to capture request data and calculate results. However, the calculated verified client IP is **not applied** — REMOTE_ADDR remains unchanged. This means there is a minimal performance cost during diagnostic recording even when the plugin is disabled.

### Diagnostic display

Each request in the log should be listed, with a details panel shown when selected. The details panel should show all of the headers for that request, as well as what the algorithm would determine as the verified client IP address. Even if not active, the diagnostics should still calculate (just not use).

When showing the calculated address, show each step, e.g. step 1 is the REMOTE_ADDR a.b.c.d, matches scheme 1-ForwardedFor, step 2 uses the ForwardedFor Header e.f.g.h, and matches to scheme 3-CloudFlare. Step 3 uses the CF header, which is unmatched, so becomes the verified client IP.

### Diagnostic storage

Diagnostic data should be stored using WordPress transients (`set_transient` / `get_transient`). Transients are appropriate because diagnostic data is temporary and non-critical. The transient should have an expiry of 24 hours as a safety measure, so stale diagnostic data is automatically cleaned up even if the user forgets to clear it. Race conditions from concurrent requests should be handled using WordPress's built-in database locking via `wp_cache` or a simple lock transient to prevent exceeding the configured request count.

### Privacy notice

The diagnostics settings tab should display a notice that diagnostic data contains IP addresses and HTTP headers, which may be considered personal data under GDPR and similar regulations. The notice should recommend clearing diagnostics promptly after use.

There should also be a button at the bottom to Clear Diagnostics, which will remove all the entries and re-enable the Start Diagnostics button.

Architecture
------------

The component should follow best practices for a WordPress plugin, including clear responsibilities between classes.

WordPress plugins are primarily written in PHP.

### Execution Timing

The IP resolution must happen as early as possible in the WordPress lifecycle. The plugin should hook into `muplugins_loaded` if installed as a must-use plugin, or `plugins_loaded` (priority 0, i.e. earliest) if installed as a regular plugin. This ensures REMOTE_ADDR is replaced before any other plugin or WordPress core code reads it. The settings/admin UI can use later hooks (`admin_init`, `admin_menu`) since those only need to run in admin context.

### Hooks and Filters

The plugin should expose the following WordPress hooks for extensibility:

- **Filter `vcip_resolved_ip`**: Applied to the final verified client IP before it is set. Allows other plugins to override or modify the result. Receives the resolved IP and the full step trace as arguments.
- **Filter `vcip_trusted_proxies`**: Applied to the merged list of trusted proxy addresses before matching. Allows other plugins to dynamically add proxy addresses.
- **Action `vcip_ip_resolved`**: Fired after REMOTE_ADDR has been replaced. Receives the new IP, original IP, and step trace. Useful for logging plugins.

### Multisite Support

The plugin should work in WordPress multisite environments. Configuration is per-site (not network-wide), stored via the standard Options API which is site-scoped. Network activation is supported but each site configures its own schemes independently.

### Permissions

Access to the settings page and diagnostics requires the `manage_options` capability (Administrator role). All settings forms must use WordPress nonces for CSRF protection, and all inputs must be sanitised.

### Uninstall Behaviour

The plugin should include an `uninstall.php` file that cleans up all stored data when the plugin is deleted (not just deactivated):

- Delete all plugin options from `wp_options` (e.g. `vcip_settings`, `vcip_schemes`)
- Delete all diagnostic transients
- In multisite, iterate through sites to clean up per-site options

Deactivation should only flush caches/permalinks, not remove data.

Packaging should be provided for submission to WordPress as a plugin, including documentation on how to run the packaging.

An appropriate code formatter should be used. I like highly opinionated formatters, like Prettier or CSharpier. PHP-CS-Fixer with a strict ruleset (e.g. `@PSR12` + additional rules) or Laravel Pint are good opinionated options for PHP.

If relevant there should also be static linting/checking, if it exists for the platform. PHPStan or Psalm should be used for static analysis.

Quality, e.g. formatting, tests, etc, should be checked as part of the automated build (also check them before committing code)

### Logging

The component should write appropriate logs to WordPress as necessary. This can include administration actions.

Logging at the request level should be very brief and at a low level of verbosity, possibly even able to turn it off for performance reasons.

Errors and other issues should be reported at a higher level.

Because it should be rare, any detected invalid values (e.g. malformed headers, or suspected fake headers) should be logged as warnings. This could also indicate a misconfigured scheme, IP address, etc. (Although inputs should be checked as part of the configuration)

Testing
-------

### Test Framework

Unit tests should use PHPUnit with **pure unit tests** (mocking `$_SERVER` and any WordPress functions) for the core algorithm. This avoids requiring a full WordPress test installation for CI and keeps tests fast and isolated. A thin integration test suite using `WP_Mock` or similar can test the WordPress hook integration separately.

These should run as part of an automated GitHub Actions pipeline.

### Algorithm Tests

Include both simple examples, e.g. no headers, straight REMOTE_ADDR, and work up to more complex examples. Include examples using multiple steps, e.g. client -> cloudflare -> proxy 1 -> proxy 2 -> server.

Include both IPv4 and IPv6 test examples, including proxies that convert between the two, e.g. a cloudflare (or other) proxy that converts 4 to 6 (or 6 to 4). Don't need to go too crazy, and keep realistic, e.g. never going to convert 6 to 4 and then 4 to 6 (in trusted proxies).

Include IPv4-mapped IPv6 address tests (e.g. `::ffff:10.0.0.1` matching the `10.0.0.0/8` range).

Handle both HTTP and HTTPS and proxies that convert (usually from HTTPS unwrapped to HTTP).

Include plenty of hacking / extra header examples, where someone might try to confuse the system by adding headers before hitting the valid proxies.

We don't need to worry much about the valid proxies making mistakes -- we assume they are working correctly, although we might want a test or two for graceful degrading if one of them has a bug/problem. e.g. a misconfigured valid proxy that sends the wrong headers shouldn't break things.

### Graceful degradation tests

For misconfigured proxies:
- A trusted proxy that sends the wrong header name: the algorithm should not find a forwarded address in the expected header, and should fall back to using REMOTE_ADDR unchanged (safe default).
- A trusted proxy that sends a malformed/non-IP value in the correct header: the malformed value becomes the verified client IP (treated as untrusted). This is logged but should not cause errors.
- An empty header from a trusted proxy: REMOTE_ADDR is left unchanged.

### Test organisation

Break tests up across multiple files by type:
- Algorithm / IP resolution tests (majority)
- Header parsing tests (RFC 7239 Forwarded, X-Forwarded-For, etc.)
- CIDR matching and IPv4/IPv6 normalisation tests
- Configuration / settings tests
- Diagnostics tests
- Security / spoofing tests

Examples
--------

Include a robust approach for local testing via examples. 

Have a container compose file that runs up wordpress along with several proxies (e.g. caddy, nginx, or something else). Podman is the preferred runtime for containers and should be used for all examples and documentation (although you can mention 'or Docker').

Use some sort of standard pattern for ports, e.g.

e.g. 
- Port 8100 is Wordpress
- Port 8101 is Proxy A, that forwards direct to Wordpress
- Port 8102 is Proxy B, that forwards to Proxy A
- Port 8103 is Proxy C, that forwards to Proxy B. (we probably don't need more than 3 in a chain)
- Port 8112 is a proxy that uses older X-Forward-For, and sends to Proxy A.
- Port 8122 is a proxy that uses Cloudflare-like headers, and sends to Proxy A.

Provide some clear instructions how to use.

May need to include a full functioning client as well, that can be remoted into, and run a browser for testing. (Because there are some limitations with Windows when connecting to containers from the host on IPv6). Should be okay if we remote into a Linux box and use a browser there. Use a lightweight Linux desktop container (e.g. Alpine + noVNC or a similar web-based VNC) with Firefox, accessible via a browser at a mapped port (e.g. `http://localhost:8180`). This avoids requiring a full desktop environment while still supporting IPv6 testing.

### IPv6 in Podman

The compose file should enable IPv6 networking explicitly (using `enable_ipv6: true` on the Podman network and assigning an IPv6 subnet). At least one proxy chain should use IPv6 addresses between hops to test IPv6 forwarding scenarios.

### Proxy header configuration

The example proxies should be configured as follows:
- Proxies on ports 8101-8103: Use RFC 7239 `Forwarded` headers
- Proxy on port 8112: Use `X-Forwarded-For` headers
- Proxy on port 8122: Use Cloudflare-style headers (`CF-Connecting-IP`), simulating a Cloudflare proxy

Provide instructions how to install a development version of the plugin, e.g. if I make local changes to it, so that I can test.

Documentation
-------------

Provide an appropriate README with a brief overview of the project, build status badges. This will also have a link where to install the plugin (once submitted to wordpress)

Link to separate documentation for Development (how to develop), Packaging (how to submit to Wordpress gallery), guidance on running the Examples, etc.

Also include user-documentation. An introductory description to use when packaging, and help instructions of how to configure the different options, use diagnostics.

REST API / WP-CLI
-----------------

Settings and diagnostics do not need to be exposed via REST API or WP-CLI for the initial version. The WordPress admin UI is the primary interface. This may be added in a future version if there is demand (e.g. for automated deployment configuration).

Background / Alternatives
-------------------------

I have some plugins used in Wordpress but they are fairly limited in what they can do, or have security issues.

e.g. I was using 'Real IP Detector', code at https://plugins.svn.wordpress.org/real-ip-detector/trunk/real-ip.php

The description says it handles cloudflare, central hosting, f5, etc, but it was very limited. All it did was set something like `$_SERVER['REMOTE_ADDR'] = $_SERVER['HTTP_CF_CONNECTING_IP'];`, with no checks (so easily spoofed) and a fixed priority order with no configuration.

(The component has now been closed.)

A partial implementation is also provided by 'Show Visitor IP', code at https://plugins.svn.wordpress.org/show-visitor-ip/trunk/show-visitor-ip.php

This replaces the short code [show_ip] with either HTTP_CLIENT_IP, HTTP_X_FORWARDED_FOR, or REMOTE_ADDR, again with no verification.
