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

### Requirement: Repository includes one executable pilot scenario
The project SHALL include at least one simple Gherkin scenario with implemented step definitions to validate BDD wiring end-to-end.

#### Scenario: Pilot scenario passes
- **WHEN** the BDD test command runs against the pilot scenario
- **THEN** the scenario executes all matching step definitions and passes without requiring a full WordPress runtime bootstrap