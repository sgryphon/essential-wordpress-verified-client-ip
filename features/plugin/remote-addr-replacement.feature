Feature: REMOTE_ADDR replacement
  In order to expose the true client IP to WordPress and other plugins
  As the plugin
  I want to replace REMOTE_ADDR with the verified client IP from trusted proxy headers

  Scenario: REMOTE_ADDR is replaced when upstream is a trusted proxy
    Given the plugin settings are:
      | key           | value |
      | enabled       | 1     |
      | forward_limit | 1     |
      | process_proto | 1     |
      | process_host  | 0     |
    And the plugin has an XFF scheme trusting "10.0.0.0/8"
    And the server var "REMOTE_ADDR" is "10.0.0.1"
    And the server var "HTTP_X_FORWARDED_FOR" is "203.0.113.50"
    When the plugin boots
    Then the server var "REMOTE_ADDR" should be "203.0.113.50"

  Scenario: Original REMOTE_ADDR is preserved in X-Original header
    Given the plugin settings are:
      | key           | value |
      | enabled       | 1     |
      | forward_limit | 1     |
      | process_proto | 1     |
      | process_host  | 0     |
    And the plugin has an XFF scheme trusting "10.0.0.0/8"
    And the server var "REMOTE_ADDR" is "10.0.0.1"
    And the server var "HTTP_X_FORWARDED_FOR" is "203.0.113.50"
    When the plugin boots
    Then the server var "HTTP_X_ORIGINAL_REMOTE_ADDR" should be "10.0.0.1"

  Scenario: Non-proxy REMOTE_ADDR is left unchanged
    Given the plugin settings are:
      | key           | value |
      | enabled       | 1     |
      | forward_limit | 1     |
      | process_proto | 1     |
      | process_host  | 0     |
    And the plugin has an XFF scheme trusting "10.0.0.0/8"
    And the server var "REMOTE_ADDR" is "203.0.113.50"
    And the server var "HTTP_X_FORWARDED_FOR" is "1.2.3.4"
    When the plugin boots
    Then the server var "REMOTE_ADDR" should be "203.0.113.50"
    And the server var "HTTP_X_ORIGINAL_REMOTE_ADDR" should not be set
