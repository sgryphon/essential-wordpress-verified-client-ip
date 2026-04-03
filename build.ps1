# Build a distributable zip for the Essential Verified Client IP WordPress plugin.
#
# Usage: .\build.ps1
# Output: build\essential-verified-client-ip.zip

$ErrorActionPreference = 'Stop'

$PluginSlug = 'essential-verified-client-ip'
$BuildDir = 'build'
$DistDir = Join-Path $BuildDir $PluginSlug

Write-Host '==> Cleaning previous build...'
if (Test-Path $BuildDir) { Remove-Item -Recurse -Force $BuildDir }
New-Item -ItemType Directory -Path $DistDir -Force | Out-Null

Write-Host '==> Installing dev dependencies for build tools...'
composer install --optimize-autoloader --quiet

Write-Host '==> Generating user guide HTML...'
composer run-script build-user-guide --quiet

Write-Host '==> Installing production dependencies...'
composer install --no-dev --optimize-autoloader --quiet

Write-Host '==> Copying files...'
Copy-Item -Recurse -Path 'src' -Destination (Join-Path $DistDir 'src')
Copy-Item -Recurse -Path 'vendor' -Destination (Join-Path $DistDir 'vendor')
Copy-Item -Path 'essential-verified-client-ip.php' -Destination $DistDir
Copy-Item -Path 'uninstall.php' -Destination $DistDir
Copy-Item -Recurse -Path 'assets' -Destination $DistDir
Copy-Item -Path 'composer.json' -Destination $DistDir
Copy-Item -Path 'LICENSE' -Destination $DistDir
Copy-Item -Path 'readme.txt' -Destination $DistDir
Copy-Item -Path 'src\user-guide.html' -Destination (Join-Path $DistDir 'src')

Write-Host '==> Creating zip...'
$ZipPath = Join-Path $BuildDir "$PluginSlug.zip"
Compress-Archive -Path $DistDir -DestinationPath $ZipPath -Force

Write-Host "==> Done: $ZipPath"

Write-Host '==> Restoring dev dependencies...'
composer install --quiet
