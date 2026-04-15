## ADDED Requirements

### Requirement: HTTPS and REQUEST_SCHEME are updated from the forwarded proto value
The system SHALL set `$_SERVER['HTTPS']` to `"on"` and `$_SERVER['REQUEST_SCHEME']` to `"https"` when `process_proto=true` and the resolved forwarding header contains `proto=https`.

#### Scenario: Proto https from Forwarded header sets HTTPS and REQUEST_SCHEME
- **WHEN** the plugin boots with `process_proto=true`, a Forwarded scheme, and `HTTP_FORWARDED: for=203.0.113.50;proto=https`
- **THEN** `$_SERVER['HTTPS']` SHALL be `"on"` and `$_SERVER['REQUEST_SCHEME']` SHALL be `"https"`

#### Scenario: Proto https from X-Forwarded-Proto header sets HTTPS and REQUEST_SCHEME
- **WHEN** the plugin boots with `process_proto=true`, an XFF scheme, `HTTP_X_FORWARDED_FOR: 203.0.113.50`, and `HTTP_X_FORWARDED_PROTO: https`
- **THEN** `$_SERVER['HTTPS']` SHALL be `"on"` and `$_SERVER['REQUEST_SCHEME']` SHALL be `"https"`

### Requirement: Original HTTPS and REQUEST_SCHEME values are preserved before proto processing
The system SHALL store the pre-existing `HTTPS` and `REQUEST_SCHEME` values in X-Original headers before overwriting them.

#### Scenario: Original proto server vars are preserved in X-Original headers
- **WHEN** the plugin boots with `process_proto=true`, `$_SERVER['HTTPS']="off"`, `$_SERVER['REQUEST_SCHEME']="http"`, and a Forwarded header with `proto=https`
- **THEN** `$_SERVER['HTTP_X_ORIGINAL_HTTPS']` SHALL be `"off"` and `$_SERVER['HTTP_X_ORIGINAL_REQUEST_SCHEME']` SHALL be `"http"`

### Requirement: When proto processing is disabled, HTTPS is left untouched
The system SHALL NOT modify `$_SERVER['HTTPS']` or `$_SERVER['REQUEST_SCHEME']` when `process_proto=false`.

#### Scenario: Disabled proto processing leaves HTTPS unset
- **WHEN** the plugin boots with `process_proto=false` and a Forwarded header containing `proto=https`
- **THEN** REMOTE_ADDR SHALL be replaced and `$_SERVER['HTTPS']` SHALL NOT be set
