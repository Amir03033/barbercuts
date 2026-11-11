#!/usr/bin/env bash
#
# Werkt de site bij op de Plesk-server na een git pull (docs/DEPLOY.md, hoofdstuk "Plesk").
# Draait in Plesk bij Git → "Additional deployment actions", of handmatig als de sitegebruiker:
#   cd /var/www/vhosts/barbercuts.nl/httpdocs && bash deploy/plesk-update.sh
#
set -euo pipefail

cd "$(dirname "${BASH_SOURCE[0]}")/.."

# De PHP en Node die Plesk voor deze site gebruikt (de gewone `php` op de server kan een andere versie zijn).
export PATH="/opt/plesk/php/8.4/bin:/opt/plesk/node/24/bin:/usr/local/bin:/usr/bin:/bin:$PATH"

[ -f .env ] || { echo "✗ .env ontbreekt (zie docs/DEPLOY.md)"; exit 1; }
grep -qE '^APP_KEY=base64:.+' .env || { echo "✗ APP_KEY in .env is leeg"; exit 1; }

echo "→ PHP-pakketten"
composer install --no-dev --optimize-autoloader --no-interaction

echo "→ Frontend bouwen"
npm ci --no-audit --no-fund
npm run build

echo "→ Database bijwerken"
php artisan migrate --force

echo "→ Bestanden en caches"
php artisan storage:link 2>/dev/null || true
php artisan filament:assets
php artisan optimize:clear
php artisan optimize
php artisan filament:optimize
php artisan queue:restart

echo "✓ Klaar"
