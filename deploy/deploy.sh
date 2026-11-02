#!/usr/bin/env bash
# Usage: ./deploy/deploy.sh
# Assumes APP_ENV=production and the server layout /var/www/waymate/{backend,frontend}.
set -euo pipefail

ROOT="${WAYMATE_ROOT:-/var/www/waymate}"
PHP="${PHP_BIN:-php}"

cleanup() {
  "$PHP" "$ROOT/backend/artisan" up || true
}
trap cleanup ERR

cd "$ROOT/backend"
"$PHP" artisan down --retry=30 || true

composer install --no-dev --prefer-dist --no-interaction --optimize-autoloader

if ! grep -q '^APP_KEY=base64:' .env 2>/dev/null; then
  "$PHP" artisan key:generate --force
fi

"$PHP" artisan migrate --force
"$PHP" artisan db:seed --class=RolePermissionSeeder --force
"$PHP" artisan storage:link || true
"$PHP" artisan optimize

cd "$ROOT/frontend"
npm ci
npm run build

"$PHP" "$ROOT/backend/artisan" up
sudo systemctl restart waymate-worker || true

echo "WayMate deployed. Add this to root's crontab if missing:"
echo "* * * * * cd $ROOT/backend && $PHP artisan schedule:run >> /dev/null 2>&1"
