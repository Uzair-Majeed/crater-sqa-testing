#!/bin/bash
set -e

# Marker file to track if database has been initialized
MARKER_FILE="/var/www/storage/.db_initialized"

# Only initialize database on first run
if [ ! -f "$MARKER_FILE" ]; then
  echo "First run detected. Initializing database..."
  
  # Wait for MariaDB to be ready
  until mariadb -h"$DB_HOST" -u"$DB_USERNAME" -p"$DB_PASSWORD" -e ";" ; do
    echo "Waiting for MariaDB at $DB_HOST..."
    sleep 2
  done

  echo "MariaDB is ready."

  # Create database if it doesn't exist (no DROP)
  mariadb -h"$DB_HOST" -u"$DB_USERNAME" -p"$DB_PASSWORD" -e "CREATE DATABASE IF NOT EXISTS $DB_DATABASE;"

  echo "Database $DB_DATABASE ready."

  # Run Laravel migrations and seeders
  php artisan migrate --force
  php artisan db:seed --force

  echo "Migrations and seeders executed."
  
  # Create marker file to indicate initialization is complete
  touch "$MARKER_FILE"
  echo "Database initialization complete. Marker file created."
else
  echo "Database already initialized. Skipping setup."
fi

# Execute default command (php-fpm)
exec "$@"
