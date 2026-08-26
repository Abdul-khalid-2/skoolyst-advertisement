#!/usr/bin/env bash
#
# Deploy script (Section 7.f). Run on the server as part of each
# release — full server provisioning (PHP-FPM/nginx setup, the
# opcache.ini drop-in, the crontab entry for rollup_daily_stats.php)
# lives in Section 11/14, not here; this script only covers the steps
# that run on every single deploy.
set -euo pipefail

echo "==> Installing dependencies (production, optimized autoloader)"
# --no-dev: skip anything test/dev-only. --optimize-autoloader (7.f):
# bakes a classmap instead of resolving PSR-4 paths at runtime, which
# matters once autoloading is handled by Composer rather than the
# manual require lists in routes/api.php.
composer install --no-dev --optimize-autoloader --no-interaction --prefer-dist

echo "==> Restarting PHP-FPM to pick up the new release"
# Required because opcache.validate_timestamps=0 (config/opcache.ini)
# means PHP won't otherwise notice the files on disk changed.
sudo systemctl restart php-fpm

echo "==> Deploy complete"
