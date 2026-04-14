## ADDED Requirements

### Requirement: Execute Gherkin feature files in project test workflow
The project SHALL provide a BDD test runner that executes Gherkin `.feature` files from the repository and returns non-zero exit status when any scenario fails.

#### Scenario: BDD runner executes feature suite
- **WHEN** a contributor runs the project BDD test command
- **THEN** the configured Gherkin test runner executes repository feature files and reports pass/fail status for each scenario

### Requirement: Main CI pipeline runs BDD tests
The project's main CI pipeline SHALL execute the BDD test command as part of standard quality checks.

#### Scenario: CI includes BDD command
- **WHEN** the main CI workflow runs for a change
- **THEN** the workflow executes the project BDD command and fails the pipeline if the BDD scenario fails

### Requirement: Repository includes one executable pilot scenario that exercises production code
The project SHALL include at least one Gherkin scenario whose step definitions call a real production class, not a hardcoded constant or inline value.

The pilot scenario SHALL exercise `IpUtils::normalise()` by passing an IPv4-mapped IPv6 address and asserting the returned value is the normalised IPv4 address.

#### Scenario: IPv4-mapped IPv6 address normalises to IPv4
- **WHEN** `IpUtils::normalise()` is called with `::ffff:192.168.1.1`
- **THEN** the result SHALL be `192.168.1.1`

#### Scenario: Pilot scenario passes without WordPress bootstrap
- **WHEN** the BDD test command runs the pilot scenario
- **THEN** the scenario executes and passes without requiring a WordPress runtime or database connection
