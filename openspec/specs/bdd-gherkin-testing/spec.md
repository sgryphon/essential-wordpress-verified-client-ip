## MODIFIED Requirements

### Requirement: Execute Gherkin feature files in project test workflow
The project SHALL provide a BDD test runner that executes Gherkin `.feature` files from the repository and returns non-zero exit status when any scenario fails.

The runner SHALL execute all feature files across all capability subdirectories under `features/`, including the integration test coverage added by this change.

#### Scenario: BDD runner executes feature suite
- **WHEN** a contributor runs the project BDD test command
- **THEN** the configured Gherkin test runner executes repository feature files and reports pass/fail status for each scenario

## ADDED Requirements

### Requirement: behat.yml.dist registers all context classes for the default suite
The `behat.yml.dist` configuration file SHALL list all context classes — `IpNormalisationContext`, `AdminPageContext`, `DiagnosticsContext`, `PluginBootContext`, and `UninstallContext` — under the default suite so Behat discovers their step definitions.

#### Scenario: All context classes are registered in behat.yml.dist
- **WHEN** `behat.yml.dist` is read
- **THEN** the default suite contexts SHALL include IpNormalisationContext, AdminPageContext, DiagnosticsContext, PluginBootContext, and UninstallContext

### Requirement: Feature files are organised under capability subdirectories
The `features/` directory SHALL contain subdirectories for each converted capability area: `admin-page/`, `diagnostics/`, `plugin/`, and `uninstall/`, each containing one `.feature` file per capability.

#### Scenario: Feature files exist under their capability subdirectories
- **WHEN** the features directory is inspected
- **THEN** feature files SHALL exist at the paths defined in the design document's feature layout
