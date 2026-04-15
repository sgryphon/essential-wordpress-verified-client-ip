Feature: Plugin action links
  In order to provide quick access to settings and documentation
  As the plugin
  I want to add Settings and Guide links to the plugins list

  Scenario: Settings and Guide links are appended after existing links
    Given the existing plugin action links are:
      | key        | html                         |
      | deactivate | <a href="#">Deactivate</a>   |
      | edit       | <a href="#">Edit</a>         |
    When the action links are generated
    Then the action links should contain 4 entries
    And the action link keys should be "deactivate, edit, settings, guide"

  Scenario: Settings URL targets the plugin settings page
    When the action links are generated
    Then the action link "settings" should contain "options-general.php?page=gryphon-verified-client-ip"

  Scenario: Guide URL targets the user guide tab
    When the action links are generated
    Then the action link "guide" should contain "options-general.php?page=gryphon-verified-client-ip&tab=user-guide"

  Scenario: Link labels contain translatable text
    When the action links are generated
    Then the action link "settings" should contain "Settings"
    And the action link "guide" should contain "Guide"
