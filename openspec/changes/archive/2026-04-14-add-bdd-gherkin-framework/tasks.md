## 1. BDD Tooling Setup

- [x] 1.1 Add Behat (and required Gherkin support packages) to Composer development dependencies.
- [x] 1.2 Add Behat configuration file(s) and default suite/context wiring for this repository.
- [x] 1.3 Add a Composer script (for example `test:bdd`) that runs the BDD suite.

## 2. Pilot Scenario Implementation

- [x] 2.1 ~~Create one Gherkin `.feature` file for plugin identity slug validation.~~ (replaced — hardcoded slug test removed)
- [x] 2.2 ~~Implement step definitions/context class methods that execute the pilot scenario end-to-end.~~ (replaced — context class removed)
- [x] 2.3 Ensure the pilot scenario is runtime-light and does not require full WordPress bootstrap.

## 3. CI Integration

- [x] 3.1 Update the main CI pipeline to execute the BDD Composer command.
- [x] 3.2 Ensure CI fails when the BDD scenario fails (non-zero command exit).

## 4. Validation and Documentation

- [x] 4.1 Run local BDD command and confirm the pilot scenario passes.
- [x] 4.2 Run existing quality checks (tests, analysis, formatting) to verify no regressions from BDD additions.
- [x] 4.3 Document how to run BDD tests and the pilot scope in project docs/tooling notes.

## 5. Replace Pilot Scenario with IpUtils-Based BDD Test

- [x] 5.1 Create a Gherkin `.feature` file for IP address normalisation that describes `IpUtils::normalise()` behaviour in natural language.
- [x] 5.2 Implement a Behat context class that calls `IpUtils::normalise()` in step definitions (no hardcoded assertions — the production class provides the answer).
- [x] 5.3 Update the Behat suite configuration to wire the new context class.
- [x] 5.4 Run `composer test:bdd` locally and confirm the new scenario passes.
- [x] 5.5 Run `composer check` to confirm no formatting or analysis regressions from the new files.
