# User Guide

## Overview

Gryphon Verified Client IP determines the true IP address of visitors to your
WordPress site. When your site sits behind proxies, load balancers, or CDNs,
the standard `REMOTE_ADDR` server variable contains the proxy's IP rather
than the visitor's. This plugin walks the forwarding header chain and
verifies each hop against your configured trusted proxy networks, stopping
at the first untrusted address — the real client IP.

## Installation

1. Download the plugin zip file.
2. In WordPress Admin, go to **Plugins → Add New → Upload Plugin**.
3. Upload the zip and click **Install Now**.
4. Click **Activate Plugin**.

Or manually upload the `gryphon-verified-client-ip` folder to
`wp-content/plugins/` and activate via the Plugins screen.

## Configuration

Navigate to **Settings → Gryphon Verified Client IP** in the WordPress admin.

### General Settings

| Setting           | Default | Description                                                                                                                                                                        |
| ----------------- | ------- | ---------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| **Enable**        | On      | Master switch. When off, the plugin calculates but does not replace `REMOTE_ADDR`. Diagnostics still work.                                                                         |
| **Forward Limit** | 1       | Maximum number of proxy hops to traverse (1–20). Set this to the number of trusted proxies between your server and the internet.                                                   |
| **Process Proto** | On      | When enabled, updates `$_SERVER['HTTPS']` and `REQUEST_SCHEME` from the proxy's forwarded protocol. Originals are preserved in `X-Original-HTTPS` and `X-Original-Request-Scheme`. |
| **Process Host**  | Off     | When enabled, updates `HTTP_HOST` and `SERVER_NAME` from the proxy's forwarded host. Original is preserved in `X-Original-Host`.                                                   |

### Schemes

A scheme defines how the plugin reads forwarding information from a proxy.
Three default schemes are provided:

1. **RFC 7239 Forwarded** (enabled) — reads the standard `Forwarded` header's
   `for` token. Trusted proxies default to private/reserved IP ranges.

2. **X-Forwarded-For** (enabled) — reads the common `X-Forwarded-For` header.
   Trusted proxies default to private/reserved IP ranges.

