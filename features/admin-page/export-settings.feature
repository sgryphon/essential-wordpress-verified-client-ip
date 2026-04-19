Feature: Export settings as JSON
  In order to back up or transfer plugin configuration
  As a WordPress administrator
  I want to export current settings as a human-readable JSON file

  Scenario: Default settings produce correct export structure
    Given the plugin has default settings
    When the settings are exported with site URL "https://example.com" and timestamp "2026-04-19T14:30:45+00:00"
    Then the export should contain metadata key "exported_from" with value "https://example.com"
    And the export should contain metadata key "exported_at" with value "2026-04-19T14:30:45+00:00"
    And the export general key "enabled" should be true
    And the export general key "forward_limit" should be integer 1
    And the export general key "process_proto" should be true
    And the export general key "process_host" should be false
    And the export should contain 3 schemes

  Scenario: Each scheme has top-level data and a forwarding_scheme object
    Given the plugin has default settings
    When the settings are exported with site URL "https://example.com" and timestamp "2026-04-19T14:30:45+00:00"
    Then export scheme 0 should have name "RFC 7239 Forwarded"
    And export scheme 0 should be enabled
    And export scheme 0 should have a "forwarding_scheme" object
    And export scheme 0 forwarding_scheme key "header" should be "Forwarded"
    And export scheme 0 forwarding_scheme key "token" should be "for"
    And export scheme 0 forwarding_scheme key "notes" should be "Standard RFC 7239 Forwarded header using the 'for' token."
    And export scheme 0 forwarding_scheme should have proxies

  Scenario: Disabled scheme is exported correctly
    Given the plugin has default settings
    When the settings are exported with site URL "https://example.com" and timestamp "2026-04-19T14:30:45+00:00"
    Then export scheme 2 should have name "Cloudflare"
    And export scheme 2 should be disabled
    And export scheme 2 forwarding_scheme key "header" should be "CF-Connecting-IP"
    And export scheme 2 forwarding_scheme "token" should be null

  Scenario: Custom settings round-trip through export
    Given settings with enabled false, forward_limit 5, process_proto false, process_host true
    And a single scheme named "Custom" enabled with header "X-Real-IP" and no token and proxies "10.0.0.0/8" and notes "test"
    When the settings are exported with site URL "https://mysite.org" and timestamp "2026-01-15T08:00:00+00:00"
    Then the export general key "enabled" should be false
    And the export general key "forward_limit" should be integer 5
    And the export general key "process_proto" should be false
    And the export general key "process_host" should be true
    And the export should contain 1 schemes
    And export scheme 0 should have name "Custom"
    And export scheme 0 should be enabled
    And export scheme 0 forwarding_scheme key "header" should be "X-Real-IP"
    And export scheme 0 forwarding_scheme "token" should be null
    And export scheme 0 forwarding_scheme key "notes" should be "test"
    And export scheme 0 forwarding_scheme proxies should contain "10.0.0.0/8"

  Scenario: Export JSON is valid and human-readable
    Given the plugin has default settings
    When the settings are exported as JSON with site URL "https://example.com" and timestamp "2026-04-19T14:30:45+00:00"
    Then the JSON should be valid
    And the JSON should be pretty-printed

  Scenario: Export filename includes UTC timestamp
    When an export filename is generated at UTC time "2026-04-19 14:30:45"
    Then the filename should be "vcip-settings-20260419-143045.json"
