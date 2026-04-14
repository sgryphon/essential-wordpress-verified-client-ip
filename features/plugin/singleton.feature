Feature: Plugin singleton behaviour
  In order to prevent double-processing of REMOTE_ADDR
  As the plugin
  I want boot to only execute once per request lifecycle

  Scenario: Second boot call is a no-op
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
    And the server var "REMOTE_ADDR" is changed to "10.0.0.2"
    And the server var "HTTP_X_FORWARDED_FOR" is changed to "198.51.100.1"
    And the plugin boots again
    Then the server var "REMOTE_ADDR" should be "10.0.0.2"
