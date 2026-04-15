## ADDED Requirements

### Requirement: Uninstall script deletes all plugin options and transients
The system SHALL delete the `vcip_settings` option and the `vcip_diagnostic_log`, `vcip_diagnostic_state`, and `vcip_diagnostic_lock` transients when `uninstall.php` is executed.

#### Scenario: Uninstall removes options and all diagnostic transients
- **WHEN** `vcip_settings`, `vcip_diagnostic_log`, `vcip_diagnostic_state`, and `vcip_diagnostic_lock` are populated and `uninstall.php` is included with `WP_UNINSTALL_PLUGIN` defined
- **THEN** `vcip_settings` SHALL NOT be present in options, and none of the three diagnostic transients SHALL be present

### Requirement: vcip_uninstall_site() deletes per-site options and transients
The system SHALL expose a `vcip_uninstall_site()` function that deletes `vcip_settings` and all diagnostic transients for the current site.

#### Scenario: vcip_uninstall_site clears options and transients
- **WHEN** `vcip_settings` and `vcip_diagnostic_log` are populated and `vcip_uninstall_site()` is called directly
- **THEN** `vcip_settings` SHALL NOT be present in options and `vcip_diagnostic_log` SHALL NOT be present in transients
