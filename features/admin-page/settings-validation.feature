Feature: Admin page settings validation
  In order to safely save validated settings
  As the admin page form handler
  I want parsed form input to be validated and produce a Settings object or errors

  Scenario: Full valid input round-trips through parse and validate
    Given the admin form POST contains:
      | field              | value |
      | vcip_enabled       | 1     |
      | vcip_forward_limit | 2     |
      | vcip_process_proto | 1     |
      | vcip_process_host  | 1     |
    And the admin form POST contains schemes:
      | name     | enabled | header          | token | proxies                    | notes         |
      | My Proxy | 1       | X-Forwarded-For |       | 10.0.0.0/8\n192.168.0.0/16 | Custom config |
    When the admin form input is parsed and validated
    Then the validation result should have no errors
    And the validated settings enabled should be true
    And the validated settings forward_limit should be 2
    And the validated settings process_proto should be true
    And the validated settings process_host should be true
    And the validated settings should have 1 scheme
    And the validated scheme 0 should have name "My Proxy"
    And the validated scheme 0 should have header "X-Forwarded-For"
    And the validated scheme 0 should have a null token
    And the validated scheme 0 should have 2 proxies

  Scenario: One invalid proxy produces errors and retains valid proxies
    Given the admin form POST contains schemes:
      | name | enabled | header | proxies                            |
      | Bad  | 1       | X-Test | 10.0.0.0/8\nnot-an-ip\n192.168.1.1 |
    When the admin form input is parsed and validated
    Then the validation result should have errors
    And the validated scheme 0 should have 2 proxies
