## ADDED Requirements

### Requirement: Diagnostics is not recording by default
The system SHALL report `is_recording() = false` before any recording session has been started.

#### Scenario: Default recording state is off
- **WHEN** `Diagnostics::is_recording()` is called without any prior `start_recording()` call
- **THEN** the result SHALL be false

#### Scenario: Default state fields are initialised correctly
- **WHEN** `Diagnostics::get_state()` is called without any prior `start_recording()` call
- **THEN** the result SHALL have `recording=false`, `max_requests=10`, `started_at=null`, `stopped_at=null`

### Requirement: Recording can be started with a custom or default request count
The system SHALL begin a recording session when `start_recording()` is called, setting `is_recording()` to true, recording `started_at`, and using the supplied count (or DEFAULT_REQUEST_COUNT when omitted).

#### Scenario: Start recording with explicit count
- **WHEN** `Diagnostics::start_recording(5)` is called
- **THEN** `is_recording()` SHALL be true, `state["max_requests"]` SHALL be 5, `state["started_at"]` SHALL be non-null, and `state["stopped_at"]` SHALL be null

#### Scenario: Start recording with no argument uses default count
- **WHEN** `Diagnostics::start_recording()` is called with no argument
- **THEN** `state["max_requests"]` SHALL equal `Diagnostics::DEFAULT_REQUEST_COUNT`

### Requirement: Recording can be stopped, preserving stopped_at timestamp
The system SHALL end the active recording session when `stop_recording()` is called and SHALL set `stopped_at` to a non-null value.

#### Scenario: Stop recording marks session as stopped
- **WHEN** `Diagnostics::start_recording(5)` is called and then `Diagnostics::stop_recording()` is called
- **THEN** `is_recording()` SHALL be false, `state["recording"]` SHALL be false, and `state["stopped_at"]` SHALL be non-null

### Requirement: Clear resets recording state and empties the log
The system SHALL stop recording and discard all log entries when `Diagnostics::clear()` is called.

#### Scenario: Clear after recording removes log entries and stops recording
- **WHEN** a recording session is started, one entry is recorded, and then `Diagnostics::clear()` is called
- **THEN** `is_recording()` SHALL be false and `get_log()` SHALL return an empty array

### Requirement: Max request count is clamped to valid bounds
The system SHALL clamp the requested count to a minimum of 1 and a maximum of MAX_REQUEST_COUNT.

#### Scenario: Count below minimum is clamped to 1
- **WHEN** `Diagnostics::start_recording(0)` is called
- **THEN** `state["max_requests"]` SHALL be 1

#### Scenario: Count above maximum is clamped to MAX_REQUEST_COUNT
- **WHEN** `Diagnostics::start_recording(999)` is called
- **THEN** `state["max_requests"]` SHALL equal `Diagnostics::MAX_REQUEST_COUNT`
