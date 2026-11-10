# BarberCuts — deploy op een eigen VPS

Stappenplan voor **Ubuntu 24.04 LTS** (docs/BOUWOPDRACHT.md §13). Voer de stappen in deze volgorde uit.
De config-bestanden staan in de map [`deploy/`](../deploy) van deze repo.

| Omgeving | Domein | Map op de server | Database |
|---|---|---|---|
| Productie | `barbercuts.nl` | `/var/www/barbercuts` | `barbercuts` |
| Staging | `staging.barbercuts.nl` (basic-auth) | `/var/www/barbercuts-staging` | `barbercuts_staging` |

Mappenstructuur per omgeving:

```text
/var/www/barbercuts
├── deploy.sh          ← kopie van deploy/deploy.sh
├── rollback.sh        ← kopie van deploy/rollback.sh
├── repo/              ← kale git-clone (alleen om code uit te halen)
├── releases/          ← 20261110143000/, … (laatste 5 blijven staan)
├── shared/
│   ├── .env
│   └── storage/       ← logs, uploads, cache, sessies
└── current → releases/20261110143000
```

**Vooraf nodig:** een VPS met root-toegang, DNS A/AAAA-records voor `barbercuts.nl`, `www.barbercuts.nl` en
`staging.barbercuts.nl` naar het IP van de VPS, en de repo op GitHub.

---

## 1. Server beveiligen

Als `root` op de nieuwe server:

```bash
adduser --disabled-password --gecos "" deploy
usermod -aG www-data deploy
mkdir -p /home/deploy/.ssh
cp ~/.ssh/authorized_keys /home/deploy/.ssh/authorized_keys   # jouw publieke SSH-key
chown -R deploy:deploy /home/deploy/.ssh && chmod 700 /home/deploy/.ssh && chmod 600 /home/deploy/.ssh/authorized_keys
```

`deploy` mag alleen PHP-FPM herladen (nodig aan het eind van een deploy):

```bash
echo 'deploy ALL=(root) NOPASSWD: /usr/bin/systemctl reload php8.4-fpm' > /etc/sudoers.d/deploy
chmod 440 /etc/sudoers.d/deploy
```

SSH dichtzetten — maak `/etc/ssh/sshd_config.d/99-barbercuts.conf`:

```text
PermitRootLogin no
PasswordAuthentication no
KbdInteractiveAuthentication no
```

```bash
sshd -t && systemctl restart ssh
```

> Test in een **tweede terminal** of inloggen als je eigen beheerder-gebruiker (met sudo) nog werkt voordat je de
> eerste sessie sluit. `deploy` heeft bewust geen volledige sudo-rechten.

Firewall, fail2ban en automatische beveiligingsupdates:

```bash
apt update && apt -y upgrade
apt -y install ufw fail2ban unattended-upgrades
ufw default deny incoming && ufw default allow outgoing
ufw allow 22/tcp && ufw allow 80/tcp && ufw allow 443/tcp
ufw --force enable
systemctl enable --now fail2ban
dpkg-reconfigure -f noninteractive unattended-upgrades
```

## 2. Pakketten

```bash
add-apt-repository -y ppa:ondrej/php
apt update
apt -y install nginx mysql-server redis-server supervisor certbot python3-certbot-nginx git unzip apache2-utils \
  php8.4-fpm php8.4-cli php8.4-bcmath php8.4-curl php8.4-gd php8.4-imagick php8.4-intl php8.4-mbstring \
  php8.4-mysql php8.4-redis php8.4-xml php8.4-zip php8.4-opcache

# Node 22 (alleen voor de build)
curl -fsSL https://deb.nodesource.com/setup_22.x | bash -
apt -y install nodejs

# Composer
curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer
```

PHP-instellingen voor productie — `/etc/php/8.4/fpm/conf.d/99-barbercuts.ini`:

```ini
upload_max_filesize = 10M
post_max_size = 12M
memory_limit = 256M
expose_php = Off
opcache.enable = 1
opcache.memory_consumption = 192
opcache.max_accelerated_files = 20000
opcache.validate_timestamps = 0
```

PHP-FPM laten draaien als `deploy` — in `/etc/php/8.4/fpm/pool.d/www.conf`:

```ini
user = deploy
group = www-data
listen.owner = www-data
listen.group = www-data
```

```bash
systemctl restart php8.4-fpm
```

## 3. Database en Redis

