# Packaging Guide

## Building a Distributable Zip

The plugin can be packaged into a `.zip` file suitable for uploading to
WordPress or submitting to the WordPress Plugin Directory.

### Using the Build Script

```bash
# Make the script executable (Linux/macOS)
chmod +x build.sh

# Run it
./build.sh
```

On Windows (PowerShell):

```powershell
.\build.ps1
```

The script:

1. Runs `composer install --no-dev --optimize-autoloader` to get production
   dependencies only.
2. Copies the required files into a `build/gryphon-verified-client-ip/` directory.
3. Creates `build/gryphon-verified-client-ip.zip`.

### Manual Build

```bash
# Install production dependencies
composer install --no-dev --optimize-autoloader

# Create the zip
mkdir -p build/gryphon-verified-client-ip
cp -r src/ vendor/ gryphon-verified-client-ip.php uninstall.php composer.json \
      LICENSE README.md build/gryphon-verified-client-ip/
cd build && zip -r gryphon-verified-client-ip.zip gryphon-verified-client-ip/
```

### Files Included in the Zip

| File/Directory                   | Purpose                     |
| -------------------------------- | --------------------------- |
| `gryphon-verified-client-ip.php` | Main plugin entry point     |
| `uninstall.php`                  | Cleanup handler on deletion |
| `src/`                           | PHP source classes          |
| `vendor/`                        | Composer autoloader         |
| `composer.json`                  | Dependency manifest         |
| `LICENSE`                        | GPLv2+ license text         |
| `readme.txt`                     | Plugin overview             |

Files **excluded** from the zip: `tests/`, `examples/`, `specifications/`,
`docs/`, `compose.yaml`, `.github/`, `phpcs.xml.dist`, `phpstan.neon`,
`phpunit.xml`, build scripts.

## WordPress Plugin Directory Submission

To submit the plugin to the [WordPress Plugin Directory](https://wordpress.org/plugins/):

1. Create an account at https://wordpress.org/support/register.php
2. Read the [Plugin Guidelines](https://developer.wordpress.org/plugins/wordpress-org/detailed-plugin-guidelines/)
3. Submit at https://wordpress.org/plugins/developers/add/
4. Provide the SVN repository once approved
5. Upload the plugin files to the SVN trunk

### Plugin Header Requirements

The plugin header in `gryphon-verified-client-ip.php` contains all required fields:

- Plugin Name, Plugin URI, Description
- Version, Requires at least, Requires PHP
- Author, Author URI
- License, License URI
- Text Domain

### WordPress Subversion

Subversion Guide: https://developer.wordpress.org/plugins/wordpress-org/how-to-use-subversion/

Initial checkout:

```sh
mkdir gryphon-verified-client-ip
svn co https://plugins.svn.wordpress.org/gryphon-verified-client-ip gryphon-verified-client-ip
```

Copy files into trunk:

```sh
ls essential-wordpress-verified-client-ip/build/gryphon-verified-client-ip/
cp -r essential-wordpress-verified-client-ip/build/gryphon-verified-client-ip/* gryphon-verified-client-ip/trunk/
ls gryphon-verified-client-ip/trunk/
```

Push:

```sh
cd gryphon-verified-client-ip
svn add trunk/*
svn ci -m 'Adding version 1.2.0 of plugin'
```

Tag version:

```sh
svn cp trunk tags/1.2.0
svn ci -m "Tagging version 1.2.0"
```

### Subversion Assets

Assets Guide: https://developer.wordpress.org/plugins/wordpress-org/plugin-assets/

## Versioning

Update version numbers in these locations when releasing:

1. `gryphon-verified-client-ip.php` — the `Version:` header and `VCIP_VERSION` constant
2. `composer.json` — the `version` field (if present)
3. Tag the Git commit: `git tag v1.0.0 && git push --tags`
