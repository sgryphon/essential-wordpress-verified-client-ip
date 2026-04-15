## ADDED Requirements

### Requirement: Plugin action links include Settings and Guide entries appended after existing links
The system SHALL append a "settings" link and a "guide" link to the existing plugin action links array, preserving the original entries and their order.

#### Scenario: Settings and Guide links are appended after pre-existing links
- **WHEN** `AdminPage::add_action_links()` is called with two existing links (deactivate, edit)
- **THEN** the result SHALL contain exactly 4 entries with keys in order: deactivate, edit, settings, guide

### Requirement: Settings action link points to the plugin settings page
The system SHALL generate a Settings action link whose URL contains the `options-general.php?page=gryphon-verified-client-ip` path.

#### Scenario: Settings URL targets the plugin settings page
- **WHEN** `AdminPage::add_action_links()` is called
- **THEN** the `settings` entry SHALL contain the string `options-general.php?page=gryphon-verified-client-ip`

### Requirement: Guide action link points to the user guide tab
The system SHALL generate a Guide action link whose URL contains the `tab=user-guide` parameter on the plugin settings page.

#### Scenario: Guide URL targets the user guide tab
- **WHEN** `AdminPage::add_action_links()` is called
- **THEN** the `guide` entry SHALL contain the string `options-general.php?page=gryphon-verified-client-ip&tab=user-guide`

### Requirement: Action link labels display human-readable text
The system SHALL render action link labels containing the words "Settings" and "Guide" respectively.

#### Scenario: Link labels contain translatable text
- **WHEN** `AdminPage::add_action_links()` is called
- **THEN** the `settings` entry SHALL contain the text "Settings" and the `guide` entry SHALL contain the text "Guide"
