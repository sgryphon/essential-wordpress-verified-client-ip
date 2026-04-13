## 1. BDD Tooling Setup

- [ ] 1.1 Add Behat (and required Gherkin support packages) to Composer development dependencies.
- [ ] 1.2 Add Behat configuration file(s) and default suite/context wiring for this repository.
- [ ] 1.3 Add a Composer script (for example `test:bdd`) that runs the BDD suite.

## 2. Pilot Scenario Implementation

- [ ] 2.1 Create one Gherkin `.feature` file for plugin identity slug validation.
- [ ] 2.2 Implement step definitions/context class methods that execute the pilot scenario end-to-end.
- [ ] 2.3 Ensure the pilot scenario is runtime-light and does not require full WordPress bootstrap.

## 3. CI Integration

- [ ] 3.1 Update the main CI pipeline to execute the BDD Composer command.
- [ ] 3.2 Ensure CI fails when the BDD scenario fails (non-zero command exit).

## 4. Validation and Documentation

- [ ] 4.1 Run local BDD command and confirm the pilot scenario passes.
- [ ] 4.2 Run existing quality checks (tests, analysis, formatting) to verify no regressions from BDD additions.
- [ ] 4.3 Document how to run BDD tests and the pilot scope in project docs/tooling notes.