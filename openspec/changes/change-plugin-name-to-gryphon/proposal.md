## Why

The WordPress Plugin Directory requires plugins to have a distinctive, non-generic name. "Essential Verified Client IP" is too generic and will not be accepted. The plugin needs a distinctive brand name — "Gryphon" — to meet directory listing requirements while retaining recognisability for the feature it provides.

## What Changes

- **BREAKING**: Plugin display name changes from `Essential Verified Client IP` to `Gryphon Verified Client IP`
- **BREAKING**: WordPress slug changes from `essential-verified-client-ip` to `gryphon-verified-client-ip`
- **BREAKING**: Text domain changes from `essential-verified-client-ip` to `gryphon-verified-client-ip`
- **BREAKING**: Main plugin file renames from `essential-verified-client-ip.php` to `gryphon-verified-client-ip.php`
- **BREAKING**: PHP namespace changes from `Essential\VerifiedClientIp` to `Gryphon\VerifiedClientIp`
- **BREAKING**: Composer package name changes from `essential/verified-client-ip` to `gryphon/verified-client-ip`
- Admin menu slug constant changes from `essential-verified-client-ip` to `gryphon-verified-client-ip`
- Admin page heading (H1) changes from `Essential Verified Client IP` to `Gryphon Verified Client IP`
- Plugin URI updated to reflect the new repository name
- readme.txt and README.md titles updated to `Gryphon Verified Client IP`

**Unchanged**:

- The `vcip_` prefix for options, transients, hooks, nonces, and constants remains as-is
- The admin settings menu item label stays as the short `Verified Client IP`
- All functional behaviour is unchanged

## Capabilities

### New Capabilities

_(none)_

### Modified Capabilities

_(none — this is a naming/branding change only; no spec-level behaviour changes)_

## Impact

- **Main plugin file**: `essential-verified-client-ip.php` → `gryphon-verified-client-ip.php`
- **All source files** (`src/*.php`): namespace declaration changes
- **All test files** (`tests/**/*.php`): namespace `use` statements and bootstrap references change
- **Composer autoload**: PSR-4 mapping updates from `Essential\\VerifiedClientIp\\` to `Gryphon\\VerifiedClientIp\\`
- **Build scripts**: `build.sh` and `build.ps1` reference the plugin slug for packaging
- **CI configuration**: any GitHub Actions workflows referencing the slug
- **Examples**: compose environment and proxy configs reference the plugin directory name
- **User guide build**: `bin/build-user-guide.php` references namespace
- **PHPStan/PHPCS config**: bootstrap and configuration files reference namespace
- **Existing installations**: users upgrading will need to deactivate the old plugin and activate the new one (slug change = new plugin to WordPress)
- **Documentation**: `docs/*.md`, `readme.txt`, `README.md`, `AGENTS.md`, and the user guide all reference the old name
