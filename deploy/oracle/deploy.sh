#!/usr/bin/env bash
# Aggiorna le API sulla VM all'ultimo main: lo lancia deploy-api.yml via SSH (o tu a mano).
set -euo pipefail

APP_DIR=/srv/duemani

cd "$APP_DIR"
git fetch --depth 1 origin main
git reset --hard origin/main

cd api
composer install --no-dev --optimize-autoloader --no-interaction --no-progress

php artisan down --retry=15 || true
# Anche se una migration fallisce l'API torna su
trap 'php artisan up' EXIT
php artisan migrate --force
php artisan config:cache
php artisan route:cache
php artisan view:cache

# Log e cache scritti da qui (utente ubuntu) devono restare scrivibili da PHP-FPM (www-data)
sudo chgrp -R www-data storage bootstrap/cache
sudo chmod -R ug+rwX storage bootstrap/cache

sudo systemctl reload php8.5-fpm

echo "Deploy fatto: $(git -C "$APP_DIR" log -1 --format='%h %s')"
