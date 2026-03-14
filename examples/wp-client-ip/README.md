# Local Development Environment

This directory contains proxy configurations and the Docker Compose file used to set up a local testing environment for the Verified Client IP plugin.

## Quick Start

First build the plugin, e.g. use the development container:

```bash
./build.sh
```

This will publish the component to `build/verified-client-ip/`.

Then on your host run the compose file to start the environment:

```powershell
# Start all services from the examples folder (Podman preferred)
cd examples/wp-client-ip
podman compose up -d
```

Activate the plugin via the WordPress admin UI at http://localhost:8100/wp-admin/.

The first time you run the example you will need to create a Wordpress site with an admin account (e.g. admin/P@ssw0rd).

Once activated, to see the plugin in action, you can go to Settings > Verified Client IP, and then on the Diagnostics tab click Start Diagnostics.

Access the website directly at http://localhost:8100/ and then via proxies http://localhost:8101/ and http://localhost:8102/. Note that the example configuration is set up with dynamic host detection so that the proxy URL (with the port) is retained, to make testing easier.

Refresh and check the diagnostics to see how the verified client IP is resolved.

## Services & Ports

| Port | Service       | Description                                      |
| ---- | ------------- | ------------------------------------------------ |
| 8100 | WordPress     | Direct access (no proxy)                         |
| 8101 | Proxy A       | RFC 7239 `Forwarded` → WordPress                 |
| 8102 | Proxy B       | RFC 7239 `Forwarded` → Proxy A                   |
| 8103 | Proxy C       | RFC 7239 `Forwarded` → Proxy B → Proxy A → WP    |
| 8112 | Proxy XFF     | `X-Forwarded-For` → Proxy A → WordPress          |
| 8122 | Proxy CF      | Cloudflare-sim (`CF-Connecting-IP`) → Proxy A    |
| 8180 | noVNC Desktop | Lightweight Linux desktop for IPv6 browser tests |

## Proxy Chains

### RFC 7239 Forwarded chain (ports 8101–8103)

```
Client → Proxy C (:8103) → Proxy B (:8102) → Proxy A (:8101) → WordPress
```

Each proxy adds a `Forwarded: for=<client>;proto=<proto>;host=<host>` header.
The plugin should resolve the full chain with Forward Limit set to 3.

### X-Forwarded-For (port 8112)

```
Client → Proxy XFF (:8112) → Proxy A (:8101) → WordPress
```

Uses the `X-Forwarded-For` header. Configure the X-Forwarded-For scheme
with Forward Limit 2.

### Cloudflare simulation (port 8122)

```
Client → Proxy CF (:8122) → Proxy A (:8101) → WordPress
```

Sets `CF-Connecting-IP` to the client's IP address, simulating Cloudflare's
behaviour. Enable the Cloudflare scheme in the plugin settings and add the
proxy's container IP to its trusted proxy list.

## IPv6 Testing

The compose network has IPv6 enabled (`fd12:3456:789a::/48`). Containers
communicate over both IPv4 and IPv6.

To test IPv6 from a browser, use the noVNC desktop at http://localhost:8180.
This avoids Windows limitations with IPv6 connections to containers. Open
Firefox inside the desktop and navigate to `http://wordpress/` or use the
IPv6 address directly.

## Shutting Down

```bash
podman compose down        # Stop containers
podman compose down -v     # Stop and remove volumes (database data)
```
