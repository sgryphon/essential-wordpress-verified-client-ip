## ADDED Requirements

### Requirement: HTTP_HOST and SERVER_NAME are updated from the forwarded host value
The system SHALL overwrite `$_SERVER['HTTP_HOST']` and `$_SERVER['SERVER_NAME']` with the host from the forwarding header when `process_host=true`, and SHALL store the original value in `HTTP_X_ORIGINAL_HOST`.

#### Scenario: Host processing enabled rewrites HTTP_HOST and SERVER_NAME
- **WHEN** the plugin boots with `process_host=true`, a Forwarded header containing `host=example.com`, and the original `HTTP_HOST` is `internal.local`
- **THEN** `$_SERVER['HTTP_HOST']` SHALL be `"example.com"`, `$_SERVER['SERVER_NAME']` SHALL be `"example.com"`, and `$_SERVER['HTTP_X_ORIGINAL_HOST']` SHALL be `"internal.local"`

### Requirement: HTTP_HOST is not modified when host processing is disabled
The system SHALL leave `$_SERVER['HTTP_HOST']` unchanged when `process_host=false` (the default).

#### Scenario: Host processing disabled leaves HTTP_HOST unchanged
- **WHEN** the plugin boots with `process_host=false` (default), a Forwarded header containing `host=example.com`, and the original `HTTP_HOST` is `internal.local`
- **THEN** `$_SERVER['HTTP_HOST']` SHALL remain `"internal.local"`
