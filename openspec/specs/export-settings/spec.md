## ADDED Requirements

### Requirement: Export Settings button on the main settings page
The admin settings page SHALL display an "Export Settings" button in the settings tab that allows administrators to download the current plugin configuration as a JSON file.

#### Scenario: Export Settings button is visible on the settings tab
- **GIVEN** the user is on the Settings tab of the Verified Client IP admin page
- **THEN** an "Export Settings" button SHALL be displayed below the Save Settings button

### Requirement: Export produces valid JSON with metadata, general settings, and schemes
The exported JSON file SHALL contain a `metadata` object, a `general` object with general plugin settings, and a `schemes` array with each scheme's top-level data and a nested `forwarding_scheme` object for the forwarding-specific configuration.

#### Scenario: Default settings produce correct export structure
- **GIVEN** the plugin has default settings (enabled, forward_limit=1, process_proto=true, process_host=false, 3 default schemes)
- **WHEN** the settings are exported via `Settings::to_export_array()`
- **THEN** the result SHALL contain a `metadata` key with `exported_from` and `exported_at` values
- **AND** the result SHALL contain a `general` key with `enabled`, `forward_limit`, `process_proto`, and `process_host`
- **AND** the result SHALL contain a `schemes` array with 3 entries
- **AND** each scheme entry SHALL have `name`, `enabled`, and a `forwarding_scheme` object
- **AND** each `forwarding_scheme` object SHALL have `header`, `token`, `proxies`, and `notes`

#### Scenario: Custom settings round-trip through export
- **GIVEN** settings with enabled=false, forward_limit=5, process_proto=false, process_host=true, and one scheme named "Custom" that is enabled with header "X-Real-IP", null token, proxies ["10.0.0.0/8"], and notes "test"
- **WHEN** the settings are exported via `Settings::to_export_array()`
- **THEN** `general.enabled` SHALL be false
- **AND** `general.forward_limit` SHALL be 5
- **AND** `general.process_proto` SHALL be false
- **AND** `general.process_host` SHALL be true
- **AND** `schemes[0].name` SHALL be "Custom"
- **AND** `schemes[0].enabled` SHALL be true
- **AND** `schemes[0].forwarding_scheme.header` SHALL be "X-Real-IP"
- **AND** `schemes[0].forwarding_scheme.token` SHALL be null
- **AND** `schemes[0].forwarding_scheme.proxies` SHALL be ["10.0.0.0/8"]
- **AND** `schemes[0].forwarding_scheme.notes` SHALL be "test"

### Requirement: Exported metadata includes source and timestamp
The metadata section SHALL include `exported_from` (the site URL) and `exported_at` (ISO 8601 UTC timestamp) to identify where and when the export was created.

#### Scenario: Metadata contains site URL and ISO 8601 timestamp
- **WHEN** the settings are exported
- **THEN** `metadata.exported_from` SHALL be the WordPress site URL
- **AND** `metadata.exported_at` SHALL be a valid ISO 8601 UTC timestamp (e.g. "2026-04-19T12:00:00+00:00")

### Requirement: Export filename includes a timestamp
The downloaded JSON filename SHALL follow the pattern `vcip-settings-YYYYMMDD-HHMMSS.json` using the UTC date/time at the moment of export.

#### Scenario: Filename includes UTC timestamp
- **WHEN** the export is triggered at 2026-04-19 14:30:45 UTC
- **THEN** the download filename SHALL be `vcip-settings-20260419-143045.json`

### Requirement: Export requires manage_options capability
The export action SHALL be protected by a WordPress nonce check and the `manage_options` capability, consistent with all other settings actions.

#### Scenario: User without manage_options cannot export
- **GIVEN** a user who does not have the `manage_options` capability
- **WHEN** the export action is triggered
- **THEN** the system SHALL NOT output the JSON and SHALL deny the request

### Requirement: Export JSON is human-readable
The JSON output SHALL be formatted with `JSON_PRETTY_PRINT` so it is easily readable and editable by humans.
