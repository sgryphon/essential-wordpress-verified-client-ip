## ADDED Requirements

### Requirement: REMOTE_ADDR is replaced with the client IP from a trusted proxy header
The system SHALL overwrite `$_SERVER['REMOTE_ADDR']` with the IP address extracted from the forwarding header when the connecting address matches a trusted proxy.

#### Scenario: REMOTE_ADDR is replaced when upstream is a trusted proxy
- **WHEN** the plugin boots with XFF scheme, REMOTE_ADDR is a trusted proxy IP, and X-Forwarded-For contains a client IP
- **THEN** `$_SERVER['REMOTE_ADDR']` SHALL equal the client IP from the header

#### Scenario: Original REMOTE_ADDR is preserved in X-Original-Remote-Addr header
- **WHEN** the plugin boots and REMOTE_ADDR is replaced
- **THEN** `$_SERVER['HTTP_X_ORIGINAL_REMOTE_ADDR']` SHALL contain the original REMOTE_ADDR value

### Requirement: REMOTE_ADDR is not changed when the connecting address is not a trusted proxy
The system SHALL leave `$_SERVER['REMOTE_ADDR']` unchanged and SHALL NOT set an X-Original header when the connecting address is not in the trusted proxy list.

#### Scenario: Non-proxy REMOTE_ADDR is left unchanged
- **WHEN** the plugin boots with an XFF scheme and REMOTE_ADDR is not in the trusted proxy CIDR
- **THEN** `$_SERVER['REMOTE_ADDR']` SHALL remain unchanged and `$_SERVER['HTTP_X_ORIGINAL_REMOTE_ADDR']` SHALL NOT be set
