## Context

The plugin currently uses PHPUnit and WP_Mock for unit and integration-style verification, but it has no behavior-level test layer written in natural language. The proposed change introduces a Gherkin-capable BDD framework to prove that readable acceptance scenarios can execute in this repository with existing PHP tooling, coding standards, and CI constraints.

## Goals / Non-Goals

**Goals:**
- Add a stable BDD execution path for PHP 8.1+ in this WordPress plugin repository.
- Keep the first implementation intentionally small: one simple Gherkin scenario with working step definitions.
- Integrate BDD execution into existing developer workflow conventions (`composer` scripts, CI-friendly CLI invocation).
- Reuse existing project conventions (no WordPress runtime bootstrap unless needed for the first scenario).

**Non-Goals:**
- Replace PHPUnit or WP_Mock test suites.
- Build a full acceptance/E2E environment with HTTP/browser automation.
- Cover all existing plugin capabilities with BDD in this change.
- Redesign production plugin architecture around BDD concepts.

## Decisions

1. Adopt Behat as the BDD framework and Gherkin executor.
   - Rationale: Behat is mature in the PHP ecosystem, natively supports `.feature` files and step definitions, and works well as a Composer dev dependency.
   - Alternative considered: PHPSpec with custom Gherkin tooling. Rejected because it is not focused on native Gherkin workflow and would add glue complexity.

2. Place feature files and context classes in a dedicated BDD test area under the repository test structure.
   - Rationale: clear separation from PHPUnit tests simplifies maintenance and avoids accidental coupling of runners.
   - Alternative considered: mixing feature files into current PHPUnit directories. Rejected to prevent tooling confusion and ambiguous ownership.

3. Use a scenario that exercises a real production class (`IpUtils`) rather than asserting a hardcoded constant.
   - Rationale: step definitions that only verify a hard-coded literal provide no signal about the code under test. `IpUtils` is a pure PHP class with no WordPress dependency, making it the simplest meaningful entry point. The chosen scenario — normalising an IPv4-mapped IPv6 address to its IPv4 form — reimplements a behaviour already covered by `IpUtilsTest`, giving a documented precedent for correctness.
   - Alternative considered: asserting plugin slug or metadata constants. Rejected because those tests would pass even if the parser class was deleted entirely.
   - Alternative considered: multi-hop proxy `Resolver` scenario. Out of scope for the pilot — requires setting up `Scheme` instances and `$_SERVER` state and is better suited to a second BDD iteration.

4. Execute BDD through a Composer script (for example, `composer test:bdd`) and keep it callable in CI.
   - Rationale: consistent command discovery and straightforward pipeline integration with existing checks.
   - Alternative considered: direct binary invocation only. Rejected because it is less discoverable and less consistent with repository workflow.

5. Keep the first scenario runtime-light (pure PHP assertions in step definitions) rather than booting full WordPress.
   - Rationale: faster feedback and easier proof-of-integration for framework adoption.
   - Alternative considered: booting WordPress in Behat context immediately. Rejected for initial slice due to added setup complexity.

## Risks / Trade-offs

- [Dependency footprint increase] -> Limit new packages to core BDD requirements and pin compatible versions.
- [Two testing styles may fragment contributor habits] -> Document clear purpose of PHPUnit vs BDD and provide command examples.
- [Scenario too trivial to demonstrate long-term value] -> Tie first scenario to an existing spec capability and plan incremental follow-up scenarios.
- [CI runtime increase] -> Keep initial suite to one scenario and monitor duration before expanding.

## Migration Plan

1. Add Behat and required extensions as Composer dev dependencies.
2. Add Behat configuration and context bootstrap in repository test tooling.
3. Add one feature file and matching step definitions for a simple plugin identity scenario.
4. Add a Composer script for BDD execution and include it in CI checks as appropriate.
5. Validate locally and in CI, then expand scenarios in future changes if needed.

Rollback strategy: remove BDD dependencies, configuration, and feature/context files; remove BDD script from Composer/CI if adoption causes instability.

## Open Questions

- None for this pilot slice; decisions are now explicit for implementation.

## Decision Updates

- BDD execution will run in the main CI pipeline from the initial rollout.
- Long-term direction is to move toward BDD replacing unit-test coverage, but this change remains a pilot with one scenario and does not remove PHPUnit/WP_Mock now.
- The first scenario exercises `IpUtils::normalise()` with an IPv4-mapped IPv6 address, replacing the original hardcoded plugin-slug approach.
- Shared assertion utilities between PHPUnit and Behat are out of scope; BDD tests stay independent for simplicity.