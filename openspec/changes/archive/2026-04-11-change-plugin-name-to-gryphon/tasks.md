## 1. Rename main plugin file

- [x] 1.1 `git mv essential-verified-client-ip.php gryphon-verified-client-ip.php`

## 2. Update plugin header and bootstrap

- [x] 2.1 Update plugin header in `gryphon-verified-client-ip.php` (Plugin Name, Text Domain, @package, namespace references)

## 3. Update Composer configuration

- [x] 3.1 Update package name and PSR-4 namespace mappings in `composer.json`

## 4. Update source files (`src/`)

- [x] 4.1 Replace namespace declarations and display name in all `src/*.php` files (AdminPage, Plugin, Resolver, ResolverResult, ResolverStep, Settings, Scheme, HeaderParser, Diagnostics, IpUtils, Logger)
- [x] 4.2 Replace `MENU_SLUG` constant and text domain strings in `src/AdminPage.php`
- [x] 4.3 Replace plugin filename reference in `src/AdminPage.php` settings link

## 5. Update test files

- [x] 5.1 Replace namespace declarations and `use` statements in all `tests/Unit/*.php` files
- [x] 5.2 Replace namespace declarations and `use` statements in all `tests/Integration/*.php` files

## 6. Update configuration files

- [x] 6.1 Update `phpstan-bootstrap.php` (@package, plugin filename reference)
- [x] 6.2 Update `phpcs.xml.dist` (ruleset name, description, file reference, text domain element)
- [x] 6.3 Update `uninstall.php` (display name, @package)

## 7. Update build scripts

- [x] 7.1 Update `build.sh` (PLUGIN_SLUG, display name, filename references)
- [x] 7.2 Update `build.ps1` ($PluginSlug, display name, filename references)

## 8. Update documentation

- [x] 8.1 Update `readme.txt` (title, display name, slug references)
- [x] 8.2 Update `README.md` (title, display name, slug references)
- [x] 8.3 Update `AGENTS.md` (display name, slug, text domain references)
- [x] 8.4 Update `docs/user-guide.md` (display name, slug references)
- [x] 8.5 Update `docs/development.md` (filename references)
- [x] 8.6 Update `docs/packaging.md` (slug, filename references)

## 9. Update examples

- [x] 9.1 Update `examples/wp-client-ip/compose.yaml` (plugin mount path)
- [x] 9.2 Update `examples/wp-client-ip/README.md` (display name, slug)

## 10. Regenerate and verify

- [x] 10.1 Run `composer dump-autoload` to regenerate autoload mappings
- [x] 10.2 Run `composer run-script check` to verify formatting, static analysis, and tests pass
