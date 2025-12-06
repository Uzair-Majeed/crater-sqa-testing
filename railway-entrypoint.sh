#!/bin/bash
set -e

echo "========================================="
echo "Railway Deployment - Starting Application"
echo "========================================="

# Ensure .env exists
if [ ! -f /var/www/.env ]; then
    echo "Creating .env from example..."
    cp /var/www/.env.example /var/www/.env
    php artisan key:generate
fi

# Wait for database to be ready
echo "Waiting for database connection..."
until php artisan migrate:status >/dev/null 2>&1; do
  echo "Database not ready yet, retrying in 2 seconds..."
  sleep 2
done

echo "Database connection established!"

# Run migrations
echo "Running database migrations..."
php artisan migrate --force

# Seed database if empty (check if users table is empty)
USER_COUNT=$(php artisan tinker --execute="echo \App\Models\User::count();" 2>/dev/null || echo "0")
if [ "$USER_COUNT" = "0" ]; then
    echo "Database is empty, running seeders..."
    php artisan db:seed --force
else
    echo "Database already seeded, skipping..."
fi

# Clear and cache configurations
echo "Optimizing application..."
php artisan config:cache
php artisan route:cache
php artisan view:cache

echo "========================================="
echo "Application ready! Starting PHP server..."
echo "Listening on 0.0.0.0:$PORT"
echo "========================================="

# Start PHP built-in server
exec php -S 0.0.0.0:$PORT -t public
