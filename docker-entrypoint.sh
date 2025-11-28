#!/bin/bash
set -e

# Ensure .env exists
if [ ! -f /var/www/.env ]; then
    cp /var/www/.env.example /var/www/.env
    php artisan key:generate
fi

# Wait for DB to be ready (optional)
until php artisan migrate:status >/dev/null 2>&1; do
  echo "Waiting for database..."
  sleep 2
done

# Run migrations
php artisan migrate --force

# Clear caches
php artisan config:cache
php artisan route:cache
php artisan view:cache

# Start PHP built-in server
php -S 0.0.0.0:$PORT -t public
