Feature: Host processing
  In order to correctly detect the original hostname behind reverse proxies
  As the plugin
  I want to update HTTP_HOST and SERVER_NAME from forwarded host headers

  Scenario: Host processing enabled rewrites HTTP_HOST and SERVER_NAME
    Given the plugin settings are:
      | key           | value |
      | enabled       | 1     |
      | forward_limit | 1     |
      | process_proto | 1     |
      | process_host  | 1     |
    And the plugin has a Forwarded scheme trusting "10.0.0.0/8"
    And the server var "REMOTE_ADDR" is "10.0.0.1"
    And the server var "HTTP_FORWARDED" is "for=203.0.113.50;host=example.com"
    And the server var "HTTP_HOST" is "internal.local"
    When the plugin boots
    Then the server var "HTTP_HOST" should be "example.com"
    And the server var "SERVER_NAME" should be "example.com"
    And the server var "HTTP_X_ORIGINAL_HOST" should be "internal.local"

  Scenario: Host processing disabled leaves HTTP_HOST unchanged
    Given the plugin settings are:
      | key           | value |
      | enabled       | 1     |
      | forward_limit | 1     |
      | process_proto | 1     |
      | process_host  | 0     |
    And the plugin has a Forwarded scheme trusting "10.0.0.0/8"
    And the server var "REMOTE_ADDR" is "10.0.0.1"
    And the server var "HTTP_FORWARDED" is "for=203.0.113.50;host=example.com"
    And the server var "HTTP_HOST" is "internal.local"
    When the plugin boots
    Then the server var "HTTP_HOST" should be "internal.local"
