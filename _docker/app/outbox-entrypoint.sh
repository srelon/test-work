#!/bin/sh
set -e

cd /var/www/backend

echo "=== Outbox: waiting for app to initialize ==="
until [ -f vendor/autoload.php ]; do
  sleep 2
done

echo "=== Outbox: starting ==="
php artisan queue:work redis --queue=default
