Feature: Admin page form input parsing
  In order to correctly save plugin settings from the admin form
  As the admin page form handler
  I want POST input to be parsed into a structured settings array

  Scenario: Basic settings fields are parsed
    Given the admin form POST contains:
      | field              | value |
      | vcip_enabled       | 1     |
      | vcip_forward_limit | 3     |
      | vcip_process_proto | 1     |
      | vcip_process_host  | 0     |
    When the admin form input is parsed
    Then the parsed boolean "enabled" should be true
    And the parsed setting "forward_limit" should be "3"
    And the parsed boolean "process_proto" should be true
    And the parsed boolean "process_host" should be false

  Scenario: Absent checkboxes are treated as disabled
    Given the admin form POST contains:
      | field              | value |
      | vcip_forward_limit | 1     |
    When the admin form input is parsed
    Then the parsed boolean "enabled" should be false
    And the parsed boolean "process_proto" should be false
    And the parsed boolean "process_host" should be false

  Scenario: Two schemes with proxies are parsed
    Given the admin form POST contains:
      | field              | value |
      | vcip_enabled       | 1     |
      | vcip_forward_limit | 1     |
    And the admin form POST contains schemes:
      | name      | enabled | header          | token | proxies                      | notes       |
      | XFF       | 1       | X-Forwarded-For |       | 10.0.0.0/8\n192.168.0.0/16   | Test scheme |
      | Forwarded | 0       | Forwarded       | for   | 172.16.0.0/12                |             |
    When the admin form input is parsed
    Then the parsed result should contain 2 schemes
    And parsed scheme 0 should have name "XFF"
    And parsed scheme 0 should be enabled
    And parsed scheme 0 should have header "X-Forwarded-For"
    And parsed scheme 0 should have token ""
    And parsed scheme 0 should have 2 proxies
    And parsed scheme 0 proxy 0 should be "10.0.0.0/8"
    And parsed scheme 0 proxy 1 should be "192.168.0.0/16"
    And parsed scheme 1 should have name "Forwarded"
    And parsed scheme 1 should be disabled
    And parsed scheme 1 should have token "for"

  Scenario: No schemes key produces no schemes
    Given the admin form POST contains:
      | field              | value |
      | vcip_enabled       | 1     |
      | vcip_forward_limit | 1     |
    When the admin form input is parsed
    Then the parsed result should not contain a "schemes" key

  Scenario: Empty proxies textarea produces empty proxies array
    Given the admin form POST contains:
      | field              | value |
      | vcip_enabled       | 1     |
      | vcip_forward_limit | 1     |
    And the admin form POST contains schemes:
      | name | header | proxies |
      | Test | X-Test |         |
    When the admin form input is parsed
    Then parsed scheme 0 should have 0 proxies

  Scenario: Blank lines in proxies textarea are filtered out
    Given the admin form POST contains schemes:
      | name | header | proxies                        |
      | Test | X-Test | 10.0.0.0/8\n\n\n192.168.1.1\n  |
    When the admin form input is parsed
    Then parsed scheme 0 should have 2 proxies
