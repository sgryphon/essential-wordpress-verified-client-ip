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
2. Copies the required files into a `build/verified-client-ip/` directory.
3. Creates `build/verified-client-ip.zip`.

### Manual Build

```bash
# Install production dependencies
composer install --no-dev --optimize-autoloader

# Create the zip
mkdir -p build/verified-client-ip
cp -r src/ vendor/ verified-client-ip.php uninstall.php composer.json \
      LICENSE README.md build/verified-client-ip/
cd build && zip -r verified-client-ip.zip verified-client-ip/
```

### Files Included in the Zip

| File/Directory           | Purpose                          |
|--------------------------|----------------------------------|
| `verified-client-ip.php` | Main plugin entry point          |
| `uninstall.php`          | Cleanup handler on deletion      |
| `src/`                   | PHP source classes               |
| `vendor/`                | Composer autoloader              |
| `composer.json`          | Dependency manifest              |
| `LICENSE`                | GPLv2+ license text              |
| `README.md`              | Plugin overview                  |

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

The plugin header in `verified-client-ip.php` contains all required fields:

- Plugin Name, Plugin URI, Description
- Version, Requires at least, Requires PHP
- Author, Author URI
- License, License URI
- Text Domain, Domain Path

## Versioning

Update version numbers in these locations when releasing:

1. `verified-client-ip.php` — the `Version:` header and `VCIP_VERSION` constant
2. `composer.json` — the `version` field (if present)
3. Tag the Git commit: `git tag v1.0.0 && git push --tags`
