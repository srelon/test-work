#!/bin/sh
set -e

cd /var/www/backend

echo "=== Scheduler: waiting for app to initialize ==="
until [ -f vendor/autoload.php ]; do
  sleep 2
done

echo "=== Scheduler: starting ==="
php artisan schedule:work
