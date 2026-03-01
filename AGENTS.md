# AGENTS.md

## Project Overview

**Verified Client IP** is a WordPress plugin (slug: `verified-client-ip`) that determines the true client IP address by verifying Forwarded-For and similar headers, traversing only trusted proxy hops. It replaces `$_SERVER['REMOTE_ADDR']` with the verified IP early in the WordPress lifecycle.

The full specification is in [specifications/Main Specification.md](specifications/Main%20Specification.md).

## Tech Stack

- **Language:** PHP 8.1+
- **Platform:** WordPress 6.4+
- **License:** GPLv2 or later
- **Text domain:** `verified-client-ip`
- **Testing:** PHPUnit (pure unit tests), WP_Mock (integration tests)
- **Static analysis:** PHPStan or Psalm
- **Formatter:** PHP-CS-Fixer (`@PSR12` + strict rules) or Laravel Pint
- **CI:** GitHub Actions
- **Container runtime:** Podman (preferred) or Docker

## Key Architecture Decisions

### Execution Timing
The IP resolution hook must fire as early as possible: `muplugins_loaded` (must-use plugin) or `plugins_loaded` at priority 0 (regular plugin). REMOTE_ADDR must be replaced before any other plugin or WordPress core reads it. Admin UI uses later hooks (`admin_init`, `admin_menu`).

### Core Algorithm
1. Start with `REMOTE_ADDR`.
2. If it matches a trusted proxy in any enabled scheme (by priority order), read the corresponding header and extract the next address (rightmost first).
3. Repeat, traversing the chain backwards, up to the configured **Forward Limit** (default: 1).
4. Stop at the first untrusted or malformed address — that becomes the verified client IP.
5. If `REMOTE_ADDR` is not a trusted proxy, the plugin is a no-op.
6. If all addresses are trusted, use the outermost (last) forwarded address.
7. Replace `REMOTE_ADDR` and store the original in `X-Original-Remote-Addr`.

### Important Behaviours
- **Malformed values** (non-IP strings, empty values) are treated as untrusted — never skipped.
- **IPv4-mapped IPv6** addresses (e.g. `::ffff:10.0.0.1`) must normalise and match their IPv4 equivalent.
- **Port numbers** are stripped before matching.
- **Multiple same-type headers** are concatenated per RFC 7230 §3.2.2 and processed right-to-left.
- **Scheme priority**: first matching enabled scheme wins; only one scheme applies per hop.

### Storage
- All settings stored via WordPress Options API (`wp_options`), prefixed `vcip_` (e.g. `vcip_settings`).
- Diagnostic data stored as WordPress transients with 24-hour expiry.
- Per-site in multisite (not network-wide).

### WordPress Hooks Exposed
- **Filter `vcip_resolved_ip`**: modify the final IP before it is set (receives resolved IP + step trace).
- **Filter `vcip_trusted_proxies`**: dynamically add proxy addresses before matching.
- **Action `vcip_ip_resolved`**: fired after replacement (receives new IP, original IP, step trace).

### Permissions
- Settings and diagnostics require `manage_options` capability.
- All forms use WordPress nonces (CSRF protection).
- All inputs must be sanitised and validated.

## Configuration & Default Schemes

Three default schemes ship with the plugin:

1. **RFC 7239 Forwarded** (enabled) — Header: `Forwarded`, Token: `for`. Trusted proxies: private IPv4 ranges + localhost + IPv6 ULA.
2. **X-Forwarded-For** (enabled) — Header: `X-Forwarded-For`. Trusted proxies: same private ranges.
3. **Cloudflare** (disabled by default) — Header: `HTTP_CF_CONNECTING_IP`. Trusted proxies: Cloudflare's published IP ranges.

Custom schemes can be added with: Name, Enabled toggle, Proxy addresses/CIDR ranges, Header name, optional Token, optional Notes.

