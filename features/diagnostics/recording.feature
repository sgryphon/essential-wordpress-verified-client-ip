Feature: Diagnostics recording
  In order to capture request data for diagnostic analysis
  As the diagnostics subsystem
  I want to record request entries with server vars, resolver results, and step traces

  Scenario: Record call while not recording leaves log empty
    When a diagnostic recording is attempted
    Then the diagnostics log should be empty

  Scenario: Entry structure contains all expected fields
    Given diagnostics recording is started with count 5
    When a diagnostic entry is recorded
    Then the diagnostics log should have 1 entry
    And the diagnostics log entry 0 should have key "timestamp"
    And the diagnostics log entry 0 should have key "request_uri"
    And the diagnostics log entry 0 should have key "method"
    And the diagnostics log entry 0 should have key "remote_addr"
    And the diagnostics log entry 0 should have key "headers"
    And the diagnostics log entry 0 should have key "resolved_ip"
    And the diagnostics log entry 0 should have key "original_ip"
    And the diagnostics log entry 0 should have key "changed"
    And the diagnostics log entry 0 should have key "steps"

  Scenario: Entry values match the supplied server vars and result
    Given diagnostics recording is started with count 3
    And the diagnostic server vars are:
      | key                    | value         |
      | REMOTE_ADDR            | 10.0.0.1      |
      | REQUEST_URI            | /test-page    |
      | REQUEST_METHOD         | POST          |
      | HTTP_HOST              | example.com   |
      | HTTP_X_FORWARDED_FOR   | 203.0.113.50  |
      | SERVER_PORT            | 443           |
    When a diagnostic entry is recorded with result "203.0.113.50" original "10.0.0.1" changed true
    Then the diagnostics log entry 0 field "request_uri" should be "/test-page"
    And the diagnostics log entry 0 field "method" should be "POST"
    And the diagnostics log entry 0 field "remote_addr" should be "10.0.0.1"
    And the diagnostics log entry 0 field "resolved_ip" should be "203.0.113.50"
    And the diagnostics log entry 0 field "original_ip" should be "10.0.0.1"
    And the diagnostics log entry 0 changed field should be true
    And the diagnostics log entry 0 header "HTTP_HOST" should be "example.com"
    And the diagnostics log entry 0 header "HTTP_X_FORWARDED_FOR" should be "203.0.113.50"

  Scenario: Recording stops at the configured limit
    Given diagnostics recording is started with count 3
    And 5 diagnostic entries are recorded
    Then the diagnostics log should have 3 entries
    And diagnostics should not be recording

  Scenario: Multiple entries below limit are all stored
    Given diagnostics recording is started with count 10
    And 4 diagnostic entries are recorded
    Then the diagnostics log should have 4 entries
    And diagnostics should be recording

  Scenario: Second start_recording clears prior entries
    Given diagnostics recording is started with count 10
    And 2 diagnostic entries are recorded
    When diagnostics recording is started with count 5
    Then the diagnostics log should be empty
    And diagnostics should be recording

  Scenario: Null result produces entry without resolved_ip or steps
    Given diagnostics recording is started with count 3
    When a diagnostic entry is recorded with null result
    Then the diagnostics log entry 0 should have key "timestamp"
    And the diagnostics log entry 0 should have key "remote_addr"
    And the diagnostics log entry 0 should not have key "resolved_ip"
    And the diagnostics log entry 0 should not have key "steps"

  Scenario: Stop recording does not discard log
    Given diagnostics recording is started with count 10
    And 2 diagnostic entries are recorded
    When diagnostics recording is stopped
    Then the diagnostics log should have 2 entries

  Scenario: Entry steps reflect the resolver step trace
    Given diagnostics recording is started with count 3
    When a diagnostic entry is recorded with a two-step trace
    Then the diagnostics log entry 0 should have 2 steps
    And the diagnostics log entry 0 step 0 field "step" should be 1
    And the diagnostics log entry 0 step 0 field "address" should be "10.0.0.1"
    And the diagnostics log entry 0 step 0 field "action" should be "trusted_proxy"

  Scenario: Entry proto field reflects result proto map
    Given diagnostics recording is started with count 3
    When a diagnostic entry is recorded with proto "https" and host "example.com"
    Then the diagnostics log entry 0 proto field "proto" should be "https"
    And the diagnostics log entry 0 proto field "host" should be "example.com"

  Scenario: Constants match expected values
    Then the diagnostics DEFAULT_REQUEST_COUNT should be 10
    And the diagnostics MAX_REQUEST_COUNT should be 100
    And the diagnostics EXPIRY_SECONDS should be 86400
