## ADDED Requirements

### Requirement: Default schemes are used when no schemes are configured in settings
The system SHALL fall back to the built-in default scheme set when the settings contain no scheme entries, and SHALL resolve REMOTE_ADDR using those defaults.

#### Scenario: Default schemes resolve REMOTE_ADDR when no schemes are configured
- **WHEN** the plugin boots with settings that have no schemes key and REMOTE_ADDR is in the 10.0.0.0/8 range with an X-Forwarded-For header
- **THEN** `$_SERVER['REMOTE_ADDR']` SHALL be replaced with the IP from the header

### Requirement: Plugin::default_schemes() returns exactly three schemes with correct names and enabled states
The system SHALL return the three built-in schemes — RFC 7239 Forwarded (enabled), X-Forwarded-For (enabled), and Cloudflare (disabled) — from `Plugin::default_schemes()`.

#### Scenario: Default schemes list contains three schemes in correct order
- **WHEN** `Plugin::default_schemes()` is called
- **THEN** the result SHALL contain exactly 3 schemes: index 0 named "RFC 7239 Forwarded" with enabled=true, index 1 named "X-Forwarded-For" with enabled=true, index 2 named "Cloudflare" with enabled=false
