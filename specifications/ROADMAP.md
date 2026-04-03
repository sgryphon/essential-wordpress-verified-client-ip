# Essential Verified Client IP — Implementation Roadmap

Each step is implemented as a separate commit. Every commit must leave the project in a buildable, testable state.

## Step 1 — Project Scaffolding & Tooling

Set up the plugin skeleton, Composer dependencies, CI pipeline, and code-quality tooling.

## Step 2 — Core IP Utilities (CIDR Matching, Normalisation)

Implement and test helpers for CIDR matching (IPv4/IPv6), IPv4-mapped IPv6 normalisation, port stripping, and IP validation.

## Step 3 — Header Parsing

Implement and test parsers for RFC 7239 `Forwarded`, `X-Forwarded-For`, single-value headers, and multi-header concatenation.

## Step 4 — Core Resolution Algorithm

Implement the main IP resolution algorithm with step-trace output. Extensive tests for single/multi-hop, Forward Limit, malformed values, spoofing, mixed IPv4/IPv6.

## Step 5 — WordPress Integration (Hooks, REMOTE_ADDR Replacement)

Hook the resolver into WordPress lifecycle, replace `REMOTE_ADDR`, implement Proto/Host processing, expose `vcip_*` hooks/filters.

## Step 6 — Settings & Configuration Storage

Settings data model, Options API storage, default schemes (RFC 7239, X-Forwarded-For, Cloudflare).

## Step 7 — Admin UI (Settings Page)

WordPress Settings sub-menu page with scheme management, validation, nonces, capability checks.

## Step 8 — Diagnostics

Diagnostics tab, request recording with transient storage, locking, step-trace display, privacy notice.

## Step 9 — Logging

WordPress logging for admin actions, warnings for malformed/fake headers, minimal request-level logging.

## Step 10 — Uninstall & Deactivation

`uninstall.php` cleanup of all `vcip_*` data; multisite iteration; deactivation flushes caches only.

## Step 11 — Local Development Environment (Examples)

Podman/Docker Compose with WordPress, proxy chains (RFC 7239, XFF, Cloudflare-sim), IPv6, noVNC desktop.

## Step 12 — Packaging & Documentation

Build script, README expansion, development/packaging/examples/user docs.

## Step 13 — Final Review & Polish

CI green, spec compliance audit, tag `v0.1.0`.
