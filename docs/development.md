# Development Guide

## Prerequisites

- **Podman** (preferred) or Docker — for running tests in containers
- **Git** — for version control
- No native PHP or Composer required on the host; everything runs in containers

If you have PHP 8.1+ and Composer installed locally, you can run tests
directly. Otherwise, use the container commands below.

## Getting Started

```bash
git clone https://github.com/sgryphon/essential-wordpress-verified-client-ip.git
cd essential-wordpress-verified-client-ip
```

### Install Dependencies (container)

```bash
podman run --rm -v "$PWD":/app -w /app docker.io/library/composer:2 install
```

### Install Dependencies (local)

```bash
composer install
```

## Running Tests

### Via Container

```bash
podman run --rm -v "$PWD":/app -w /app docker.io/library/php:8.3-cli vendor/bin/phpunit
```

### Via Local PHP

```bash
vendor/bin/phpunit
```

### Running Specific Tests

```bash
# Run only unit tests
vendor/bin/phpunit tests/Unit

# Run only integration tests
vendor/bin/phpunit tests/Integration

# Run a specific test file
vendor/bin/phpunit tests/Unit/ResolverTest.php

# Run a specific test method
vendor/bin/phpunit --filter testSingleProxyXff tests/Unit/ResolverTest.php
```

## Code Quality

### Static Analysis (PHPStan)

```bash
# Container
podman run --rm -v "$PWD":/app -w /app docker.io/library/php:8.3-cli vendor/bin/phpstan analyse

# Local
vendor/bin/phpstan analyse
```

### Code Formatting (PHP-CS-Fixer)

```bash
# Check formatting
vendor/bin/php-cs-fixer fix --dry-run --diff

# Fix formatting
vendor/bin/php-cs-fixer fix
```

## Project Structure

```
verified-client-ip.php        Main plugin entry point
uninstall.php                 Cleanup on plugin deletion
src/
├── AdminPage.php             Admin settings page UI
├── Diagnostics.php           Request recording for debugging
├── HeaderParser.php          RFC 7239 / XFF / single-value parsing
├── IpUtils.php               CIDR matching, IPv4/IPv6 normalisation
├── Logger.php                error_log wrapper with severity levels
├── Plugin.php                WordPress lifecycle, IP resolution wiring
├── Resolver.php              Core IP resolution algorithm
├── ResolverResult.php        Resolution result data object
├── ResolverStep.php          Step trace entry data object
├── Scheme.php                Proxy scheme configuration data object
└── Settings.php              Settings data model, persistence, validation
tests/
├── Unit/                     Pure unit tests (no WordPress required)
│   ├── HeaderParserTest.php
│   ├── IpUtilsTest.php
│   ├── LoggerTest.php
│   ├── ResolverTest.php
│   └── SettingsTest.php
└── Integration/              Integration tests (WordPress stubs)
    ├── bootstrap.php         WordPress function stubs
    ├── AdminPageTest.php
    ├── DiagnosticsTest.php
    ├── PluginTest.php
    └── UninstallTest.php
examples/                     Proxy chain configs for local testing
specifications/               Design documents
docs/                         User and developer documentation
```

## Architecture

### Execution Flow

1. WordPress loads `verified-client-ip.php` at `plugins_loaded` priority 0.
2. `Plugin::boot()` creates the singleton and calls `resolveClientIp()`.
3. `Settings::load()` reads configuration from `wp_options`.
4. `Resolver::resolve()` walks the proxy chain using configured schemes.
5. If enabled and the IP changed, `$_SERVER['REMOTE_ADDR']` is replaced.
6. `Diagnostics::maybeRecord()` captures the request (if recording).
7. Admin UI is registered separately when `is_admin()` is true.

### Testing Approach

- **Unit tests** mock `$_SERVER` and test pure logic (Resolver, IpUtils,
  HeaderParser, Settings validation). No WordPress installation needed.
- **Integration tests** use function stubs defined in `bootstrap.php` to
  simulate WordPress functions (get_option, apply_filters, etc.).
- No WP_Mock dependency — stubs are simple `function_exists()` guarded
  functions with `$GLOBALS` storage.

## CI Pipeline

The GitHub Actions workflow runs:

1. `composer install`
2. PHPUnit tests
3. PHPStan static analysis
4. PHP-CS-Fixer formatting check

All four must pass before merging.

## Local Testing with Proxy Chains

See [examples/README.md](../examples/README.md) for instructions on running
WordPress with proxy chains for end-to-end testing.
