Feature: Proto processing
  In order to correctly detect HTTPS connections behind reverse proxies
  As the plugin
  I want to update HTTPS and REQUEST_SCHEME from forwarded proto headers

  Scenario: Proto https from Forwarded header sets HTTPS and REQUEST_SCHEME
    Given the plugin settings are:
      | key           | value |
      | enabled       | 1     |
      | forward_limit | 1     |
      | process_proto | 1     |
      | process_host  | 0     |
    And the plugin has a Forwarded scheme trusting "10.0.0.0/8"
    And the server var "REMOTE_ADDR" is "10.0.0.1"
    And the server var "HTTP_FORWARDED" is "for=203.0.113.50;proto=https"
    When the plugin boots
    Then the server var "HTTPS" should be "on"
    And the server var "REQUEST_SCHEME" should be "https"

  Scenario: Proto https from X-Forwarded-Proto header sets HTTPS and REQUEST_SCHEME
    Given the plugin settings are:
      | key           | value |
      | enabled       | 1     |
      | forward_limit | 1     |
      | process_proto | 1     |
      | process_host  | 0     |
    And the plugin has an XFF scheme trusting "10.0.0.0/8"
    And the server var "REMOTE_ADDR" is "10.0.0.1"
    And the server var "HTTP_X_FORWARDED_FOR" is "203.0.113.50"
    And the server var "HTTP_X_FORWARDED_PROTO" is "https"
    When the plugin boots
    Then the server var "HTTPS" should be "on"
    And the server var "REQUEST_SCHEME" should be "https"

  Scenario: Original proto server vars are preserved in X-Original headers
    Given the plugin settings are:
      | key           | value |
      | enabled       | 1     |
      | forward_limit | 1     |
      | process_proto | 1     |
      | process_host  | 0     |
    And the plugin has a Forwarded scheme trusting "10.0.0.0/8"
    And the server var "REMOTE_ADDR" is "10.0.0.1"
    And the server var "HTTP_FORWARDED" is "for=203.0.113.50;proto=https"
    And the server var "HTTPS" is "off"
    And the server var "REQUEST_SCHEME" is "http"
    When the plugin boots
    Then the server var "HTTP_X_ORIGINAL_HTTPS" should be "off"
    And the server var "HTTP_X_ORIGINAL_REQUEST_SCHEME" should be "http"

  Scenario: Disabled proto processing leaves HTTPS unset
    Given the plugin settings are:
      | key           | value |
      | enabled       | 1     |
      | forward_limit | 1     |
      | process_proto | 0     |
      | process_host  | 0     |
    And the plugin has a Forwarded scheme trusting "10.0.0.0/8"
    And the server var "REMOTE_ADDR" is "10.0.0.1"
    And the server var "HTTP_FORWARDED" is "for=203.0.113.50;proto=https"
    When the plugin boots
    Then the server var "REMOTE_ADDR" should be "203.0.113.50"
    And the server var "HTTPS" should not be set
