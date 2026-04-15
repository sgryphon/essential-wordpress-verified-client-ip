## ADDED Requirements

### Requirement: maybe_record does nothing when not recording
The system SHALL ignore calls to `maybe_record()` when no active recording session exists.

#### Scenario: Record call while not recording leaves log empty
- **WHEN** `Diagnostics::maybe_record()` is called without a prior `start_recording()` call
- **THEN** `get_log()` SHALL return an empty array

### Requirement: Recorded entries contain required fields derived from server vars and result
The system SHALL store a log entry with timestamp, request_uri, method, remote_addr, headers, resolved_ip, original_ip, changed, and steps fields when recording is active.

#### Scenario: Entry structure contains all expected fields
- **WHEN** recording is active and `maybe_record()` is called with standard server vars and a result
- **THEN** the log SHALL contain exactly 1 entry with keys: timestamp, request_uri, method, remote_addr, headers, resolved_ip, original_ip, changed, steps

#### Scenario: Entry values match the supplied server vars and result
- **WHEN** recording is active and `maybe_record()` is called with `REQUEST_URI=/test-page`, `REQUEST_METHOD=POST`, `REMOTE_ADDR=10.0.0.1`, `HTTP_X_FORWARDED_FOR=203.0.113.50`, and a result with resolved_ip=203.0.113.50, original_ip=10.0.0.1, changed=true
- **THEN** the entry SHALL have `request_uri="/test-page"`, `method="POST"`, `remote_addr="10.0.0.1"`, `resolved_ip="203.0.113.50"`, `original_ip="10.0.0.1"`, `changed=true`, and headers SHALL include `HTTP_HOST` and `HTTP_X_FORWARDED_FOR`

### Requirement: Recording automatically stops when the max request count is reached
The system SHALL stop recording after exactly max_requests entries have been stored, even if more calls are made.

#### Scenario: Recording stops at the configured limit
- **WHEN** `start_recording(3)` is called and `maybe_record()` is called 5 times
- **THEN** `get_log()` SHALL contain exactly 3 entries and `is_recording()` SHALL be false

### Requirement: Multiple entries can be recorded within the limit
The system SHALL accumulate multiple entries in the log without stopping while below the limit.

#### Scenario: Multiple entries below limit are all stored
- **WHEN** `start_recording(10)` is called and `maybe_record()` is called 4 times
- **THEN** `get_log()` SHALL contain exactly 4 entries and `is_recording()` SHALL be true

### Requirement: Starting a new recording session clears the previous log
The system SHALL discard all previously recorded entries when `start_recording()` is called again.

#### Scenario: Second start_recording clears prior entries
- **WHEN** 2 entries are recorded, then `start_recording(5)` is called again
- **THEN** `get_log()` SHALL be empty and `is_recording()` SHALL be true

### Requirement: maybe_record with a null result stores a partial entry
The system SHALL record a log entry with basic request fields but without resolved_ip or steps when the result is null.

#### Scenario: Null result produces entry without resolved_ip or steps
- **WHEN** recording is active and `maybe_record()` is called with a null result
- **THEN** the entry SHALL have `timestamp` and `remote_addr` keys but SHALL NOT have `resolved_ip` or `steps` keys

### Requirement: Stopping recording preserves existing log entries
The system SHALL retain all previously recorded entries after `stop_recording()` is called.

#### Scenario: Stop recording does not discard log
- **WHEN** 2 entries are recorded and then `stop_recording()` is called
- **THEN** `get_log()` SHALL still contain exactly 2 entries

### Requirement: Recorded entry contains the resolver step trace
The system SHALL serialise the steps array from the ResolverResult into the log entry, preserving step number, address, and action for each step.

#### Scenario: Entry steps reflect the resolver step trace
- **WHEN** a result with 2 ResolverStep objects is recorded
- **THEN** the entry SHALL have exactly 2 steps, the first with `step=1`, `address="10.0.0.1"`, `action="trusted_proxy"`

### Requirement: Recorded entry contains proto and host info from the result
The system SHALL store the proto map from the ResolverResult in the log entry under the `proto` key.

#### Scenario: Entry proto field reflects result proto map
- **WHEN** a result with `proto=["proto"=>"https","host"=>"example.com"]` is recorded
- **THEN** the entry SHALL have a `proto` key equal to `["proto"=>"https","host"=>"example.com"]`

### Requirement: Diagnostics constants have correct values
The system SHALL expose `DEFAULT_REQUEST_COUNT=10`, `MAX_REQUEST_COUNT=100`, and `EXPIRY_SECONDS=86400` as class constants.

#### Scenario: Constants match expected values
- **WHEN** the Diagnostics class constants are read
- **THEN** `DEFAULT_REQUEST_COUNT` SHALL be 10, `MAX_REQUEST_COUNT` SHALL be 100, and `EXPIRY_SECONDS` SHALL be 86400