3. **Cloudflare** (disabled) — reads Cloudflare's `CF-Connecting-IP` header.
   Trusted proxies should be set to
   [Cloudflare's published IP ranges](https://www.cloudflare.com/ips/).

#### Adding a Custom Scheme

Click **Add Scheme** at the bottom of the Schemes section. Fill in:

- **Name**: A descriptive name (e.g. "My Load Balancer").
- **Enabled**: Toggle the scheme on or off.
- **Header Name**: The HTTP header this proxy sets (e.g. `X-Real-IP`).
- **Token** (optional): For multi-value headers like `Forwarded`, the token
  to extract (e.g. `for`).
- **Trusted Proxies**: One IP address or CIDR range per line. Only requests
  arriving from these addresses will be trusted.
- **Notes** (optional): Any notes for your reference.

#### Deleting / Reordering Schemes

- Click **Delete** to remove a scheme.
- Use **Move Up** / **Move Down** to change priority order. The first matching
  enabled scheme wins when multiple schemes could match a proxy address.

### Understanding the Algorithm

1. Start with `REMOTE_ADDR` (the address your server sees).
2. Check if it matches any trusted proxy in an enabled scheme.
3. If it does, read the corresponding header and extract the next
   (rightmost) address from the chain.
4. Repeat (up to the Forward Limit).
5. The first untrusted address, or if the Forward Limit is reached, is the verified client IP.
6. If `REMOTE_ADDR` is not a trusted proxy, the plugin does nothing.
7. If all addresses are trusted, use the outermost (leftmost) address.

### Forward Limit

The Forward Limit controls how many proxy hops the plugin will traverse.
Set this to the exact number of trusted proxies between your server and
the internet.

**Example**: If your stack is `Client → Cloudflare → Nginx → WordPress`,
you have 2 proxies, so set Forward Limit to 2.

This is a second layer of defense, as even if an attacker works a way around the verification there is a limit on the number of accepted proxies. However note that setting it too low means you'll resolve a proxy's IP instead of the client's, so make sure it is enough to cover your longest proxy chain.

## Diagnostics

The **Diagnostics** tab lets you record incoming requests to inspect the
headers and algorithm steps without modifying your site's behaviour.

### Recording Requests

1. Select the number of requests to record (1–100, default 10).
2. Click **Start Recording**.
3. Visit your site through the proxy chain you want to test.
4. Return to the Diagnostics tab to review the recorded requests.

Recording automatically stops when the configured number of requests is
reached. Data expires after 24 hours.

### Reading the Results

Each recorded request shows:

- **Timestamp** and **Request URI**
- **Remote Address** — the raw `REMOTE_ADDR` your server received
- **Resolved IP** — what the algorithm determined as the client IP
- **Changed** — whether the plugin would replace `REMOTE_ADDR`
- **Step Trace** — each hop the algorithm evaluated, with the matched scheme,
  header used, and action taken.
- **Headers** — all HTTP headers from the request

### Privacy Notice

Diagnostic data contains IP addresses and HTTP headers, which may be
personal data under GDPR and similar regulations. Only record what you
need, and clear the data as soon as you're done debugging.

## WordPress Hooks

### Filters

- **`vcip_resolved_ip`** `(string $ip, array $steps): string` — modify the
  resolved IP before it replaces `REMOTE_ADDR`.
- **`vcip_trusted_proxies`** `(array $schemes): array` — dynamically add or
  modify proxy schemes before matching.

### Actions

- **`vcip_ip_resolved`** `(string $newIp, string $originalIp, array $steps)` —
  fired after `REMOTE_ADDR` has been replaced. Useful for logging plugins.

## Must-Use Plugin

For the earliest possible execution, install as a must-use plugin:

1. Copy `gryphon-verified-client-ip.php` and the `src/` directory to
   `wp-content/mu-plugins/gryphon-verified-client-ip/`.
2. Create `wp-content/mu-plugins/gryphon-verified-client-ip-loader.php`:

```php
<?php
require_once ABSPATH . 'wp-content/mu-plugins/gryphon-verified-client-ip/gryphon-verified-client-ip.php';
```

The plugin will run at `muplugins_loaded` priority 0 instead of
`plugins_loaded`.

## Compatibility with Apache `mod_remoteip` and nginx `set_real_ip_from`

### The conflict

Both Apache and nginx ship with built-in modules that perform IP resolution
from forwarding headers:

- **Apache** — `mod_remoteip` reads `X-Forwarded-For` (or a configured header)
  and replaces `REMOTE_ADDR` **before PHP runs**.
- **nginx** — `ngx_http_realip_module` with `set_real_ip_from` does the same.

When either of these is active and trusts your proxy network, `REMOTE_ADDR`
will already contain the resolved client IP by the time WordPress (and this
plugin) starts. The plugin will see `REMOTE_ADDR` as a non-proxy address and
become a no-op.

### How to tell if this is happening

In the Diagnostics tab, if the **Original REMOTE_ADDR** column shows the visitor's IP
(rather than the direct upstream proxy's IP) even before the plugin has
resolved anything, a web-server-level module is pre-resolving the address.

### Option 1 — Disable the web server module (recommended)

Let this plugin handle all IP resolution. Disable the conflicting module:

**Apache** — remove or override `remoteip.conf`:

```apache
# /etc/apache2/conf-enabled/remoteip.conf
# Point mod_remoteip at a non-existent header to effectively disable it.
RemoteIPHeader X-No-Such-Header
```

Or disable the module entirely:

```bash
a2dismod remoteip
```

**nginx** — remove `set_real_ip_from` and `real_ip_header` directives from
your nginx configuration.

### Option 2 — Live with the pre-resolution (limited functionality)

If you cannot change the web server configuration, the plugin will still work
for any remaining hops that the web server module did not resolve. For example,
if `mod_remoteip` resolves one hop and your Forward Limit is 2, the plugin
will resolve the next hop.

However, the plugin's step trace in Diagnostics will start from the
already-resolved address, so the full chain will not be visible.

## Troubleshooting

| Problem                                              | Solution                                                                                                                                                                                         |
| ---------------------------------------------------- | ------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------ |
| Plugin has no effect                                 | Check that `REMOTE_ADDR` matches a trusted proxy in an enabled scheme. Use Diagnostics to verify.                                                                                                |
| Plugin is a no-op even though proxies are configured | Apache `mod_remoteip` or nginx `set_real_ip_from` may be pre-resolving the IP. See [Compatibility with Apache mod_remoteip](#compatibility-with-apache-mod_remoteip-and-nginx-set_real_ip_from). |
| Wrong IP resolved                                    | Check your Forward Limit matches the number of proxies. Use the step trace in Diagnostics.                                                                                                       |
| All requests show proxy IP                           | The proxy may not be setting the expected header. Check the header name in your scheme matches what the proxy actually sends.                                                                    |
| Cloudflare not working                               | Enable the Cloudflare scheme and add [Cloudflare's IP ranges](https://www.cloudflare.com/ips/) to the trusted proxies list.                                                                      |