```bash
mysql_secure_installation
mysql <<'SQL'
CREATE DATABASE barbercuts CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE DATABASE barbercuts_staging CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'barbercuts'@'localhost' IDENTIFIED BY 'KIES-EEN-STERK-WACHTWOORD';
CREATE USER 'barbercuts_staging'@'localhost' IDENTIFIED BY 'KIES-EEN-ANDER-WACHTWOORD';
GRANT ALL PRIVILEGES ON barbercuts.* TO 'barbercuts'@'localhost';
GRANT ALL PRIVILEGES ON barbercuts_staging.* TO 'barbercuts_staging'@'localhost';
FLUSH PRIVILEGES;
SQL
```

Redis luistert standaard alleen op `127.0.0.1`; laat dat zo. Staging gebruikt een eigen Redis-database
(`REDIS_DB=2`, `REDIS_CACHE_DB=3`) en een eigen prefix, zodat de omgevingen elkaars queue niet zien.

## 4. Mappen en de eerste `.env`

Als `deploy` (herhaal voor `barbercuts-staging`):

```bash
sudo mkdir -p /var/www/barbercuts && sudo chown deploy:www-data /var/www/barbercuts   # als beheerder
cd /var/www/barbercuts
mkdir -p releases shared/storage/{app/public,framework/{cache/data,sessions,views},logs}
git clone --bare git@github.com:<eigenaar>/barbercuts.git repo
```

> De server heeft leestoegang tot de repo nodig: maak op de server een SSH-key (`ssh-keygen -t ed25519`) en voeg de
> publieke key toe als **Deploy key** (alleen-lezen) in de GitHub-repo.

Kopieer de scripts en maak de `.env`:

```bash
git --git-dir=repo show main:deploy/deploy.sh > deploy.sh
git --git-dir=repo show main:deploy/rollback.sh > rollback.sh
chmod +x deploy.sh rollback.sh
git --git-dir=repo show main:.env.example > shared/.env
nano shared/.env
```

Zet in `shared/.env` minimaal:

```dotenv
APP_NAME=BarberCuts
APP_ENV=production            # staging: staging
APP_KEY=                      # zie hieronder
APP_DEBUG=false
APP_URL=https://barbercuts.nl # staging: https://staging.barbercuts.nl

DB_CONNECTION=mysql
DB_DATABASE=barbercuts
DB_USERNAME=barbercuts
DB_PASSWORD=...

SESSION_DRIVER=redis
SESSION_SECURE_COOKIE=true
QUEUE_CONNECTION=redis
CACHE_STORE=redis
REDIS_CLIENT=phpredis

FILESYSTEM_DISK=public        # later om te zetten naar S3-compatible opslag

MAIL_MAILER=smtp              # zie stap 11
MAIL_FROM_ADDRESS="hallo@barbercuts.nl"

BILLING_ENABLED=false
FEATURE_DEPOSITS=false
SMS_PROVIDER=log
```

## 5. Eerste deploy

```bash
cd /var/www/barbercuts
./deploy.sh origin/main
```

Bij de allereerste keer is `APP_KEY` nog leeg. Genereer hem één keer en deploy opnieuw:

```bash
cd /var/www/barbercuts/current && php artisan key:generate --show   # plak de waarde in shared/.env
cd /var/www/barbercuts && ./deploy.sh origin/main
```

Wat `deploy.sh` doet: code ophalen → nieuwe release-map → `composer install --no-dev --optimize-autoloader` →
`npm ci && npm run build` → symlinks naar `shared/.env` en `shared/storage` → `php artisan migrate --force` →
`php artisan optimize` → symlink `current` omzetten → `php artisan queue:restart` → PHP-FPM herladen →
oude releases opruimen (laatste 5 blijven staan).

**Rollback:** `./rollback.sh` zet de vorige release terug (migraties worden niet teruggedraaid).

## 6. Nginx

```bash
sudo cp deploy/nginx-barbercuts.conf /etc/nginx/sites-available/barbercuts.nl
sudo cp deploy/nginx-staging.conf    /etc/nginx/sites-available/staging.barbercuts.nl
sudo ln -s /etc/nginx/sites-available/barbercuts.nl /etc/nginx/sites-enabled/
sudo ln -s /etc/nginx/sites-available/staging.barbercuts.nl /etc/nginx/sites-enabled/
sudo rm -f /etc/nginx/sites-enabled/default
sudo htpasswd -c /etc/nginx/.htpasswd-staging barbercuts      # wachtwoord voor staging
sudo nginx -t && sudo systemctl reload nginx
```

De vhosts: [`deploy/nginx-barbercuts.conf`](../deploy/nginx-barbercuts.conf) en
[`deploy/nginx-staging.conf`](../deploy/nginx-staging.conf). Daarin: root op `current/public`, HTTP/2, gzip,
een jaar cache op `/build/*`, `client_max_body_size 10M` en `www` → zonder `www`.

