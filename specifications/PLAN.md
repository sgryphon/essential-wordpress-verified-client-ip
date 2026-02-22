# Verified Client IP — Implementation Plan

Progress tracker. Check off items as completed.

## Step 1 — Project Scaffolding & Tooling

- [x] Create `verified-client-ip.php` with standard WordPress plugin header
- [x] Create `composer.json` with PHPUnit, PHPStan, PHP-CS-Fixer (WP_Mock deferred to Step 5)
- [x] Create `phpunit.xml.dist` configuration
- [x] Create `phpstan.neon` configuration
- [x] Create `.php-cs-fixer.dist.php` formatter config (PSR-12 strict)
- [x] Create GitHub Actions CI workflow (`.github/workflows/ci.yml`)
- [ ] Verify everything runs locally (formatter, analysis, tests) — deferred to CI
- [x] Commit

## Step 2 — Core IP Utilities (CIDR Matching, Normalisation)

- [x] Create `src/IpUtils.php` with CIDR matching (IPv4 + IPv6)
- [x] Add IPv4-mapped IPv6 normalisation
- [x] Add port stripping (including bracketed IPv6)
- [x] Add IP validation helper
- [x] Write unit tests in `tests/Unit/IpUtilsTest.php`
- [ ] Verify all checks pass
- [ ] Commit

## Step 3 — Header Parsing

- [x] Create `src/HeaderParser.php`
- [x] Implement RFC 7239 `Forwarded` header parsing (token extraction, quoted values)
- [x] Implement `X-Forwarded-For` parsing (comma-separated, right-to-left)
- [x] Implement single-value header parsing (e.g. `CF-Connecting-IP`)
- [x] Handle multiple same-name headers (RFC 7230 §3.2.2 concatenation)
- [x] Write unit tests in `tests/Unit/HeaderParserTest.php`
- [ ] Verify all checks pass
- [ ] Commit

## Step 4 — Core Resolution Algorithm

- [x] Create `src/Resolver.php` with main resolution logic
- [x] Implement scheme-priority matching (with header-presence check)
- [x] Implement Forward Limit enforcement (limit = max proxy hops; return next address without checking when limit reached)
- [x] Implement step-trace generation
- [x] Handle malformed values as untrusted
- [x] Handle all-trusted chains (use outermost address)
- [x] Write unit tests in `tests/Unit/ResolverTest.php` — no-op, single-hop, multi-hop, multi-scheme, Forward Limit, malformed, spoofing, IPv4/IPv6 mixed, IPv4-mapped IPv6
- [ ] Verify all checks pass
- [ ] Commit

## Step 5 — WordPress Integration

- [x] Create `src/Plugin.php` — hook into `plugins_loaded` (priority 0); mu-plugin support documented
- [x] Replace `REMOTE_ADDR`, store original in `X-Original-Remote-Addr`
- [x] Implement Proto processing (`HTTPS`, `REQUEST_SCHEME`) with originals stored
- [x] Implement Host processing (off by default) with original stored
- [x] Expose filters: `vcip_resolved_ip`, `vcip_trusted_proxies`
- [x] Expose action: `vcip_ip_resolved`
- [x] Default schemes (RFC 7239, XFF, Cloudflare disabled) with `defaultSchemes()` helper
- [x] Disabled mode: calculates but does not apply (for diagnostics)
- [x] Write integration tests in `tests/Integration/PluginTest.php` (with WP function stubs)
- [ ] Verify all checks pass
- [ ] Commit

## Step 6 — Settings & Configuration Storage

- [x] Create `src/Settings.php` — data model, load/save via Options API
- [x] Define default schemes (RFC 7239, XFF, Cloudflare) in Settings
- [x] Include default proxy ranges per spec
- [x] Input validation for settings (forward limit, proxies, header names, scheme limits)
- [x] Refactor Plugin.php to delegate to Settings::load()
- [x] Write tests in `tests/Unit/SettingsTest.php`
- [ ] Verify all checks pass
- [ ] Commit

## Step 7 — Admin UI (Settings Page)

