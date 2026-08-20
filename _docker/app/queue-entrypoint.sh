#!/bin/sh
set -e

cd /var/www/backend

echo "=== Queue: waiting for app to initialize ==="
until [ -f vendor/autoload.php ]; do
  sleep 2
done

echo "=== Queue: waiting for RabbitMQ ==="
until php -r "exit(@stream_socket_client('tcp://rabbitmq:5672', \$errno, \$errstr, 2) !== false ? 0 : 1);"; do
  sleep 2
done

echo "=== Queue: starting ==="
php artisan comments:consume
