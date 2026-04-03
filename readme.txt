=== Essential Verified Client IP ===
Contributors: sgryphon
Tags: ip address, client ip, user ip, visitor ip, proxy
Plugin URI: https://github.com/sgryphon/essential-wordpress-verified-client-ip
Requires at least: 6.4
Tested up to: 6.9
Stable tag: 1.0.1
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Determines the true client IP by verifying Forwarded and similar headers, traversing only trusted proxy hops.

== Description ==

Essential Verified Client IP determines the client IP by walking the forwarding chain, only trusting addresses
that match your configured proxy networks (by CIDR range). It stops at the first untrusted hop, which is the 
true client IP. The resolved address replaces `REMOTE_ADDR` early in the WordPress lifecycle, allowing other Plugins
to use it.

The component is secure by default and only trusted proxies are traversed; spoofed headers are ignored. Both
IPv4 and IPv6 are fully supported, including protocol translation.

Multiple header formats are supported including standard RFC 7239 `Forwarded`, common `X-Forwarded-For`, 
Cloudflare `CF-Connecting-IP`, or any custom header.

The component includes a diagnostics panel that can be enabled to recording incoming requests with
full header dumps and algorithm step traces for debugging.

For more detail see the Github project site
[Essential WordPress Verified Client IP](https://github.com/sgryphon/essential-wordpress-verified-client-ip)

= Compatibility Note =

If your server uses **Apache `mod_remoteip`** or **nginx `set_real_ip_from`**, those modules will 
pre-resolve `REMOTE_ADDR` from forwarding headers before PHP runs. Disable the web server module and 
let this plugin handle IP resolution instead, or let it pre-resolve and use this plugin for any
additional proxies not covered by the engine.

== Installation ==

1. Download the plugin zip file.
2. In WordPress Admin, go to **Plugins → Add New → Upload Plugin**.
3. Upload the zip and click **Install Now**.
4. Click **Activate Plugin**.

Or manually upload the `essential-verified-client-ip` folder to
`wp-content/plugins/` and activate via the Plugins screen.

Navigate to **Settings → Essential Verified Client IP** in the WordPress admin.

Configure with your proxy setup, or accept the defaults if suitable.

== Frequently Asked Questions ==

= The plugin has no effect. What should I check? =

Check that `REMOTE_ADDR` matches a trusted proxy in an enabled scheme. Use the **Diagnostics** tab 
to record a request and inspect the step trace.

= The plugin is a no-op even though my proxies are configured. =

Apache `mod_remoteip` or nginx `set_real_ip_from` may be pre-resolving the IP before PHP starts. 
In the Diagnostics tab, if the **Remote Address** column already shows the visitor's IP rather 
than the upstream proxy's IP, a web-server-level module is the cause.

Disable the relevant module or accept that addresses are already partially resolved.

= I'm resolving the wrong IP. =

Check that your **Forward Limit** matches the exact number of proxies in your chain. Use the 
step trace in **Diagnostics** to see each hop the algorithm evaluated.

= Cloudflare is not working. =

Enable the Cloudflare scheme and add [Cloudflare's published IP ranges](https://www.cloudflare.com/ips/) 
to the trusted proxies list.

= Can I extend the plugin with my own code? =

Yes. Three hooks are available:

* **Filter `vcip_resolved_ip`** `(string $ip, array $steps): string` — modify the resolved IP before it replaces `REMOTE_ADDR`.
* **Filter `vcip_trusted_proxies`** `(array $schemes): array` — dynamically add or modify proxy schemes before matching.
* **Action `vcip_ip_resolved`** `(string $newIp, string $originalIp, array $steps)` — fired after `REMOTE_ADDR` has been replaced.

= What happens when the plugin is deactivated? =

Deactivation only flushes object caches — no settings or data are removed. Uninstalling the plugin 
removes all `vcip_*` options and diagnostic transients.

= Is diagnostic data stored permanently? =

No. Diagnostic records are stored as WordPress transients with a 24-hour expiry and are automatically removed when you 
uninstall the plugin. Because the data includes IP addresses and HTTP headers, only record what you need for debugging 
and clear the data when you are done.

== Screenshots ==

1. Main settings page — enable/disable the plugin, set the forward limit, and configure proto/host processing.
2. Scheme detail — configure header name, trusted proxy CIDR ranges, and optional token for a scheme.
3. Diagnostics page — list of recorded requests with resolved IP and whether `REMOTE_ADDR` was changed.
4. Diagnostics with IPv6 — shows IPv6 addresses and protocol translation.
5. Diagnostics detail — full step trace showing each hop evaluated by the algorithm.
6. Comments page — WordPress comments showing the verified client IP for each commenter.

== Changelog ==

= 1.0.1 =
* Minor fixes for initial release.

= 1.0.0 =
* RFC 7239 `Forwarded`, `X-Forwarded-For`, and Cloudflare `CF-Connecting-IP` schemes.
* Configurable forward limit and trusted proxy CIDR ranges.
* Proto and Host processing.
* Diagnostics with request recording and step traces.
* Must-use plugin support.
* WordPress hooks: `vcip_resolved_ip`, `vcip_trusted_proxies`, `vcip_ip_resolved`.

== Upgrade Notice ==

= 1.0.1 =
Initial release.