- [x] Create `src/AdminPage.php` — register settings sub-menu
- [x] Build main settings form (enable, Forward Limit, Proto, Host)
- [x] Build scheme list with priority reordering (up/down arrows via JS)
- [x] Build scheme config panels (proxies, header, token, notes)
- [x] Support custom scheme add/delete (JS-powered)
- [x] Nonce + capability checks
- [x] Wire AdminPage::register() from plugin entry point (admin only)
- [x] Write integration tests in `tests/Integration/AdminPageTest.php`
- [ ] Verify all checks pass
- [ ] Commit

## Step 8 — Diagnostics

- [x] Create `src/Diagnostics.php`
  - [x] Constants: transient keys, default/max request count, expiry, lock duration
  - [x] `maybeRecord()` — records request if active + under limit, with locking
  - [x] `startRecording()` / `stopRecording()` / `clear()` state management
  - [x] `getState()` / `getLog()` / `isRecording()` read operations
  - [x] `buildEntry()` — extracts headers, result data, step trace
  - [x] Transient-based lock to prevent concurrent writes
  - [x] Auto-stop when limit reached
- [x] Add Diagnostics tab to admin page (tab navigation in `renderPage()`)
- [x] Implement request recording (timestamp, URI, headers, step trace)
- [x] Transient storage with 24h expiry + locking
- [x] Start/Clear buttons, request count config
- [x] Work when main switch is off (called after resolution, before enabled check)
- [x] Expandable request detail display
- [x] GDPR/privacy notice
- [x] Add transient stubs to `tests/Integration/bootstrap.php`
- [x] Write tests in `tests/Integration/DiagnosticsTest.php`
- [ ] Verify all checks pass
- [x] Commit

## Step 9 — Logging

- [x] Create `src/Logger.php` with error/warning/info/debug levels
  - [x] Consistent `[Verified Client IP]` prefix
  - [x] Debug/request-level logging gated by WP_DEBUG / VCIP_LOG_REQUESTS
  - [x] Context parameter for categorisation
- [x] Add request-level debug logging in Plugin (resolver result)
- [x] Add warnings for malformed forwarded values in resolver steps
- [x] Add admin action logging (settings saved, diagnostics start/clear)
- [x] Write tests in `tests/Unit/LoggerTest.php`
- [x] Commit

## Step 10 — Uninstall & Deactivation

- [x] Create `uninstall.php` — delete `vcip_*` options + diagnostic transients
- [x] Handle multisite (iterate sites via `get_sites` / `switch_to_blog`)
- [x] Deactivation hook — `wp_cache_flush()` only, no data removal
- [x] Add stubs to bootstrap (delete_option, register_deactivation_hook, wp_cache_flush, plugin_dir_path)
- [x] Write tests in `tests/Integration/UninstallTest.php`
- [x] Commit

## Step 11 — Local Development Environment

- [x] Create `compose.yaml` (Podman/Docker)
- [x] WordPress service on port 8100
- [x] RFC 7239 proxy chain (ports 8101–8103) with Caddy
- [x] X-Forwarded-For proxy (port 8112)
- [x] Cloudflare-sim proxy (port 8122)
- [x] noVNC desktop (port 8180) for IPv6 testing
- [x] Enable IPv6 networking (`fd12:3456:789a::/48`)
- [x] Proxy configs (Caddy) in `examples/` subdirectories
- [x] Usage instructions in `examples/README.md`
- [x] Commit

## Step 12 — Packaging & Documentation

- [x] Build scripts (`build.sh`, `build.ps1`) for distributable `.zip`
- [x] Expand `README.md` (overview, badges, features, quick start, links)
- [x] Development guide (`docs/development.md`)
- [x] Packaging guide (`docs/packaging.md`)
- [x] Examples guide (`examples/README.md`) — done in Step 11
- [x] User documentation (`docs/user-guide.md`)
- [x] Commit

## Step 13 — Final Review & Polish

- [ ] All CI checks green
- [ ] Spec compliance audit
- [ ] Default schemes/ranges verified
- [ ] No unresolved TODOs
- [ ] Tag `v0.1.0`
