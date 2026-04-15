## Context

The project already has one Behat feature (`features/ip-normalisation/normalise.feature`) with its context class in `features/bootstrap/IpNormalisationContext.php`. The `behat.yml.dist` config uses a single default suite registering that one context. The integration test bootstrap (`tests/Integration/bootstrap.php`) defines WordPress function stubs that allow plugin classes to be exercised without a full WordPress installation.

Four PHPUnit integration test files need migrating:

| File | Tests | Capabilities covered |
|---|---|---|
| `AdminPageTest.php` | 11 | form parsing, validation round-trip, action links |
| `DiagnosticsTest.php` | 17 | state management, clamping, recording, constants |
| `PluginTest.php` | 14 | REMOTE_ADDR replacement, enabled/disabled, proto, host, hooks, defaults, singleton, forward limit |
| `UninstallTest.php` | 2 | options/transient cleanup |

## Goals / Non-Goals

**Goals:**
- Convert every PHPUnit integration test into an equivalent Behat scenario
- Maintain identical coverage — every assertion in PHPUnit must have a corresponding Then step
- Follow the existing convention: one context class per feature area, one feature file per capability, feature files grouped by subdirectory
- Keep all contexts in `features/bootstrap/` (the Behat autoload path)
- Reuse the WordPress function stubs from `tests/Integration/bootstrap.php`
- Each conversion is independently committable and passes `composer check`

**Non-Goals:**
- Deleting the original PHPUnit integration test files (follow-up task)
- Adding new test scenarios beyond what exists in the PHPUnit tests
- Changing production source code
- Moving to a full WordPress test harness (WP_UnitTestCase, wp-env, etc.)

## Decisions

### 1. One context class per feature area, not per capability

Several capabilities share the same production class and test setup (e.g., `plugin-remote-addr-replacement`, `plugin-enabled-disabled`, `plugin-proto-processing` all exercise `Plugin::boot()`). Using one context per production class keeps setup/teardown DRY.

**Context classes (4 total):**
- `AdminPageContext` — covers form parsing, validation round-trip, action links
- `DiagnosticsContext` — covers state management, clamping, recording, constants
- `PluginBootContext` — covers REMOTE_ADDR replacement, enabled/disabled, proto, host, hooks, defaults, singleton, forward limit
- `UninstallContext` — covers uninstall cleanup

**Alternative considered:** One context per capability (14 contexts). Rejected because the shared setup logic (`$_SERVER` backup/restore, globals reset, settings helpers) would be duplicated extensively.

### 2. WordPress stubs loaded via bootstrap require_once

The WordPress stubs from `tests/Integration/bootstrap.php` need to be loaded once before any scenario. Each context that needs stubs will `require_once __DIR__ . '/../../tests/Integration/bootstrap.php'` in its constructor. The stubs already use `if ( ! function_exists(...) )` guards, so multiple require_once calls are safe and idempotent.

**Alternative considered:** A dedicated `WordPressStubsContext` with `@BeforeScenario`. Rejected because Behat contexts can't easily share state across context instances in the same suite — a simple require_once in each context constructor is simpler.

### 3. Feature file organisation: one file per capability

Each of the 14 capabilities from the proposal gets its own `.feature` file in a subdirectory matching the production class:

```
features/
  admin-page/
    form-input-parsing.feature
    settings-validation.feature
    action-links.feature
  diagnostics/
    state-management.feature
    recording.feature
  plugin/
    remote-addr-replacement.feature
    enabled-disabled.feature
    proto-processing.feature
    host-processing.feature
    wordpress-hooks.feature
    default-schemes.feature
    singleton.feature
    forward-limit.feature
  uninstall/
    uninstall.feature
```

This mirrors the proposal's capability list exactly and keeps features scannable.

### 4. behat.yml.dist: single suite with all contexts

Rather than per-feature suites, add all context classes to the default suite. This keeps the config simple and matches the existing pattern. Behat's autoloader picks up all classes in `features/bootstrap/`.

```yaml
default:
  suites:
    default:
      contexts:
        - IpNormalisationContext
        - AdminPageContext
        - DiagnosticsContext
        - PluginBootContext
        - UninstallContext
```

### 5. Conversion order: one test file at a time, atomic commits

Each integration test file is converted as a batch (all its scenarios in one pass) because the context class and stubs setup are shared across scenarios within the same file. The order follows dependency depth:

1. **AdminPageTest** — pure function calls, no `$_SERVER` mutation, simplest setup
2. **DiagnosticsTest** — transient stubs only, no `$_SERVER`
3. **PluginTest** — requires `$_SERVER` management, singleton reset, most scenarios
4. **UninstallTest** — requires `define()` guards and `include` of `uninstall.php`

Each step: write `.feature` + context → run `composer test:bdd` → run `composer check` → commit.

### 6. Step definition style

Follow the existing project convention from `IpNormalisationContext`:
- `@Given` / `@When` / `@Then` annotations (not attributes — project uses docblock annotations)
- Assertions via `throw new RuntimeException(...)` (not PHPUnit assertions in contexts)
- `WordPress-Core` PHPCS formatting (tabs, snake_case for variables, Yoda conditions)

## Risks / Trade-offs

- **Step name collisions across contexts** → Mitigated by using specific, non-generic step text (e.g. "the admin form input is parsed" not "the input is parsed"). Behat will error on duplicate step definitions at boot, so collisions are caught immediately.
- **Bootstrap load order** → The stubs use `if ( ! function_exists(...) )` guards, so loading `bootstrap.php` multiple times is safe.
- **Plugin singleton leaking between scenarios** → Each Plugin scenario resets the singleton via reflection in a `@BeforeScenario` hook, exactly as the PHPUnit `setUp()` does today.
- **UninstallTest uses `define()` for `WP_UNINSTALL_PLUGIN`** → Constants can't be undefined. The existing test already handles this with `if ( ! defined(...) )`. The BDD context will do the same.
- **Long BDD test runtime** → Behat may take 70s+. This is expected per AGENTS.md. No mitigation needed beyond CI timeout configuration.
