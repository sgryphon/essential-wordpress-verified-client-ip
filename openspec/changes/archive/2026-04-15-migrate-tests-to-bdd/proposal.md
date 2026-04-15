## Why

The integration tests in `tests/Integration/` are written in PHPUnit and test behaviour that belongs in the BDD layer. Moving them to Behat/Gherkin makes the test suite consistent, improves readability, and ensures CI validates all behaviour through the same toolchain.

## What Changes

- Convert all PHPUnit integration tests in `tests/Integration/` to Behat `.feature` files with step-definition contexts, organised by feature area
- Add new Behat suites / contexts for: admin-page form parsing, settings validation, plugin action links, diagnostics state management, diagnostics recording, plugin REMOTE_ADDR replacement, plugin enabled/disabled, proto processing, host processing, WordPress hooks, default schemes, singleton behaviour, forward limit, and uninstall
- Register new contexts in `behat.yml`
- The original PHPUnit integration test files are retained (not deleted) during migration; removal can be a follow-up

## Capabilities

### New Capabilities
- `admin-page-form-parsing`: Parsing raw POST input into structured settings arrays
- `admin-page-settings-validation`: Round-trip parse → validate including invalid proxy detection
- `admin-page-action-links`: Plugin action links rendered in the plugins list table
- `diagnostics-state-management`: Start/stop/clear recording state and max-request clamping
- `diagnostics-recording`: Recording request entries, log limits, null results, step traces, proto info
- `plugin-remote-addr-replacement`: REMOTE_ADDR replaced when upstream is a trusted proxy
- `plugin-enabled-disabled`: Plugin respects the enabled flag while still computing results
- `plugin-proto-processing`: HTTPS/REQUEST_SCHEME updated from forwarded proto header
- `plugin-host-processing`: HTTP_HOST/SERVER_NAME updated from forwarded host header
- `plugin-wordpress-hooks`: `vcip_resolved_ip`, `vcip_trusted_proxies` filters and `vcip_ip_resolved` action
- `plugin-default-schemes`: Default scheme set used when none are configured
- `plugin-singleton`: Plugin::boot() is idempotent / runs only once per request
- `plugin-forward-limit`: Forward-limit setting caps how many hops are traversed
- `uninstall`: Uninstall script removes all options and transients

### Modified Capabilities
- `bdd-gherkin-testing`: behat.yml gains additional suite contexts

## Impact

- `features/` directory gains ~14 new subdirectories and context files
- `behat.yml` updated to register all new contexts
- `tests/Integration/bootstrap.php` WordPress stubs reused by BDD contexts
- No production source code changes
