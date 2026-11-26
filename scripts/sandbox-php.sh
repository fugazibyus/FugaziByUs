#!/usr/bin/env bash
# Makes sure the agent sandbox runs PHP 8.4, which composer.lock needs (Symfony 8.1).
#
# The Managed Agents cloud sandbox is Ubuntu 24.04 with PHP 8.3, and the environment config
# (environments/sandbox.yaml) can only install packages from Ubuntu's own archive, which stops
# at 8.3. So the developer and debugger agents run this before `composer install`:
#
#   bash scripts/sandbox-php.sh
#
# It adds the ondrej/php PPA (signing key pinned in scripts/ondrej-php.asc, fingerprint
# 14AA40EC0831756756D7F66C4F4EA0AAE5267A6C) and installs PHP 8.4 with the extensions the app
# and its tests need. It does nothing if PHP 8.4 is already the default, so it is safe to run again.
set -euo pipefail

VERSION=8.4
EXTENSIONS=(cli mbstring xml curl sqlite3 mysql intl zip bcmath)

if php -r 'exit(PHP_VERSION_ID >= 80400 ? 0 : 1);' 2>/dev/null; then
    echo "PHP $(php -r 'echo PHP_VERSION;') is already installed."
    exit 0
fi

sudo=''
if [ "$(id -u)" -ne 0 ]; then
    sudo='sudo'
fi

here="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
codename="$(. /etc/os-release && echo "$VERSION_CODENAME")"

$sudo install -d -m 0755 /etc/apt/keyrings
$sudo install -m 0644 "$here/ondrej-php.asc" /etc/apt/keyrings/ondrej-php.asc
echo "deb [signed-by=/etc/apt/keyrings/ondrej-php.asc] https://ppa.launchpadcontent.net/ondrej/php/ubuntu $codename main" |
    $sudo tee /etc/apt/sources.list.d/ondrej-php.list >/dev/null

$sudo apt-get update -qq
$sudo env DEBIAN_FRONTEND=noninteractive apt-get install -y -qq --no-install-recommends \
    "${EXTENSIONS[@]/#/php$VERSION-}" >/dev/null

# Make 8.4 the default `php`, whether or not the existing PHP was registered with update-alternatives.
$sudo update-alternatives --set php "/usr/bin/php$VERSION" >/dev/null 2>&1 || true
if ! php -r 'exit(PHP_VERSION_ID >= 80400 ? 0 : 1);' 2>/dev/null; then
    $sudo ln -sf "/usr/bin/php$VERSION" /usr/local/bin/php
    hash -r
fi

echo "PHP $(php -r 'echo PHP_VERSION;') is ready."