Brotli (optioneel): `apt install libnginx-mod-http-brotli-filter` en zet `brotli on;` naast `gzip on;`.

## 7. SSL

```bash
sudo certbot --nginx -d barbercuts.nl -d www.barbercuts.nl
sudo certbot --nginx -d staging.barbercuts.nl
sudo certbot renew --dry-run          # vernieuwen gaat automatisch via een systemd-timer
```

Certbot zet zelf de `listen 443 ssl`-regels en de redirect van http naar https in de vhosts.

## 8. Queue (Supervisor)

```bash
sudo cp deploy/supervisor-barbercuts.conf /etc/supervisor/conf.d/barbercuts.conf
sudo supervisorctl reread && sudo supervisorctl update
sudo supervisorctl status
```

Zie [`deploy/supervisor-barbercuts.conf`](../deploy/supervisor-barbercuts.conf): 2 processen voor productie,
1 voor staging, `queue:work redis --tries=3 --max-time=3600`.

## 9. Scheduler (cron)

`crontab -e` als `deploy`:

```cron
* * * * * cd /var/www/barbercuts/current && php artisan schedule:run >> /dev/null 2>&1
* * * * * cd /var/www/barbercuts-staging/current && php artisan schedule:run >> /dev/null 2>&1
```

## 10. CI/CD (GitHub Actions)

De workflow staat in [`.github/workflows/ci.yml`](../.github/workflows/ci.yml): Pint, Larastan, Pest (met MySQL en
Redis) en Playwright bij elke push en PR. Deploy:

- **staging** bij elke push naar `main`
- **productie** bij een tag `v*` of handmatig (Actions → CI → *Run workflow* → "Deploy naar productie")

Instellen in GitHub → *Settings* → *Secrets and variables* → *Actions*:

| Soort | Naam | Waarde |
|---|---|---|
| Variable | `DEPLOY_ENABLED` | `true` (zonder deze variabele worden de deploy-jobs overgeslagen) |
| Secret | `DEPLOY_HOST` | IP of hostnaam van de VPS |
| Secret | `DEPLOY_SSH_KEY` | private key van een aparte deploy-key; de publieke key staat in `/home/deploy/.ssh/authorized_keys` |

Maak onder *Environments* de omgevingen `staging` en `production` aan; zet bij `production` eventueel een
verplichte goedkeuring.

## 11. Mail

Transactionele provider in de EU (Postmark of Mailgun EU). Zet de SMTP-gegevens in `shared/.env` en voeg bij de
DNS van `barbercuts.nl` toe: **SPF** (include van de provider), **DKIM** (de records die de provider geeft) en
**DMARC**:

```text
_dmarc.barbercuts.nl  TXT  "v=DMARC1; p=quarantine; rua=mailto:dmarc@barbercuts.nl"
```

Test: `php artisan tinker --execute 'Mail::raw("Test", fn ($m) => $m->to("jij@example.com")->subject("Test"));'`

## 12. Back-ups

Komt in de code zodra de VPS er staat (`spatie/laravel-backup`): dagelijks database + `storage/app` naar S3-compatible opslag
in de EU (Scaleway of Hetzner), 30 dagen bewaren. Zet dan in `shared/.env` de `AWS_*`-gegevens van de bucket.
Doe **maandelijks een terugzet-test** op staging.

Tot die tijd handmatig:

```bash
mysqldump --single-transaction barbercuts | gzip > ~/backup-$(date +%F).sql.gz
```

## 13. Monitoring

- **Uptime:** laat een uptime-checker (UptimeRobot, Better Stack, …) elke minuut `https://barbercuts.nl/up` controleren.
- **Fouten:** Sentry of Flare (DSN in `.env`), of mail via het `LOG_CHANNEL`.
- **Logs:** `LOG_CHANNEL=daily` en `LOG_DAILY_DAYS=14` in `.env` (Laravel roteert zelf).
- **Queue:** `php artisan queue:monitor redis:default --max=100` komt in de scheduler zodra er jobs zijn (fase 2).

## 14. Controlelijst na een deploy

```bash
curl -I https://barbercuts.nl/up                  # 200
curl -I https://www.barbercuts.nl                 # 301 → https://barbercuts.nl
curl -I https://staging.barbercuts.nl             # 401 (basic-auth)
sudo supervisorctl status                         # RUNNING
cd /var/www/barbercuts/current && php artisan about
```

## Bestanden (uploads)

De `public` disk op de server is prima voor de start (zit in `shared/storage`, dus blijft staan bij een deploy en
gaat mee in de back-up). Omzetten naar S3-compatible opslag kan later via `FILESYSTEM_DISK` in `.env`.
