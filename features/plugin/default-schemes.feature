Feature: Default schemes
  In order to work out of the box with common proxy configurations
  As the plugin
  I want to provide sensible default forwarding schemes when none are configured

  Scenario: Default schemes resolve REMOTE_ADDR when no schemes are configured
    Given the plugin settings are:
      | key           | value |
      | enabled       | 1     |
      | forward_limit | 1     |
      | process_proto | 1     |
      | process_host  | 0     |
    And the server var "REMOTE_ADDR" is "10.0.0.1"
    And the server var "HTTP_X_FORWARDED_FOR" is "203.0.113.50"
    When the plugin boots
    Then the server var "REMOTE_ADDR" should be "203.0.113.50"

  Scenario: Default schemes list contains three schemes in correct order
    Then the default schemes should contain 3 entries
    And the default scheme 0 name should be "RFC 7239 Forwarded"
    And the default scheme 0 should be enabled
    And the default scheme 1 name should be "X-Forwarded-For"
    And the default scheme 1 should be enabled
    And the default scheme 2 name should be "Cloudflare"
    And the default scheme 2 should be disabled
