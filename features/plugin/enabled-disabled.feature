Feature: Plugin enabled and disabled behaviour
  In order to control whether the plugin modifies REMOTE_ADDR
  As a site administrator
  I want the enabled flag to be respected while still computing results for diagnostics

  Scenario: Disabled plugin does not overwrite REMOTE_ADDR
    Given the plugin settings are:
      | key           | value |
      | enabled       | 0     |
      | forward_limit | 1     |
      | process_proto | 1     |
      | process_host  | 0     |
    And the plugin has an XFF scheme trusting "10.0.0.0/8"
    And the server var "REMOTE_ADDR" is "10.0.0.1"
    And the server var "HTTP_X_FORWARDED_FOR" is "203.0.113.50"
    When the plugin boots
    Then the server var "REMOTE_ADDR" should be "10.0.0.1"

  Scenario: Disabled plugin still produces a resolver result
    Given the plugin settings are:
      | key           | value |
      | enabled       | 0     |
      | forward_limit | 1     |
      | process_proto | 1     |
      | process_host  | 0     |
    And the plugin has an XFF scheme trusting "10.0.0.0/8"
    And the server var "REMOTE_ADDR" is "10.0.0.1"
    And the server var "HTTP_X_FORWARDED_FOR" is "203.0.113.50"
    When the plugin boots
    Then the plugin last result should not be null
    And the plugin last result changed should be true
    And the plugin last result resolved_ip should be "203.0.113.50"
