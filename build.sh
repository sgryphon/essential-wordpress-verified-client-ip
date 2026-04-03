#!/usr/bin/env bash
# Build a distributable zip for the Verified Client IP WordPress plugin.
#
# Usage: ./build.sh
# Output: build/verified-client-ip.zip

set -euo pipefail

PLUGIN_SLUG="verified-client-ip"
BUILD_DIR="build"
DIST_DIR="${BUILD_DIR}/${PLUGIN_SLUG}"

echo "==> Cleaning previous build..."
rm -rf "${BUILD_DIR}"
mkdir -p "${DIST_DIR}"

echo "==> Installing dev dependencies for build tools..."
composer install --optimize-autoloader --quiet

echo "==> Generating user guide HTML..."
composer run-script build-user-guide --quiet

echo "==> Cleaning vendor..."
rm -rf vendor composer.lock

echo "==> Installing production dependencies..."
composer install --no-dev --optimize-autoloader --quiet

echo "==> Copying files..."
cp -r src/ "${DIST_DIR}/src/"
cp -r vendor/ "${DIST_DIR}/vendor/"
cp verified-client-ip.php "${DIST_DIR}/"
cp uninstall.php "${DIST_DIR}/"
cp composer.json "${DIST_DIR}/"
cp LICENSE "${DIST_DIR}/"
cp readme.txt "${DIST_DIR}/"
cp src/user-guide.html "${DIST_DIR}/src/"

echo "==> Creating zip..."
cd "${BUILD_DIR}"
zip -rq "${PLUGIN_SLUG}.zip" "${PLUGIN_SLUG}/"
cd ..

echo "==> Done: ${BUILD_DIR}/${PLUGIN_SLUG}.zip"

# Restore dev dependencies for development.
echo "==> Restoring dev dependencies..."
composer install --quiet
