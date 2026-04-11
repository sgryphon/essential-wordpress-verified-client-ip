## RENAMED Requirements

### Requirement: Plugin identity strings
FROM: `Essential Verified Client IP` / `essential-verified-client-ip` / `Essential\VerifiedClientIp` / `essential/verified-client-ip`
TO: `Gryphon Verified Client IP` / `gryphon-verified-client-ip` / `Gryphon\VerifiedClientIp` / `gryphon/verified-client-ip`

The plugin SHALL use "Gryphon" as the brand prefix in all identity strings: display name, WordPress slug, text domain, PHP namespace, and Composer package name.

The plugin SHALL retain the `vcip_` prefix for all options, transients, hooks, nonces, and PHP constants.

The admin settings menu item label SHALL remain as the short form `Verified Client IP`.

#### Scenario: WordPress recognises the plugin under the new slug
- **WHEN** WordPress scans the plugins directory
- **THEN** the plugin is identified as `gryphon-verified-client-ip/gryphon-verified-client-ip.php`

#### Scenario: Text domain matches the new slug
- **WHEN** a translatable string is loaded via `__()` or `esc_html__()`
- **THEN** the text domain used is `gryphon-verified-client-ip`

#### Scenario: PHP namespace is updated
- **WHEN** any source file under `src/` declares its namespace
- **THEN** the namespace is `Gryphon\VerifiedClientIp`

#### Scenario: Internal prefixes are unchanged
- **WHEN** the plugin reads or writes options, transients, or fires hooks
- **THEN** the `vcip_` prefix is used (not changed to `gryphon_` or any other prefix)

#### Scenario: Admin menu label is unchanged
- **WHEN** the settings page is registered in the WordPress admin menu
- **THEN** the menu item label is `Verified Client IP` (not prefixed with "Gryphon")
