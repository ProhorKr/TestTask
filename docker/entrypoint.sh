#!/bin/sh
set -eu
cd /var/www/html
mkdir -p storage/framework/cache/data storage/framework/sessions storage/framework/views storage/logs bootstrap/cache
if [ ! -s storage/app.key ]; then
    php -r 'file_put_contents("storage/app.key", "base64:".base64_encode(random_bytes(32)));'
fi
export APP_KEY="$(cat storage/app.key)"
touch "${DB_DATABASE:-/var/www/html/storage/database.sqlite}"
php artisan migrate --force --no-interaction
chown -R www-data:www-data storage bootstrap/cache
chmod 600 storage/app.key
exec "$@"
