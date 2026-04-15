## ADDED Requirements

### Requirement: Valid parsed input produces a Settings object with no errors
The system SHALL accept parsed form input through `Settings::validate()` and return a Settings object with all fields correctly typed when the input is valid.

#### Scenario: Full valid input round-trips through parse and validate
- **WHEN** a POST array with enabled=1, forward_limit=2, process_proto=1, process_host=1, and one scheme with two valid CIDR proxies is passed through `AdminPage::parse_form_input()` then `Settings::validate()`
- **THEN** the result SHALL have no errors, `settings->enabled=true`, `settings->forward_limit=2` (integer), `settings->process_proto=true`, `settings->process_host=true`, one scheme named "My Proxy" with header "X-Forwarded-For", null token, and 2 proxies

### Requirement: Invalid proxy addresses in parsed input are flagged as errors
The system SHALL reject non-IP strings in the proxies list, report them as validation errors, and retain only the valid proxies on the resulting scheme.

#### Scenario: One invalid proxy among valid entries produces errors and retains valid proxies
- **WHEN** a POST array containing a scheme with proxies `10.0.0.0/8`, `not-an-ip`, and `192.168.1.1` is passed through parse and validate
- **THEN** the result SHALL have a non-empty errors array, and the scheme SHALL have exactly 2 proxies (the two valid ones)
