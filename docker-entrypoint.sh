#!/bin/bash
set -e

# Wait for MariaDB to be ready
until mariadb -h"$DB_HOST" -u"$DB_USERNAME" -p"$DB_PASSWORD" -e ";" ; do
  echo "Waiting for MariaDB at $DB_HOST..."
  sleep 2
done

echo "MariaDB is ready."

# Drop and recreate the database
mariadb -h"$DB_HOST" -u"$DB_USERNAME" -p"$DB_PASSWORD" -e "DROP DATABASE IF EXISTS $DB_DATABASE; CREATE DATABASE $DB_DATABASE;"

echo "Database $DB_DATABASE recreated."

# Run Laravel migrations and seeders
php artisan migrate --force
php artisan db:seed --force

echo "Migrations and seeders executed."

# Execute default command (php-fpm)
exec "$@"
