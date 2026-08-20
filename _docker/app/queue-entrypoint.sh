#!/bin/sh
set -e

cd /var/www/backend

echo "=== Queue: waiting for app to initialize ==="
until [ -f vendor/autoload.php ]; do
  sleep 2
done

echo "=== Queue: starting ==="
php artisan comments:consume
