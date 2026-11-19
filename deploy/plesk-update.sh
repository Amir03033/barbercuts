#!/usr/bin/env bash
#
# Kort pad voor Plesk Git → "Additional deployment actions". Doet hetzelfde als ./deploy.sh in de projectmap,
# maar haalt zelf geen code op: Plesk heeft dat net gedaan.
#   /bin/bash /var/www/vhosts/barbercuts.nl/httpdocs/deploy/plesk-update.sh
#
set -euo pipefail

cd "$(dirname "${BASH_SOURCE[0]}")/.."

NO_PULL=1 exec bash ./deploy.sh
