## ADDED Requirements

### Requirement: Settings link on Plugins page

The plugin SHALL add a "Settings" action link to its row on the WordPress Plugins page. The link SHALL navigate to the plugin's settings page.

#### Scenario: Settings link is present

- **WHEN** an administrator views the WordPress Plugins page
- **THEN** the plugin's row SHALL display a "Settings" action link before the default action links

#### Scenario: Settings link navigates to settings page

- **WHEN** the administrator clicks the "Settings" action link
- **THEN** the browser SHALL navigate to the plugin's main settings page

### Requirement: Guide link on Plugins page

The plugin SHALL add a "Guide" action link to its row on the WordPress Plugins page. The link SHALL navigate to the plugin's User Guide tab.

#### Scenario: Guide link is present

- **WHEN** an administrator views the WordPress Plugins page
- **THEN** the plugin's row SHALL display a "Guide" action link after the "Settings" link and before the default action links

#### Scenario: Guide link navigates to user guide tab

- **WHEN** the administrator clicks the "Guide" action link
- **THEN** the browser SHALL navigate to the plugin's User Guide tab

### Requirement: Links use translatable labels

The "Settings" and "Guide" link labels SHALL be wrapped in WordPress translation functions using the plugin text domain so they can be translated.

#### Scenario: Labels are translatable

- **WHEN** the plugin action links are rendered
- **THEN** each link label SHALL be passed through a WordPress translation function with the plugin text domain
