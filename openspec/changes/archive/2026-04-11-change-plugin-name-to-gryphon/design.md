## Context

The plugin is currently named "Essential Verified Client IP" across all naming layers: display name, WordPress slug, text domain, PHP namespace, Composer package, and main filename. The WordPress Plugin Directory requires a distinctive (non-generic) name, so the brand prefix changes from "Essential" to "Gryphon". This is a pure rename — no functional changes.

The rename touches ~35 files with four distinct string patterns. The `vcip_` internal prefix for options, transients, hooks, and constants is explicitly unchanged, as is the short admin menu label "Verified Client IP".

## Goals / Non-Goals

**Goals:**

- Replace all identity strings so the plugin is recognized as "Gryphon Verified Client IP" by WordPress, Composer, and developers
- Maintain all existing functionality — no behavioural changes
- Keep `vcip_` prefix and admin menu label unchanged
- Ensure tests, static analysis, and coding standards pass after rename

**Non-Goals:**

- Renaming the GitHub repository (separate decision; repo URL references will be left as-is or updated independently)
- Providing an automated migration path for existing installations (slug change = new plugin to WordPress; users must deactivate old and activate new)
- Changing the `vcip_` option/hook prefix
- Renaming SVG/image asset files in `docs/master/`

## Decisions

### 1. Four string patterns, applied globally

| Pattern (old)                  | Replacement (new)            | Scope                                                  |
| ------------------------------ | ---------------------------- | ------------------------------------------------------ |
| `Essential Verified Client IP` | `Gryphon Verified Client IP` | Display name in headers, headings, docblocks, docs     |
| `essential-verified-client-ip` | `gryphon-verified-client-ip` | Slug, text domain, filenames, menu slug, build scripts |
| `Essential\VerifiedClientIp`   | `Gryphon\VerifiedClientIp`   | PHP namespace (unescaped)                              |
| `essential/verified-client-ip` | `gryphon/verified-client-ip` | Composer package name                                  |

The escaped namespace variant (`Essential\\VerifiedClientIp\\`) is covered by the unescaped pattern match in context.

**Rationale**: Four precise patterns cover every occurrence. A broader regex risks false positives; targeted patterns are safer and auditable.

### 2. Main plugin file renamed via `git mv`

The file `essential-verified-client-ip.php` is renamed to `gryphon-verified-client-ip.php` using `git mv` to preserve history.

**Rationale**: WordPress identifies plugins by `directory/filename.php`. The filename must match the slug.

### 3. No data migration

WordPress treats a slug change as a completely different plugin. Existing `vcip_*` options and transients will persist in the database (keyed by the `vcip_` prefix, not the slug), so settings data survives. However, the plugin must be deactivated (old slug) and reactivated (new slug) manually.

**Rationale**: The `vcip_` prefix is unchanged, so all stored settings and transients are still valid. The only "migration" is reactivation — an unavoidable consequence of a slug change in WordPress. An automated migration would require shipping both old and new plugin files simultaneously, which adds complexity for minimal benefit.

### 4. Build output regenerated, not manually renamed

The `build/` directory contains generated artifacts. After renaming source files and updating build scripts, running the build will produce correctly-named output. No manual changes to `build/` are needed.

### 5. GitHub repository URL left as-is

References to `https://github.com/sgryphon/essential-wordpress-verified-client-ip` are not changed in this scope. If the repository is renamed later, a follow-up change can update these URLs. GitHub provides automatic redirects for renamed repos.

**Rationale**: Repository renaming is a separate operational decision with its own implications (CI, links, forks). Decoupling it keeps this change focused.

### 6. Process: rename file first, then bulk string replacement, then test

Order of operations:

1. `git mv` the main plugin file
2. Apply the four string replacements across all source, test, config, and doc files
3. Run `composer dump-autoload` to regenerate autoload mappings
4. Run the full check suite (`composer run-script check`) to verify

**Rationale**: Renaming first avoids editing a file that will move. Bulk replacement is safe because the four patterns are distinctive and won't produce false positives (confirmed by audit). Regenerating autoload is necessary because the PSR-4 namespace root changes.

## Risks / Trade-offs

- **Existing users must reactivate** → Mitigated by documenting in changelog and upgrade notice. Settings data persists via `vcip_` prefix.
- **Stale references in external docs/links** → Mitigated by GitHub redirect (if repo is later renamed) and WordPress Plugin Directory handling slug changes.
- **Merge conflicts with in-flight branches** → Mitigated by doing the rename as an atomic commit before other changes land.
- **`build/` directory contains old-named files** → Mitigated by `.gitignore` and regeneration on next build.
