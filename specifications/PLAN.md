# Verified Client IP — Implementation Plan

Progress tracker. Check off items as completed.

## Step 1 — Project Scaffolding & Tooling

- [ ] Create `verified-client-ip.php` with standard WordPress plugin header
- [ ] Create `composer.json` with PHPUnit, WP_Mock, PHPStan, PHP-CS-Fixer
- [ ] Create `phpunit.xml.dist` configuration
- [ ] Create `phpstan.neon` configuration
- [ ] Create `.php-cs-fixer.dist.php` formatter config (PSR-12 strict)
- [ ] Create GitHub Actions CI workflow (`.github/workflows/ci.yml`)
- [ ] Verify everything runs locally (formatter, analysis, tests)
- [ ] Commit

## Step 2 — Core IP Utilities (CIDR Matching, Normalisation)

- [ ] Create `src/IpUtils.php` with CIDR matching (IPv4 + IPv6)
- [ ] Add IPv4-mapped IPv6 normalisation
- [ ] Add port stripping (including bracketed IPv6)
- [ ] Add IP validation helper
- [ ] Write unit tests in `tests/Unit/IpUtilsTest.php`
- [ ] Verify all checks pass
- [ ] Commit

## Step 3 — Header Parsing

- [ ] Create `src/HeaderParser.php`
- [ ] Implement RFC 7239 `Forwarded` header parsing (token extraction, quoted values)
- [ ] Implement `X-Forwarded-For` parsing (comma-separated, right-to-left)
- [ ] Implement single-value header parsing (e.g. `CF-Connecting-IP`)
- [ ] Handle multiple same-name headers (RFC 7230 §3.2.2 concatenation)
- [ ] Write unit tests in `tests/Unit/HeaderParserTest.php`
- [ ] Verify all checks pass
- [ ] Commit

## Step 4 — Core Resolution Algorithm

- [ ] Create `src/Resolver.php` with main resolution logic
- [ ] Implement scheme-priority matching
- [ ] Implement Forward Limit enforcement
- [ ] Implement step-trace generation
- [ ] Handle malformed values as untrusted
- [ ] Handle all-trusted chains (use outermost address)
- [ ] Write unit tests in `tests/Unit/ResolverTest.php` — no-op, single-hop, multi-hop, multi-scheme, Forward Limit, malformed, spoofing, IPv4/IPv6 mixed, IPv4-mapped IPv6
- [ ] Verify all checks pass
- [ ] Commit

## Step 5 — WordPress Integration

- [ ] Create `src/Plugin.php` — hook into `plugins_loaded` / `muplugins_loaded`
- [ ] Replace `REMOTE_ADDR`, store original in `X-Original-Remote-Addr`
- [ ] Implement Proto processing (`HTTPS`, `REQUEST_SCHEME`)
- [ ] Implement Host processing (off by default)
- [ ] Expose filters: `vcip_resolved_ip`, `vcip_trusted_proxies`
- [ ] Expose action: `vcip_ip_resolved`
- [ ] Write integration tests in `tests/Integration/PluginTest.php`
- [ ] Verify all checks pass
- [ ] Commit

## Step 6 — Settings & Configuration Storage

- [ ] Create `src/Settings.php` — data model, load/save via Options API
- [ ] Define default schemes (RFC 7239, XFF, Cloudflare)
- [ ] Include default proxy ranges per spec
- [ ] Input validation for settings
- [ ] Write tests in `tests/Unit/SettingsTest.php`
- [ ] Verify all checks pass
- [ ] Commit

## Step 7 — Admin UI (Settings Page)

- [ ] Create `src/AdminPage.php` — register settings sub-menu
- [ ] Build main settings form (enable, Forward Limit, Proto, Host)
- [ ] Build scheme list with priority reordering (up/down arrows)
- [ ] Build scheme config panels (proxies, header, token, notes)
- [ ] Support custom scheme add/delete
- [ ] Nonce + capability checks
- [ ] Verify all checks pass
- [ ] Commit

## Step 8 — Diagnostics

- [ ] Create `src/Diagnostics.php`
- [ ] Add Diagnostics tab to admin page
- [ ] Implement request recording (timestamp, URI, headers, step trace)
- [ ] Transient storage with 24h expiry + locking
- [ ] Start/Clear buttons, request count config
- [ ] Work when main switch is off
- [ ] Expandable request detail display
- [ ] GDPR/privacy notice
- [ ] Write tests in `tests/Unit/DiagnosticsTest.php`
- [ ] Verify all checks pass
- [ ] Commit

## Step 9 — Logging

- [ ] Add logging for admin actions (settings changes)
- [ ] Add warnings for malformed/fake headers
- [ ] Add error logging
- [ ] Minimal request-level logging (optional)
- [ ] Commit

## Step 10 — Uninstall & Deactivation

- [ ] Create `uninstall.php` — delete `vcip_*` options + transients
- [ ] Handle multisite (iterate sites)
- [ ] Deactivation hook — flush caches only
- [ ] Write tests
- [ ] Commit

## Step 11 — Local Development Environment

- [ ] Create `compose.yaml` (Podman/Docker)
- [ ] WordPress service on port 8100
- [ ] RFC 7239 proxy chain (ports 8101–8103)
- [ ] X-Forwarded-For proxy (port 8112)
- [ ] Cloudflare-sim proxy (port 8122)
- [ ] noVNC desktop (port 8180) for IPv6 testing
- [ ] Enable IPv6 networking
- [ ] Proxy configs (Caddy/Nginx)
- [ ] Usage instructions
- [ ] Commit

## Step 12 — Packaging & Documentation

- [ ] Build script for distributable `.zip`
- [ ] Expand `README.md` (overview, badge, links)
- [ ] Development guide
- [ ] Packaging guide
- [ ] Examples guide
- [ ] User documentation
- [ ] Commit

## Step 13 — Final Review & Polish

- [ ] All CI checks green
- [ ] Spec compliance audit
- [ ] Default schemes/ranges verified
- [ ] No unresolved TODOs
- [ ] Tag `v0.1.0`
