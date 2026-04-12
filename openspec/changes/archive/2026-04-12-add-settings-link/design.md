## Context

The plugin currently registers its admin UI in `AdminPage::register()`, which is called conditionally inside `if (is_admin())` in the main plugin file. The admin page is added under WordPress Settings with slug `gryphon-verified-client-ip` and has a tab-based layout including a `user-guide` tab. There are no existing `plugin_action_links` filters anywhere in the codebase. The main plugin file defines `VCIP_PLUGIN_FILE` as `__FILE__`, which can be used to derive the plugin basename.

## Goals / Non-Goals

**Goals:**

- Add "Settings" and "Guide" links to the plugin's row on the Plugins page.
- Keep the implementation minimal — a single filter registration and callback.
- Follow existing patterns: place the logic in `AdminPage` alongside other admin registrations.

**Non-Goals:**

- Adding links to the network plugins page (multisite network-level).
- Adding plugin row meta links (the second row of links, e.g., "View details").
- Internationalisation of link labels beyond using the existing text domain.

## Decisions

### 1. Place the filter in `AdminPage::register()`

**Decision**: Add the `plugin_action_links_{basename}` filter inside `AdminPage::register()`, next to the existing `admin_menu` and `admin_init` hooks.

**Rationale**: `AdminPage` already owns all admin-facing UI registration. Adding the filter here keeps admin concerns together. The alternative — registering it in the main plugin file — would scatter admin logic across files.

### 2. Use `plugin_action_links_{basename}` (specific filter)

**Decision**: Use the plugin-specific filter `plugin_action_links_` . `plugin_basename( VCIP_PLUGIN_FILE )` rather than the generic `plugin_action_links` filter.

**Rationale**: The specific filter only fires for this plugin's row, avoiding unnecessary callback invocations for every plugin on the page. This is standard WordPress practice.

### 3. Prepend links (Settings first, then Guide)

**Decision**: Prepend both links before the existing "Deactivate" and "Edit" links using `array_merge` so "Settings" appears first and "Guide" second.

**Rationale**: Matches the convention used by most WordPress plugins where "Settings" is the leftmost action link.

### 4. Use `admin_url()` for link targets

**Decision**: Build URLs with `admin_url( 'options-general.php?page=' . self::MENU_SLUG )` for Settings and append `&tab=user-guide` for Guide.

**Rationale**: `admin_url()` is the WordPress-standard approach for generating admin URLs and handles subdirectory installs correctly. Using the existing `MENU_SLUG` constant avoids duplicating the slug string.

## Risks / Trade-offs

- **VCIP_PLUGIN_FILE not defined** → `AdminPage::register()` is only called inside `if (is_admin())` in the main plugin file, which defines the constant first. No risk in practice.
- **Guide tab removed in future** → The "Guide" link would point to a tab that doesn't exist, falling back to the default tab. Low risk, easily caught by tests.
