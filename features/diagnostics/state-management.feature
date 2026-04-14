Feature: Diagnostics state management
  In order to control diagnostic recording sessions
  As the diagnostics subsystem
  I want to manage recording state with start, stop, and clear operations

  Scenario: Default recording state is off
    When diagnostics recording state is checked
    Then diagnostics should not be recording

  Scenario: Default state fields are initialised correctly
    When the diagnostics state is retrieved
    Then the diagnostics state recording should be false
    And the diagnostics state max_requests should be 10
    And the diagnostics state started_at should be null
    And the diagnostics state stopped_at should be null

  Scenario: Start recording with explicit count
    When diagnostics recording is started with count 5
    Then diagnostics should be recording
    And the diagnostics state max_requests should be 5
    And the diagnostics state started_at should not be null
    And the diagnostics state stopped_at should be null

  Scenario: Start recording with no argument uses default count
    When diagnostics recording is started with default count
    Then the diagnostics state max_requests should be the default request count

  Scenario: Stop recording marks session as stopped
    Given diagnostics recording is started with count 5
    When diagnostics recording is stopped
    Then diagnostics should not be recording
    And the diagnostics state recording should be false
    And the diagnostics state stopped_at should not be null

  Scenario: Clear after recording removes log entries and stops recording
    Given diagnostics recording is started with count 3
    And a diagnostic entry is recorded
    When diagnostics is cleared
    Then diagnostics should not be recording
    And the diagnostics log should be empty

  Scenario: Count below minimum is clamped to 1
    When diagnostics recording is started with count 0
    Then the diagnostics state max_requests should be 1

  Scenario: Count above maximum is clamped to MAX_REQUEST_COUNT
    When diagnostics recording is started with count 999
    Then the diagnostics state max_requests should be the max request count
