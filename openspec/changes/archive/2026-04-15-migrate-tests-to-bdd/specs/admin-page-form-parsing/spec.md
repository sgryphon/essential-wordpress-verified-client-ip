## ADDED Requirements

### Requirement: Basic settings fields are parsed from POST input
The system SHALL parse the `vcip_enabled`, `vcip_forward_limit`, `vcip_process_proto`, and `vcip_process_host` POST fields into a structured settings array with correctly typed values.

#### Scenario: All basic fields present and populated
- **WHEN** `AdminPage::parse_form_input()` is called with `vcip_enabled=1`, `vcip_forward_limit=3`, `vcip_process_proto=1`, `vcip_process_host=0`
- **THEN** the result SHALL have `enabled=true`, `forward_limit="3"`, `process_proto=true`, `process_host=false`

#### Scenario: Absent checkboxes are treated as disabled
- **WHEN** `AdminPage::parse_form_input()` is called with only `vcip_forward_limit=1` (no checkbox fields)
- **THEN** the result SHALL have `enabled=false`, `process_proto=false`, `process_host=false`

### Requirement: Scheme entries are parsed from POST input
The system SHALL parse an array of scheme entries from the `vcip_schemes` POST field, preserving name, enabled state, header, token, proxies list, and notes for each entry.

#### Scenario: Two schemes with different enabled states and proxy lists
- **WHEN** `AdminPage::parse_form_input()` is called with two scheme entries — one enabled XFF scheme with two proxies, one disabled Forwarded scheme with a token
- **THEN** the result SHALL contain exactly 2 schemes, the first with `name="XFF"`, `enabled=true`, `header="X-Forwarded-For"`, `token=""`, 2 proxies (`10.0.0.0/8` and `192.168.0.0/16`), and the second with `name="Forwarded"`, `enabled=false`, `token="for"`

#### Scenario: No schemes key in POST produces no schemes in result
- **WHEN** `AdminPage::parse_form_input()` is called without a `vcip_schemes` key
- **THEN** the result SHALL NOT contain a `schemes` key

#### Scenario: Empty proxies textarea produces empty proxies array
- **WHEN** `AdminPage::parse_form_input()` is called with a scheme whose `proxies` value is an empty string
- **THEN** the parsed scheme SHALL have an empty proxies array

#### Scenario: Blank lines in proxies textarea are filtered out
- **WHEN** `AdminPage::parse_form_input()` is called with a scheme whose `proxies` value contains blank lines between valid entries
- **THEN** the parsed scheme SHALL contain only the non-blank proxy entries
