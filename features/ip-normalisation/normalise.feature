Feature: IP address normalisation
  In order to reliably match proxy addresses regardless of format
  As the plugin's IP resolution algorithm
  I want IPv4-mapped IPv6 addresses to be normalised to their IPv4 form

  Scenario: IPv4-mapped IPv6 address normalises to IPv4
    Given an IP address of "::ffff:192.168.1.1"
    When the address is normalised
    Then the result should be "192.168.1.1"
