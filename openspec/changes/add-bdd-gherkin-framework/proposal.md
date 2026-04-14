## Why

The project currently relies on PHPUnit and WP_Mock only, which makes behavior-level acceptance tests harder to express in a business-readable way. Adding a Gherkin-based BDD layer now improves collaboration and gives a clear path for executable behavior specs as the plugin grows.

## What Changes

- Add a PHP BDD framework that supports Gherkin feature files (e.g., Behat) as a development and CI test dependency.
- Add baseline BDD test infrastructure (configuration, context class, and test bootstrap integration) so feature files can execute reliably in this repository.
- Add one simple, end-to-end validation scenario in Gherkin that exercises a real production class (`IpUtils`) and implement matching step definitions to prove the framework is wired correctly.
- Document the command used to run BDD tests alongside existing test tooling.

## Capabilities

### New Capabilities
- `bdd-gherkin-testing`: Provide executable Gherkin-based behavior tests with at least one working scenario that exercises production code to validate framework integration.

### Modified Capabilities
<!-- None for this pilot slice -->

## Impact

- Affected code: test tooling configuration, Composer dev dependencies, BDD context/step definition classes, and new `.feature` test files.
- Affected workflows: local development and CI test execution may include an additional BDD command.
- Dependencies: introduces a BDD framework package and related components for Gherkin parsing/execution.