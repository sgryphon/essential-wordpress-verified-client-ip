## ADDED Requirements

### Requirement: When the plugin is disabled, REMOTE_ADDR is not modified
The system SHALL leave `$_SERVER['REMOTE_ADDR']` unchanged when the `enabled` setting is false, even when a trusted proxy header is present.

#### Scenario: Disabled plugin does not overwrite REMOTE_ADDR
- **WHEN** the plugin boots with `enabled=false`, REMOTE_ADDR is a trusted proxy IP, and a forwarding header is present
- **THEN** `$_SERVER['REMOTE_ADDR']` SHALL retain its original value

### Requirement: When the plugin is disabled, the resolver result is still computed and available
The system SHALL compute the resolved IP and store it on the Plugin instance even when `enabled=false`, so diagnostics can access it.

#### Scenario: Disabled plugin still produces a resolver result
- **WHEN** the plugin boots with `enabled=false`, REMOTE_ADDR is a trusted proxy IP, and a forwarding header is present
- **THEN** `Plugin::instance()->last_result()` SHALL be non-null, `last_result()->changed` SHALL be true, and `last_result()->resolved_ip` SHALL equal the client IP from the header