### Proto & Host Processing
- **Proto** (configurable, default on): sets `$_SERVER['HTTPS']` and `$_SERVER['REQUEST_SCHEME']` from proxy headers. Originals stored in `X-Original-HTTPS` and `X-Original-Request-Scheme`.
- **Host** (configurable, default off): sets `HTTP_HOST` and `SERVER_NAME`. Original stored in `X-Original-Host`.

## Diagnostics

- Records a configurable number of requests (default 10, max 100) then stops.
- Captures timestamps, request URI, all `$_SERVER` headers, and algorithm step trace.
- Works even when the plugin's main switch is off (calculates but does not apply).
- Stored as transients (24h expiry). Concurrent writes must be guarded with locking.
- UI must display a GDPR/privacy notice about IP and header data.

## Testing Guidelines

### Test Organisation
Tests are split across multiple files by concern:
- Algorithm / IP resolution tests (majority of tests)
- Header parsing tests (RFC 7239 `Forwarded`, `X-Forwarded-For`, etc.)
- CIDR matching and IPv4/IPv6 normalisation tests
- Configuration / settings tests
- Diagnostics tests
- Security / spoofing tests

### Test Principles
- **Pure unit tests** for the core algorithm — mock `$_SERVER` and WordPress functions. No full WordPress installation required.
- **Integration tests** via WP_Mock for hook wiring.
- Include IPv4, IPv6, and IPv4-mapped IPv6 scenarios.
- Include multi-hop chains (e.g. client → Cloudflare → proxy1 → proxy2 → server).
- Include attack/spoofing scenarios (injected headers, extra headers before proxies).
- Include graceful degradation (wrong header name, malformed values, empty headers from trusted proxies).
- Tests must pass in CI (GitHub Actions) before merging.

## Code Quality

- Run formatter, static analysis, and tests before committing.
- All three checks run in the GitHub Actions pipeline.
- Quality gates must pass for CI to succeed.

## Uninstall Behaviour

- `uninstall.php` must delete all `vcip_*` options and diagnostic transients.
- In multisite, iterate all sites to clean up.
- Deactivation only flushes caches — does **not** remove data.

## Local Development / Examples

### PHP / Composer Are Not Available on the Host

PHP and Composer are **not** installed on the development machine. All PHP tooling (Composer, PHPUnit, PHPStan, PHP-CS-Fixer) must be run inside a container. Use a one-off container with the project mounted:

```bash
# Podman (preferred)
podman run --rm -v "$PWD:/app" -w /app docker.io/library/composer:2 <command>

# Docker
docker run --rm -v "$PWD:/app" -w /app docker.io/library/composer:2 <command>
```

Common examples:

```bash
# Install / update dependencies
podman run --rm -v "$PWD:/app" -w /app docker.io/library/composer:2 update --no-interaction --prefer-dist

# Run the full check suite (format, analyse, test)
podman run --rm -v "$PWD:/app" -w /app docker.io/library/composer:2 run-script check

# Run tests only
podman run --rm -v "$PWD:/app" -w /app docker.io/library/composer:2 run-script test
```

> **Do not** attempt to run `php`, `composer`, `phpunit`, or any other PHP command directly on the host — they will fail.

### Container Compose Environment

A container compose file provides a local test environment with:
- WordPress on port 8100
- Proxy chains on ports 8101–8103 (RFC 7239 `Forwarded` headers)
- X-Forwarded-For proxy on port 8112
- Cloudflare-simulating proxy on port 8122
- A lightweight Linux desktop (noVNC) on port 8180 for IPv6 browser testing

IPv6 networking must be explicitly enabled in the compose file. At least one chain must use IPv6 between hops.

## File & Naming Conventions

- WordPress plugin slug: `verified-client-ip`
- Text domain for i18n: `verified-client-ip`
- Option keys prefixed: `vcip_`
- Transient keys prefixed: `vcip_`
- Hook names prefixed: `vcip_`
