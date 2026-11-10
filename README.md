# BarberCuts

Platform voor kapperszaken: afspraken, agenda per kapper, klanten en omzet. Laravel 13 · Livewire 4 · Tailwind v4.

- **Opdracht en beslissingen:** [docs/BOUWOPDRACHT.md](docs/BOUWOPDRACHT.md) (keuzes van de bouwer in §15)
- **Deploy op de VPS:** [docs/DEPLOY.md](docs/DEPLOY.md)

## Lokaal starten

Nodig: PHP 8.4, Composer, Node 22+, MySQL 8.

```bash
composer install
cp .env.example .env && php artisan key:generate
mysql -uroot -e "CREATE DATABASE barbercuts; CREATE DATABASE barbercuts_test;"
php artisan migrate
npm install && npm run build
composer run dev
```

Zonder Redis: zet in `.env` `SESSION_DRIVER`, `QUEUE_CONNECTION` en `CACHE_STORE` op `database`.

De showcase van alle UI-componenten staat (alleen lokaal) op `/_ui`, `/_ui/app` en `/_ui/shop`.

## Controles

```bash
php artisan test --compact        # Pest (MySQL: barbercuts_test)
vendor/bin/pint                   # codestijl
vendor/bin/phpstan analyse        # Larastan niveau 6
npm run test:browser              # Playwright op 375×667 en 430×932, screenshots in docs/screenshots
```
