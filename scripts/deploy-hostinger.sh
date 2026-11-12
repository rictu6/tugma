#!/usr/bin/env bash
set -euo pipefail
umask 077

root=$1
release_id=$2
php_bin=$3
composer_bin=$4

[[ "$release_id" =~ ^[a-f0-9]{40}-[0-9]+-[0-9]+$ ]] || { echo 'Invalid release ID.' >&2; exit 1; }
[[ -d "$root" && -f "$root/shared/.env" && -d "$root/shared/storage" ]] || { echo 'Provision shared/.env and shared/storage first.' >&2; exit 1; }
[[ ! -e "$root/current" || -L "$root/current" ]] || { echo 'current exists and is not a symlink.' >&2; exit 1; }
[[ -x "$php_bin" && -f "$composer_bin" ]] || { echo 'PHP or Composer path is invalid.' >&2; exit 1; }
"$php_bin" -r 'if (PHP_VERSION_ID < 80336 || PHP_VERSION_ID >= 80400) { fwrite(STDERR, "Server CLI must use PHP 8.3.36 or newer in the 8.3 series.\n"); exit(1); }'
mkdir -p "$root/releases" "$root/shared/storage/app/public" "$root/shared/storage/framework/cache/data" "$root/shared/storage/framework/sessions" "$root/shared/storage/framework/views" "$root/shared/storage/logs"
mkdir "$root/.deploy-lock" || { echo 'Another deployment is running or a stale lock needs inspection.' >&2; exit 1; }
trap 'rmdir "$root/.deploy-lock"' EXIT
release="$root/releases/$release_id"
mkdir "$release"
tar -xzf "$root/incoming/$release_id.tar.gz" -C "$release"
rm -rf "$release/storage"
ln -s "$root/shared/storage" "$release/storage"
ln -s "$root/shared/.env" "$release/.env"
ln -s "$root/shared/storage/app/public" "$release/public/storage"
mkdir -p "$release/bootstrap/cache"
cd "$release"
"$php_bin" "$composer_bin" check-platform-reqs --no-dev
"$php_bin" artisan package:discover --no-interaction
"$php_bin" -r 'require "vendor/autoload.php"; $app = require "bootstrap/app.php"; $app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap(); if (config("app.env") !== "production" || config("app.debug") || ! config("app.key") || ! str_starts_with(config("app.url"), "https://")) { fwrite(STDERR, "Production APP_ENV, APP_DEBUG, APP_KEY, and HTTPS APP_URL are required.\n"); exit(1); }'
"$php_bin" artisan filament:assets --no-interaction
"$php_bin" artisan optimize --no-interaction
"$php_bin" artisan filament:optimize --no-interaction
"$php_bin" artisan migrate --force --no-interaction
"$php_bin" -r 'require "vendor/autoload.php"; $app = require "bootstrap/app.php"; $app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap(); if (! Illuminate\Support\Facades\Schema::hasColumn("accomplishments", "target")) { fwrite(STDERR, "Missing accomplishments.target: fix migrations before deployment.\n"); exit(1); }'
ln -s "$release" "$root/.current-$release_id"
mv -Tf "$root/.current-$release_id" "$root/current"
echo "Activated release $release_id"
