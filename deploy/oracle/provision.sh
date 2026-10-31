#!/usr/bin/env bash
# Prepara da zero la VM Oracle Always Free (Ubuntu 24.04 ARM) per le API duemani.
# Si lancia una volta sola, come utente `ubuntu`:
#   curl -fsSL https://raw.githubusercontent.com/lorenzoalulithos/rasta/main/deploy/oracle/provision.sh | bash
# Si può rilanciare: salta quello che è già fatto. Passi completi in docs/deploy.md.
set -euo pipefail

REPO=https://github.com/lorenzoalulithos/rasta.git
APP_DIR=/srv/duemani
DB_NAME=duemani
DB_USER=duemani

say() { printf '\n\033[1;33m▸ %s\033[0m\n' "$1"; }

say "Pacchetti di sistema"
sudo apt-get update -y
sudo apt-get install -y software-properties-common ca-certificates curl gnupg git unzip debian-keyring debian-archive-keyring apt-transport-https netfilter-persistent

say "PHP 8.5 (PPA ondrej/php)"
sudo add-apt-repository -y ppa:ondrej/php
sudo apt-get update -y
sudo apt-get install -y php8.5-fpm php8.5-cli php8.5-mysql php8.5-mbstring php8.5-xml php8.5-bcmath php8.5-curl php8.5-zip php8.5-intl php8.5-gd

say "Composer"
if ! command -v composer >/dev/null; then
    EXPECTED=$(curl -fsSL https://composer.github.io/installer.sig)
    curl -fsSL https://getcomposer.org/installer -o /tmp/composer-setup.php
    ACTUAL=$(php -r "echo hash_file('sha384', '/tmp/composer-setup.php');")
    [ "$EXPECTED" = "$ACTUAL" ] || { echo "Checksum di Composer sbagliato" >&2; exit 1; }
    sudo php /tmp/composer-setup.php --install-dir=/usr/local/bin --filename=composer --quiet
    rm /tmp/composer-setup.php
fi

say "MySQL 8"
sudo apt-get install -y mysql-server
sudo systemctl enable --now mysql

say "Caddy"
if ! command -v caddy >/dev/null; then
    curl -1sLf 'https://dl.cloudsmith.io/public/caddy/stable/gpg.key' | sudo gpg --dearmor -o /usr/share/keyrings/caddy-stable-archive-keyring.gpg
    curl -1sLf 'https://dl.cloudsmith.io/public/caddy/stable/debian.deb.txt' | sudo tee /etc/apt/sources.list.d/caddy-stable.list >/dev/null
    sudo apt-get update -y
    sudo apt-get install -y caddy
fi

say "Firewall della VM: porte 80 e 443 (le immagini Oracle bloccano tutto tranne SSH)"
for PORT in 80 443; do
    if ! sudo iptables -C INPUT -p tcp --dport "$PORT" -m state --state NEW -j ACCEPT 2>/dev/null; then
        sudo iptables -I INPUT 6 -p tcp --dport "$PORT" -m state --state NEW -j ACCEPT
    fi
done
sudo netfilter-persistent save

say "Codice in $APP_DIR"
if [ ! -d "$APP_DIR/.git" ]; then
    sudo mkdir -p "$APP_DIR"
    sudo chown "$USER":"$USER" "$APP_DIR"
    git clone --depth 1 "$REPO" "$APP_DIR"
fi

say "Database e .env"
ENV_FILE="$APP_DIR/api/.env"
if [ ! -f "$ENV_FILE" ]; then
    DB_PASSWORD=$(openssl rand -hex 24)
    sudo mysql -e "CREATE DATABASE IF NOT EXISTS \`$DB_NAME\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
    sudo mysql -e "CREATE USER IF NOT EXISTS '$DB_USER'@'localhost' IDENTIFIED BY '$DB_PASSWORD';"
    sudo mysql -e "ALTER USER '$DB_USER'@'localhost' IDENTIFIED BY '$DB_PASSWORD'; GRANT ALL PRIVILEGES ON \`$DB_NAME\`.* TO '$DB_USER'@'localhost'; FLUSH PRIVILEGES;"

    cp "$APP_DIR/deploy/oracle/env.production" "$ENV_FILE"
    sed -i "s/^DB_PASSWORD=.*/DB_PASSWORD=$DB_PASSWORD/" "$ENV_FILE"
    chmod 640 "$ENV_FILE"
fi

say "Dipendenze PHP e permessi"
cd "$APP_DIR/api"
composer install --no-dev --optimize-autoloader --no-interaction --no-progress
grep -q '^APP_KEY=base64' "$ENV_FILE" || php artisan key:generate --force
sudo chgrp -R www-data storage bootstrap/cache "$ENV_FILE"
sudo chmod -R ug+rwX storage bootstrap/cache

say "Migration"
php artisan migrate --force
php artisan config:cache
php artisan route:cache
php artisan view:cache
sudo chgrp -R www-data storage bootstrap/cache
sudo chmod -R ug+rwX storage bootstrap/cache

say "Caddy con il dominio duemani.duckdns.org"
sudo cp "$APP_DIR/deploy/oracle/Caddyfile" /etc/caddy/Caddyfile
sudo mkdir -p /var/log/caddy
sudo chown caddy:caddy /var/log/caddy
# Caddy deve leggere il codice e parlare con PHP-FPM
sudo usermod -aG www-data caddy
sudo systemctl reload-or-restart php8.5-fpm
sudo systemctl reload-or-restart caddy

say "Fatto"
cat <<'NEXT'
Adesso, sempre qui sulla VM:
  cd /srv/duemani/api
  php artisan app:create-tenant "Nome azienda" email@esempio.it     # chiede la password
  php artisan db:seed --class=PainterCatalogSeeder --force           # carica il catalogo pittori

Prova: curl https://duemani.duckdns.org/api/ping
NEXT
