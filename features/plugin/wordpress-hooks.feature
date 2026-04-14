Feature: WordPress hooks
  In order to allow other plugins to interact with IP resolution
  As the plugin
  I want to fire appropriate WordPress filters and actions during boot

  Scenario: vcip_resolved_ip filter is called with the resolved IP
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
    Then the filter "vcip_resolved_ip" should have been applied
    And the filter "vcip_resolved_ip" first argument should be "203.0.113.50"

  Scenario: vcip_trusted_proxies filter is called during boot
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
    Then the filter "vcip_trusted_proxies" should have been applied

  Scenario: vcip_ip_resolved action fires with correct arguments
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
    Then the action "vcip_ip_resolved" should have been fired
    And the action "vcip_ip_resolved" argument 0 should be "203.0.113.50"
    And the action "vcip_ip_resolved" argument 1 should be "10.0.0.1"
    And the action "vcip_ip_resolved" argument 2 should be an array

  Scenario: vcip_ip_resolved action is not fired when no change occurs
    Given the plugin settings are:
      | key           | value |
      | enabled       | 1     |
      | forward_limit | 1     |
      | process_proto | 1     |
      | process_host  | 0     |
    And the plugin has an XFF scheme trusting "10.0.0.0/8"
    And the server var "REMOTE_ADDR" is "203.0.113.50"
    When the plugin boots
    Then the action "vcip_ip_resolved" should not have been fired
