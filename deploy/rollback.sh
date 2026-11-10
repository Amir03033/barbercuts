#!/usr/bin/env bash
#
# Zet de vorige release terug. Migraties worden NIET teruggedraaid.
#
set -euo pipefail

APP_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PREVIOUS="$(ls -1dt "$APP_DIR"/releases/*/ | sed -n 2p)"

[ -n "$PREVIOUS" ] || { echo "Geen vorige release gevonden."; exit 1; }

ln -sfn "${PREVIOUS%/}" "$APP_DIR/current.new"
mv -Tf "$APP_DIR/current.new" "$APP_DIR/current"

cd "$APP_DIR/current"
php artisan optimize
php artisan queue:restart
sudo systemctl reload php8.4-fpm

echo "✓ Teruggezet naar ${PREVIOUS%/}"
