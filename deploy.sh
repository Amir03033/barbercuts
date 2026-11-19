#!/usr/bin/env bash
#
# Werkt barbercuts.nl bij op de Plesk-server (docs/DEPLOY.md, hoofdstuk "Plesk").
#
# Gebruik, als sitegebruiker in de map httpdocs:
#   PHP=/opt/plesk/php/8.4/bin/php ./deploy.sh
#
# Opties (omgevingsvariabelen):
#   PHP       het PHP-programma (standaard: /opt/plesk/php/8.4/bin/php)
#   NODE_BIN  de map met Node 24 (standaard: /opt/plesk/node/24/bin)
#   NO_PULL=1 haal geen code op uit GitHub (Plesk Git deed dat al)
#   REF       branch of tag om op te halen (standaard: main)
#
set -euo pipefail

cd "$(dirname "${BASH_SOURCE[0]}")"

PHP="${PHP:-/opt/plesk/php/8.4/bin/php}"
NODE_BIN="${NODE_BIN:-/opt/plesk/node/24/bin}"
REF="${REF:-main}"

[ -x "$PHP" ] || { echo "✗ PHP niet gevonden op $PHP (geef het pad mee met PHP=...)"; exit 1; }
[ -x "$NODE_BIN/node" ] || { echo "✗ Node niet gevonden in $NODE_BIN (geef de map mee met NODE_BIN=...)"; exit 1; }

# Plesk start deployment-acties soms zonder HOME; composer en npm hebben een schrijfbare thuismap nodig.
export HOME="${HOME:-$(getent passwd "$(id -un)" | cut -d: -f6)}"
export COMPOSER_HOME="${COMPOSER_HOME:-$HOME/.composer}"
# Alles hieronder gebruikt dit PHP en deze Node, ook wat composer en npm zelf starten.
export PATH="$(dirname "$PHP"):$NODE_BIN:/usr/local/bin:/usr/bin:/bin:$PATH"

[ -f .env ] || { echo "✗ .env ontbreekt (zie docs/DEPLOY.md)"; exit 1; }
grep -qE '^APP_KEY=base64:.+' .env || { echo "✗ APP_KEY in .env is leeg"; exit 1; }
grep -qE '^APP_DEBUG=false' .env || echo "! APP_DEBUG staat niet op false in .env"

COMPOSER_BIN="$(command -v composer || true)"
[ -n "$COMPOSER_BIN" ] || { echo "✗ composer niet gevonden"; exit 1; }

echo "→ PHP $("$PHP" -r 'echo PHP_VERSION;') · Node $(node --version)"

if [ "${NO_PULL:-0}" != "1" ] && [ -d .git ]; then
    echo "→ Code ophalen ($REF)"
    git fetch --prune --tags origin
    git checkout "$REF"
    git merge --ff-only "origin/$REF" 2>/dev/null || git merge --ff-only FETCH_HEAD 2>/dev/null || true
fi

echo "→ PHP-pakketten"
"$PHP" "$COMPOSER_BIN" install --no-dev --optimize-autoloader --no-interaction --prefer-dist

echo "→ Frontend bouwen"
npm ci --no-audit --no-fund
npm run build

# Vanaf hier even in onderhoudsmodus (bezoekers zien de 503-pagina). Gaat er iets mis, dan gaat de site meteen weer open.
echo "→ Onderhoudsmodus aan"
"$PHP" artisan down --retry=30 --refresh=15
trap '"$PHP" artisan up >/dev/null 2>&1 || true' EXIT

echo "→ Database bijwerken"
"$PHP" artisan migrate --force

echo "→ Bestanden en caches"
"$PHP" artisan storage:link 2>/dev/null || true
"$PHP" artisan filament:assets
"$PHP" artisan optimize:clear
"$PHP" artisan optimize
"$PHP" artisan filament:optimize
"$PHP" artisan queue:restart

echo "→ Onderhoudsmodus uit"
"$PHP" artisan up
trap - EXIT

echo "✓ Klaar"
