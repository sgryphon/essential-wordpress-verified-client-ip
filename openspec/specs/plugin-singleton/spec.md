## ADDED Requirements

### Requirement: Plugin::boot() is idempotent and only executes once per request lifecycle
The system SHALL ignore subsequent calls to `Plugin::boot()` after the first, leaving `$_SERVER` in the state set by the first boot.

#### Scenario: Second boot call is a no-op
- **WHEN** `Plugin::boot()` is called, REMOTE_ADDR is replaced, then `$_SERVER['REMOTE_ADDR']` and `HTTP_X_FORWARDED_FOR` are changed to different values before a second `Plugin::boot()` call
- **THEN** `$_SERVER['REMOTE_ADDR']` SHALL retain the value set by the second assignment (not re-resolved), confirming the second boot did not run the resolver
