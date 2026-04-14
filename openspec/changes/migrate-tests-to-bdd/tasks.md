## 1. Shared Infrastructure

- [x] 1.1 Add `AdminPageContext`, `DiagnosticsContext`, `PluginBootContext`, and `UninstallContext` to the `contexts` list in `behat.yml.dist`
- [x] 1.2 Run `composer test:bdd` to confirm the existing IP normalisation scenario still passes with the updated config, then run `composer check` and commit

## 2. Admin Page — Form Input Parsing

- [x] 2.1 Create `features/admin-page/form-input-parsing.feature` with scenarios: basic settings fields parsed, absent checkboxes treated as disabled, two schemes with proxies parsed, no schemes key produces no schemes, empty proxies textarea, blank lines in proxies filtered
- [x] 2.2 Create `features/bootstrap/AdminPageContext.php` implementing all step definitions for the form-parsing feature, loading `tests/Integration/bootstrap.php` via `require_once` and resetting globals in `@BeforeScenario`
- [x] 2.3 Run `composer test:bdd`, then `composer check`, then commit

## 3. Admin Page — Settings Validation

- [ ] 3.1 Create `features/admin-page/settings-validation.feature` with scenarios: full valid input round-trips through parse and validate, one invalid proxy produces errors and retains valid proxies
- [ ] 3.2 Add step definitions for the settings-validation scenarios to `AdminPageContext`
- [ ] 3.3 Run `composer test:bdd`, then `composer check`, then commit

## 4. Admin Page — Action Links

- [ ] 4.1 Create `features/admin-page/action-links.feature` with scenarios: settings and guide appended after existing links, settings URL targets settings page, guide URL targets user-guide tab, labels contain "Settings" and "Guide"
- [ ] 4.2 Add step definitions for the action-links scenarios to `AdminPageContext`
- [ ] 4.3 Run `composer test:bdd`, then `composer check`, then commit

## 5. Diagnostics — State Management

- [ ] 5.1 Create `features/diagnostics/state-management.feature` with scenarios: not recording by default, default state fields, start recording with explicit count, start recording default count, stop recording, clear resets state and log, clamp low, clamp high
- [ ] 5.2 Create `features/bootstrap/DiagnosticsContext.php` implementing all state-management step definitions, loading `tests/Integration/bootstrap.php` and resetting `$GLOBALS['_vcip_test_transients']` in `@BeforeScenario`
- [ ] 5.3 Run `composer test:bdd`, then `composer check`, then commit

## 6. Diagnostics — Recording

- [ ] 6.1 Create `features/diagnostics/recording.feature` with scenarios: no record when not recording, entry structure fields, entry values match server vars, auto-stops at limit, multiple entries below limit, restart clears log, null result partial entry, stop preserves log, step trace in entry, proto info in entry, constants values
- [ ] 6.2 Add step definitions for all recording scenarios to `DiagnosticsContext`
- [ ] 6.3 Run `composer test:bdd`, then `composer check`, then commit

## 7. Plugin Boot — REMOTE_ADDR Replacement

- [ ] 7.1 Create `features/plugin/remote-addr-replacement.feature` with scenarios: REMOTE_ADDR replaced when upstream is trusted proxy, original stored in X-Original header, non-proxy REMOTE_ADDR left unchanged
- [ ] 7.2 Create `features/bootstrap/PluginBootContext.php` implementing step definitions — include `tests/Integration/bootstrap.php`, reset globals and Plugin singleton via reflection in `@BeforeScenario`, restore `$_SERVER` in `@AfterScenario`
- [ ] 7.3 Run `composer test:bdd`, then `composer check`, then commit

## 8. Plugin Boot — Enabled / Disabled

- [ ] 8.1 Create `features/plugin/enabled-disabled.feature` with scenarios: disabled plugin does not overwrite REMOTE_ADDR, disabled plugin still produces a resolver result
- [ ] 8.2 Add step definitions for enabled/disabled scenarios to `PluginBootContext`
- [ ] 8.3 Run `composer test:bdd`, then `composer check`, then commit

## 9. Plugin Boot — Proto Processing

- [ ] 9.1 Create `features/plugin/proto-processing.feature` with scenarios: HTTPS and REQUEST_SCHEME set from Forwarded proto=https, set from X-Forwarded-Proto, originals preserved in X-Original headers, proto processing disabled leaves HTTPS unset
- [ ] 9.2 Add step definitions for proto-processing scenarios to `PluginBootContext`
- [ ] 9.3 Run `composer test:bdd`, then `composer check`, then commit

## 10. Plugin Boot — Host Processing

- [ ] 10.1 Create `features/plugin/host-processing.feature` with scenarios: host processing rewrites HTTP_HOST and SERVER_NAME and preserves original, disabled host processing leaves HTTP_HOST unchanged
- [ ] 10.2 Add step definitions for host-processing scenarios to `PluginBootContext`
- [ ] 10.3 Run `composer test:bdd`, then `composer check`, then commit

## 11. Plugin Boot — WordPress Hooks

- [ ] 11.1 Create `features/plugin/wordpress-hooks.feature` with scenarios: vcip_resolved_ip filter called with resolved IP, vcip_trusted_proxies filter called, vcip_ip_resolved action fired with correct args, action not fired when no change
- [ ] 11.2 Add step definitions for WordPress-hooks scenarios to `PluginBootContext`
- [ ] 11.3 Run `composer test:bdd`, then `composer check`, then commit

## 12. Plugin Boot — Default Schemes

- [ ] 12.1 Create `features/plugin/default-schemes.feature` with scenarios: default schemes resolve REMOTE_ADDR when no schemes configured, default_schemes() returns three schemes in correct order with correct enabled states
- [ ] 12.2 Add step definitions for default-schemes scenarios to `PluginBootContext`
- [ ] 12.3 Run `composer test:bdd`, then `composer check`, then commit

## 13. Plugin Boot — Singleton

- [ ] 13.1 Create `features/plugin/singleton.feature` with scenario: second boot call is a no-op
- [ ] 13.2 Add step definitions for singleton scenario to `PluginBootContext`
- [ ] 13.3 Run `composer test:bdd`, then `composer check`, then commit

## 14. Plugin Boot — Forward Limit

- [ ] 14.1 Create `features/plugin/forward-limit.feature` with scenario: two-hop limit resolves to IP beyond second trusted proxy
- [ ] 14.2 Add step definitions for forward-limit scenario to `PluginBootContext`
- [ ] 14.3 Run `composer test:bdd`, then `composer check`, then commit

## 15. Uninstall

- [ ] 15.1 Create `features/uninstall/uninstall.feature` with scenarios: uninstall removes options and diagnostic transients, vcip_uninstall_site clears options and transients
- [ ] 15.2 Create `features/bootstrap/UninstallContext.php` implementing step definitions — define `WP_UNINSTALL_PLUGIN` guard and register multisite/delete/get_sites/switch_to_blog/restore_current_blog stubs, reset globals in `@BeforeScenario`
- [ ] 15.3 Run `composer test:bdd`, then `composer check`, then commit
