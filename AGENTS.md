# AGENTS.md

## Overview

- PHP 8.1+ WordPress 6.4+ plugin, GPLv2 or later
- Testing: Behat/Gherkin BDD (`composer test:bdd`, warning: may take 70s+ to execute, so be patient and don't assume it failed)
- Static analysis: PHPStan (`composer analyse`)
- Formatter: PHP_CodeSniffer with WordPress Coding Standards — `WordPress-Core` standard (`composer format-check`, `composer format`)
- CI: GitHub Actions — format, analyse, and test must all pass before merging

## Architecture

- Source code in `src/`; entry point is the main plugin PHP file in the root
- WordPress hooks (actions/filters) are the primary integration mechanism
- Settings stored via WordPress Options API; transients for temporary/cache data
- Follow WordPress conventions: snake_case for functions and variables, prefixed globals

## Testing

- Tests are Gherkin `.feature` files with step definitions in `features/bootstrap/`
- Feature files live under `features/<topic>/` — one subdirectory per capability area
- Step definitions must call production code
- Cover happy paths, edge cases, error/malformed inputs, and security/spoofing scenarios
- Tests must pass in CI before merging

## Code Quality

- Run `composer check` before committing (runs format-check, analyse, and all tests)
- All inputs must be sanitised and validated; use nonces for form submissions
- Capability checks required for any admin action

## Uninstall

- `uninstall.php` must remove all plugin data (options, transients) on deletion
- Deactivation must not remove data — only flush caches if needed
- In multisite, iterate all sites to clean up

## Local Development

- Project runs in a devcontainer; PHP, Composer, and all tools are available in the terminal
- `composer update --no-interaction --prefer-dist` — install/update dependencies
- `composer check` — full quality check (format, analyse, test)
- `composer test:bdd` — BDD tests only
- `examples/` contains a local proxy chain environment for manual end-to-end testing

## Project Specific Instructions

- Plugin slug and text domain: `gryphon-verified-client-ip`
- All option keys, transient keys, hook names, nonces, and PHP constants use the `vcip_` prefix
- Admin settings menu label: `Verified Client IP`
- Core algorithm: start from `REMOTE_ADDR`, traverse forwarding headers right-to-left through trusted proxies only, stop at first untrusted or malformed address
- Malformed values (non-IP strings, empty values) are treated as untrusted — never skipped
- IPv4-mapped IPv6 addresses (e.g. `::ffff:10.0.0.1`) must normalise to their IPv4 form before matching
- Port numbers must be stripped before IP matching
- Multiple same-type headers are concatenated per RFC 7230 §3.2.2 and processed right-to-left
- Three default proxy schemes: RFC 7239 `Forwarded` (enabled), `X-Forwarded-For` (enabled), Cloudflare `CF-Connecting-IP` (disabled by default)
- Filters exposed: `vcip_resolved_ip` (modify final IP), `vcip_trusted_proxies` (add proxies dynamically)
- Action exposed: `vcip_ip_resolved` (fired after REMOTE_ADDR replacement)
- Diagnostics records up to 100 requests as transients (24h expiry); UI must include a GDPR/privacy notice
- Settings and diagnostics are per-site in multisite (not network-wide)
- Settings and diagnostics pages require `manage_options` capability
