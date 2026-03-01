# Development Guide

## Prerequisites

- **Podman** (preferred) or Docker — for running tests in containers
- **Git** — for version control
- **VS Code** with Dev Containers extension (optional) — for integrated dev environment
- No native PHP or Composer required on the host; everything runs in containers

If you have PHP 8.1+ and Composer installed locally, you can run tests
directly. Otherwise, use the container commands below.

## Getting Started

If you use VS Code with the [Dev Containers extension](https://marketplace.visualstudio.com/items?itemName=ms-vscode-remote.remote-containers), you can open this project in a fully configured development environment:

1. Install the "Dev Containers" extension in VS Code
2. Open the project folder in VS Code
3. Click "Reopen in Container" when prompted (or press `F1` → "Dev Containers: Reopen")

This will start a PHP 8.3 CLI container for development. Use Terminal > New Termninal in VS Code to get a terminal running in the container.

## Install Dependencies

```powershell
composer install
```

## Running Tests

```powershell
composer test
```

### Running Specific Tests

For running a specific subset, file, or test method, pass arguments directly to PHPUnit:

```powershell
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

```powershell
composer analyse
```

### Code Formatting (PHPCS / WPCS)

```powershell
# Check formatting (detect violations)
composer format-check

# Fix formatting automatically
composer format
```

## Full Quality Check

Run all quality checks (formatter, static analysis, tests) in one command:

```powershell
composer check
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
2. `composer test` — PHPUnit tests
3. `composer analyse` — PHPStan static analysis
4. `composer format-check` — PHPCS (WordPress Coding Standards) formatting check

All four must pass before merging.

## Local Testing with Proxy Chains

For full end-to-end testing with WordPress and proxy chains, see the examples directory:

- [examples/README.md](../examples/README.md) — setup instructions for local proxy chain testing
