#!/usr/bin/env bash
set -e

PORT="${PORT:-10000}"

echo "Starting Laravel on port ${PORT}"

sed -i "s/Listen 80/Listen ${PORT}/g" /etc/apache2/ports.conf
sed -i "s/:80/:${PORT}/g" /etc/apache2/sites-available/000-default.conf

php artisan config:clear
php artisan cache:clear
php artisan route:clear
php artisan view:clear

php artisan migrate --force || true

exec apache2-foreground
