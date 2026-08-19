#!/bin/sh
set -e

cd /var/www/backend

echo "=== Reverb: waiting for app to initialize ==="
until [ -f vendor/autoload.php ]; do
  sleep 2
done

echo "=== Reverb: starting ==="
php artisan reverb:start
