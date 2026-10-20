#!/bin/bash
# Sign-Now Setup Script
# Run this once after cloning to bootstrap the Laravel app inside Docker.

set -e

echo "=== Building and starting containers ==="
docker-compose up -d --build mysql redis

echo "=== Waiting for MySQL to be healthy ==="
until docker-compose exec mysql mysqladmin ping -h localhost --silent 2>/dev/null; do
    sleep 2
done
echo "MySQL is ready."

echo "=== Creating Laravel project ==="
docker-compose run --rm --no-deps app bash -c "
    if [ ! -f composer.json ]; then
        composer create-project laravel/laravel /tmp/laravel-install --prefer-dist
        cp -a /tmp/laravel-install/. /var/www/html/
        rm -rf /tmp/laravel-install
    fi
"

echo "=== Installing Laravel Breeze ==="
docker-compose run --rm --no-deps app bash -c "
    composer require laravel/breeze --dev
    php artisan breeze:install blade --no-interaction
"

echo "=== Installing FPDI/TCPDF ==="
docker-compose run --rm --no-deps app bash -c "
    composer require setasign/fpdi tecnickcom/tcpdf
"

echo "=== Writing .env ==="
docker-compose run --rm --no-deps app bash -c "
cat > /var/www/html/.env << 'ENVEOF'
APP_NAME=SignNow
APP_ENV=local
APP_KEY=
APP_DEBUG=true
APP_URL=http://localhost:8027

LOG_CHANNEL=stack
LOG_LEVEL=debug

DB_CONNECTION=mysql
DB_HOST=mysql
DB_PORT=3306
DB_DATABASE=signnow
DB_USERNAME=signnow
DB_PASSWORD=secret

BROADCAST_DRIVER=log
CACHE_DRIVER=redis
FILESYSTEM_DISK=local
QUEUE_CONNECTION=redis
SESSION_DRIVER=redis
SESSION_LIFETIME=120

REDIS_HOST=redis
REDIS_PASSWORD=null
REDIS_PORT=6379

MAIL_MAILER=smtp
MAIL_HOST=mailhog
MAIL_PORT=1025
MAIL_USERNAME=null
MAIL_PASSWORD=null
MAIL_ENCRYPTION=null
MAIL_FROM_ADDRESS=noreply@signnow.test
MAIL_FROM_NAME=SignNow

WEBSOCKET_URL=ws://localhost:3027
ENVEOF

    php artisan key:generate
"

echo "=== Setting permissions ==="
docker-compose run --rm --no-deps app bash -c "
    chmod -R 775 storage bootstrap/cache
    chown -R www-data:www-data storage bootstrap/cache
"

echo "=== Starting all services ==="
docker-compose up -d --build

echo "=== Waiting for app container to be ready ==="
sleep 5

echo "=== Running migrations ==="
docker-compose exec app php artisan config:clear
docker-compose exec app php artisan migrate --force

echo "=== Installing Node.js dependencies ==="
docker-compose exec node npm install

echo ""
echo "================================================"
echo "  Setup Complete!"
echo "================================================"
echo "  App (Laravel)  : http://localhost:8027"
echo "  WebSocket      : ws://localhost:3027"
echo "  phpMyAdmin     : http://localhost:8127"
echo "  MailHog        : http://localhost:8227"
echo "  MySQL          : localhost:3327"
echo "  Redis          : localhost:6427"
echo "================================================"
echo "  Register your admin account at:"
echo "  http://localhost:8027/register"
echo "================================================"
