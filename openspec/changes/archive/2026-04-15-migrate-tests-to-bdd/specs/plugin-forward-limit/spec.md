## ADDED Requirements

### Requirement: forward_limit setting caps the number of proxy hops traversed
The system SHALL traverse at most `forward_limit` hops from REMOTE_ADDR when resolving the client IP, stopping at the first untrusted address beyond that limit.

#### Scenario: Two-hop limit resolves to the IP beyond the second trusted proxy
- **WHEN** the plugin boots with `forward_limit=2`, an XFF scheme trusted for `10.0.0.0/8` and `192.168.0.0/16`, `REMOTE_ADDR=10.0.0.1`, and `X-Forwarded-For: 203.0.113.50, 192.168.1.1`
- **THEN** `$_SERVER['REMOTE_ADDR']` SHALL be `"203.0.113.50"`
