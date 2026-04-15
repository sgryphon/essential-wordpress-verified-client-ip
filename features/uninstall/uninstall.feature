Feature: Uninstall
  In order to leave no data behind when the plugin is removed
  As a site administrator
  I want the uninstall process to clean up all options and transients

  Scenario: Uninstall removes options and all diagnostic transients
    Given the option "vcip_settings" is populated
    And the transient "vcip_diagnostic_log" is populated
    And the transient "vcip_diagnostic_state" is populated
    And the transient "vcip_diagnostic_lock" is populated
    When the uninstall script is executed
    Then the option "vcip_settings" should not exist
    And the transient "vcip_diagnostic_log" should not exist
    And the transient "vcip_diagnostic_state" should not exist
    And the transient "vcip_diagnostic_lock" should not exist

  Scenario: vcip_uninstall_site clears options and transients
    Given the option "vcip_settings" is populated
    And the transient "vcip_diagnostic_log" is populated
    When vcip_uninstall_site is called
    Then the option "vcip_settings" should not exist
    And the transient "vcip_diagnostic_log" should not exist
