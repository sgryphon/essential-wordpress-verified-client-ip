# Local Development Environment

This directory contains proxy configurations used by the `compose.yaml` file
in the project root to set up a local testing environment for the Verified
Client IP plugin.

## Quick Start

```bash
# Start all services (Podman preferred)
podman compose up -d

# Or with Docker
docker compose up -d
```

## Services & Ports

| Port | Service        | Description                                      |
|------|----------------|--------------------------------------------------|
| 8100 | WordPress      | Direct access (no proxy)                         |
| 8101 | Proxy A        | RFC 7239 `Forwarded` → WordPress                 |
| 8102 | Proxy B        | RFC 7239 `Forwarded` → Proxy A                   |
| 8103 | Proxy C        | RFC 7239 `Forwarded` → Proxy B → Proxy A → WP   |
| 8112 | Proxy XFF      | `X-Forwarded-For` → Proxy A → WordPress          |
| 8122 | Proxy CF       | Cloudflare-sim (`CF-Connecting-IP`) → Proxy A    |
| 8180 | noVNC Desktop  | Lightweight Linux desktop for IPv6 browser tests |

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

## Installing the Plugin for Development

The plugin source is bind-mounted read-only into the WordPress container at:

```
/var/www/html/wp-content/plugins/verified-client-ip
```

Any changes you make to the plugin files on your host are immediately
reflected in the container. Activate the plugin via the WordPress admin UI
at http://localhost:8100/wp-admin/.

If you modify `composer.json`, rebuild the autoloader:

```bash
podman compose exec wordpress bash -c "cd /var/www/html/wp-content/plugins/verified-client-ip && composer dump-autoload"
```

## Shutting Down

```bash
podman compose down        # Stop containers
podman compose down -v     # Stop and remove volumes (database data)
```
