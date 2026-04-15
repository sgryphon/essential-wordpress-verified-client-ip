Feature: Forward limit
  In order to prevent attackers from injecting spoofed IPs deep in the header chain
  As the plugin
  I want the forward_limit setting to cap how many proxy hops are traversed

  Scenario: Two-hop limit resolves to the IP beyond the second trusted proxy
    Given the plugin settings are:
      | key           | value |
      | enabled       | 1     |
      | forward_limit | 2     |
      | process_proto | 1     |
      | process_host  | 0     |
    And the plugin has an XFF scheme trusting "10.0.0.0/8" and "192.168.0.0/16"
    And the server var "REMOTE_ADDR" is "10.0.0.1"
    And the server var "HTTP_X_FORWARDED_FOR" is "203.0.113.50, 192.168.1.1"
    When the plugin boots
    Then the server var "REMOTE_ADDR" should be "203.0.113.50"
