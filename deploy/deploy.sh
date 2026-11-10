#!/usr/bin/env bash
#
# Zero-downtime deploy met releases-mappen. Staat op de server als <APP_DIR>/deploy.sh.
# Gebruik: ./deploy.sh [git-ref]      (standaard: origin/main)
#
set -euo pipefail

APP_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
REPO_DIR="$APP_DIR/repo"
SHARED_DIR="$APP_DIR/shared"
RELEASES_DIR="$APP_DIR/releases"
REF="${1:-origin/main}"
RELEASE="$RELEASES_DIR/$(date +%Y%m%d%H%M%S)"
KEEP=5
PHP_FPM_SERVICE="php8.4-fpm"

echo "→ Code ophalen ($REF)"
git -C "$REPO_DIR" fetch --prune --tags origin
mkdir -p "$RELEASE"
git -C "$REPO_DIR" archive "$REF" | tar -x -C "$RELEASE"

echo "→ Gedeelde bestanden koppelen"
rm -rf "$RELEASE/storage"
ln -s "$SHARED_DIR/storage" "$RELEASE/storage"
ln -s "$SHARED_DIR/.env" "$RELEASE/.env"

cd "$RELEASE"

echo "→ Composer"
composer install --no-dev --optimize-autoloader --no-interaction --prefer-dist

echo "→ Frontend bouwen"
npm ci --no-audit --no-fund
npm run build
rm -rf node_modules

echo "→ Migraties en cache"
php artisan storage:link
php artisan migrate --force
php artisan optimize

echo "→ Nieuwe release live zetten"
ln -sfn "$RELEASE" "$APP_DIR/current.new"
mv -Tf "$APP_DIR/current.new" "$APP_DIR/current"

php artisan queue:restart
sudo systemctl reload "$PHP_FPM_SERVICE"

echo "→ Oude releases opruimen (laatste $KEEP blijven staan)"
ls -1dt "$RELEASES_DIR"/*/ | tail -n +$((KEEP + 1)) | xargs -r rm -rf

echo "✓ Klaar: $RELEASE"
