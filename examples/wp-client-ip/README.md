# Local Development Environment

This directory contains proxy configurations and the Docker Compose file used to set up a local testing environment for the Verified Client IP plugin.

## Quick Start

Run the compose file on your host to start the environment:

```powershell
# Start all services from the examples folder (Podman preferred)
cd examples/wp-client-ip
podman compose up -d
```

This will load the source code into the plugin directory, so changes to the source code will be reflected immediately.

Activate the plugin via the WordPress admin UI at http://localhost:8064/wp-admin/.

The first time you run the example you will need to create a WordPress site with an admin account (e.g. admin/P@ssw0rd).

Once activated, to see the plugin in action, you can go to Settings > Verified Client IP, and then on the Diagnostics tab click Start Diagnostics.

Access the website directly at http://localhost:8064/ and then via proxies http://localhost:8128/ and http://localhost:8160/. Note that the example configuration is set up with dynamic host detection so that the proxy URL (with the port) is retained, to make testing easier.

Refresh and check the diagnostics to see how the verified client IP is resolved. Note that by default only 1 hop is resolved, so the proxy using 2 hops (port 8160) will only resolve to the address of that proxy (not the actual client).

## Services & Ports

| Port      | Service      | IPv4         | IPv6                   | Description                                     |
| --------- | ------------ | ------------ | ---------------------- | ----------------------------------------------- |
| 8064      | WordPress    | 10.72.39.64  | fd00:72:39:0:4000::64  | Direct access (no proxy)                        |
| 8128      | Proxy A      | 10.72.39.128 | fd00:72:39:0:8000::128 | RFC 7239 `Forwarded` → WordPress                |
| 8160      | Proxy B      | 10.72.39.160 | fd00:72:39:0:A000::160 | RFC 7239 `Forwarded` → Proxy A                  |
| 8161      | Proxy XFF    | 10.72.39.161 | fd00:72:39:0:A100::161 | `X-Forwarded-For` → Proxy A → WordPress         |
| 8162      | Proxy CF     | 10.72.39.162 | fd00:72:39:0:A200::162 | Cloudflare-sim (`CF-Connecting-IP`) → Proxy A   |
| 8164      | Proxy 64     | 10.72.39.164 | fd00:72:39:0:A400::164 | IPv6→IPv4 converting → Proxy A (IPv4 upstream)  |
| 8166      | Proxy 46     | 10.72.39.166 | fd00:72:39:0:A600::166 | IPv4→IPv6 converting → Proxy A (IPv6 upstream)  |
| 8192      | Proxy C      | 10.72.39.192 | fd00:72:39:0:C000::192 | RFC 7239 `Forwarded` → Proxy B → Proxy A → WP   |
| 8032/8033 | Test Browser | 10.72.39.32  | fd00:72:39:0:2000::32  | Firefox running in noVNC for IPv6 browser tests |

Default connections come in from `10.72.39.1`.

## Proxy Chains

### RFC 7239 Forwarded chain

```
Client → Proxy C (:8192) → Proxy B (:8160) → Proxy A (:8128) → WordPress (:8064)
```

Each proxy adds a `Forwarded: for=<client>;proto=<proto>;host=<host>` header.
The plugin should resolve the full chain with Forward Limit set to 3.

### X-Forwarded-For (port 8161)

```
Client → Proxy XFF (:8161) → Proxy A (:8128) → WordPress (:8064)
```

Uses the `X-Forwarded-For` header. Configure the X-Forwarded-For scheme
with Forward Limit 2.

### Protocol-translating proxies (ports 8164, 8166)

```
Client → Proxy 64 (:8164) → Proxy A (:8128, IPv4 upstream) → WordPress
Client → Proxy 46 (:8166) → Proxy A (:8128, IPv6 upstream) → WordPress
```

These proxies listen on both IPv4 and IPv6 but always forward to a single
fixed-protocol upstream, allowing testing of protocol conversion scenarios:

### Cloudflare simulation (port 8162)

```
Client → Proxy CF (:8162) → Proxy A (:8128) → WordPress (:8064)
```

Sets `CF-Connecting-IP` to the client's IP address, simulating Cloudflare's
behaviour. Enable the Cloudflare scheme in the plugin settings and add the
proxy's container IP to its trusted proxy list.

## Trusted Proxy CIDR Ranges

The proxy addresses are arranged so you can test different CIDR configurations
in the plugin settings:

| IPv4 range        | IPv6 range               | Covers                         |
| ----------------- | ------------------------ | ------------------------------ |
| `10.72.39.128/25` | `fd00:72:39:0:8000::/65` | All proxies (A, B, XFF, CF, C) |
| `10.72.39.128/26` | `fd00:72:39:0:8000::/66` | Proxy A + B, XFF, CF           |
| `10.72.39.128/27` | `fd00:72:39:0:8000::/67` | Proxy A only                   |

To test multi-hop resolution, set the trusted proxy range to cover the proxies
you want to traverse and adjust the Forward Limit accordingly.

## Build package testing

You can use the development container to build the package, which will publish to `build/verified-client-ip/`.

```bash
./build.sh
```

You can then change the `compose.yaml` file to mount the built plugin instead of the source code.

## IPv6 Testing

The compose network has IPv6 enabled (`fd00:72:39::/48`). Containers communicate over both IPv4 and IPv6.

To test IPv6 from a browser, use Firefox noVNC at https://localhost:8033. This avoids Windows limitations
with IPv6 connections to containers. In the browser you can connect direct to the IPv6 address,
`http://[fd00:72:39:0:4000::64]`, or via one of the proxies, `http://[fd00:72:39:0:C000::192]`.

The protocol translating proxies can also be used to test protocol conversion scenarios, resolving
the correct client IP, even when the initial REMOTE_ADDR (closest proxy) is a different protocol:

| URL                               | Proxy    | Original REMOTE_ADDR     | Client IP               |
| --------------------------------- | -------- | ------------------------ | ----------------------- |
| `http://[fd00:72:39:0:A000::160]` | Proxy B  | `fd00:72:39:0:8000::128` | `fd00:72:39:0:2000::32` |
| `http://10.72.39.166`             | Proxy 46 | `fd00:72:39:0:8000::128` | `10.72.39.32`           |
| `http://[fd00:72:39:0:A400::164]` | Proxy 64 | `10.72.39.128`           | `fd00:72:39:0:2000::32` |

## Apache `mod_remoteip` and this plugin

The WordPress Docker image ships with Apache's `mod_remoteip` enabled and
configured to trust all RFC 1918 private IP ranges. This module reads
`X-Forwarded-For` and replaces `REMOTE_ADDR` **before PHP runs**, which means
the plugin would receive an already-resolved IP and become a no-op.

To prevent this, the compose file mounts
`wordpress-apache/disable-remoteip.conf` over the default `remoteip.conf`,
redirecting `mod_remoteip` to a non-existent header so it leaves `REMOTE_ADDR`
untouched. The plugin then performs its own verified resolution.

> **Note for production use:** If your server runs Apache with `mod_remoteip`
> (or nginx with `set_real_ip_from`) you should disable that feature and let
> this plugin handle IP resolution instead. See the
> [user guide](../../docs/user-guide.md) for details.

## Shutting Down

```bash
podman compose down        # Stop containers
podman compose down -v     # Stop and remove volumes (database data)
```
