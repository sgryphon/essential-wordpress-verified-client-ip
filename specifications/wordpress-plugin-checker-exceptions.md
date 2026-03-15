# WordPress Plugin Checker Exceptions

This document records Plugin Checker issues that are intentionally not fixed,
with a justification for each decision.

## `src/AdminPage.php`

### Line 119 — `WordPress.Security.NonceVerification.Recommended` on `$_GET['tab']`

**Issue:** Processing form data without nonce verification.

**Decision:** Accepted (suppressed with inline `phpcs:ignore`).

**Justification:** The `$_GET['tab']` parameter is used only to determine which
tab to display in the admin page (a read-only, idempotent, GET request). No
data is modified based on this value.  Adding a nonce to a plain page-navigation
GET URL would be non-standard WordPress behaviour and break standard browser
navigation (bookmarks, history, direct links).  The PHPCS rule is
`NonceVerification.Recommended` (a warning, not an error), confirming it is an
advisory rather than a hard requirement.

## `src/Logger.php`

### Line 87 — `WordPress.PHP.DevelopmentFunctions.error_log_error_log`

**Issue:** `error_log()` found. Debug code should not normally be used in
production.

**Decision:** Accepted (suppressed with inline `phpcs:ignore`).

**Justification:** `error_log()` is the intentional implementation of the
plugin's logging facility.  It is guarded by `WP_DEBUG` / `VCIP_LOG_REQUESTS`
constants so it is silent by default in production, and is only called for
errors and warnings that a site administrator should be aware of.  Removing it
would break the plugin's observability features.
