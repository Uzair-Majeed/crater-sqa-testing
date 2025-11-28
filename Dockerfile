FROM php:8.1-fpm

# ---------- Build arguments ----------
ARG user=laravel
ARG uid=1000
ARG gid=1000

# ---------- System dependencies ----------
RUN apt-get update && apt-get install -y \
    git curl libpng-dev libonig-dev libxml2-dev zip unzip libzip-dev libmagickwand-dev mariadb-client \
    && apt-get clean && rm -rf /var/lib/apt/lists/*

# ---------- PHP extensions ----------
RUN pecl install imagick && docker-php-ext-enable imagick
RUN docker-php-ext-install pdo_mysql mbstring zip exif pcntl bcmath gd

# ---------- Composer ----------
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# ---------- Create system user ----------
RUN groupadd -g $gid $user || true
RUN useradd -u $uid -g $gid -m -s /bin/bash $user || true
RUN mkdir -p /home/$user/.composer && chown -R $user:$user /home/$user

# ---------- Working directory ----------
WORKDIR /var/www

# ---------- Copy application code ----------
COPY . /var/www

# =======================================================
# 🚀 CRITICAL LARAVEL SETUP FIXES (Causes 500 Errors) 🚀
# =======================================================

# 1. Create the .env file from the example
RUN cp .env.example .env

# 2. Install PHP dependencies
RUN composer install --optimize-autoloader --no-dev

# 3. Generate Application Key (Essential for encryption and security)
RUN php artisan key:generate

# 4. Run Database Migrations
# This command applies the database schema defined in the app.
# It requires the DB environment variables to be set on Railway BEFORE the build finishes.
RUN php artisan migrate --force

# 5. Clear Caches (Optional, but good practice)
RUN php artisan config:cache
RUN php artisan route:cache

# 6. Storage Link (Essential for file uploads/avatars)
RUN php artisan storage:link

# =======================================================

# ---------- Fix permissions ----------
# Ensure the user has permission to write to necessary directories
RUN chown -R $user:$user /var/www
# Grant write permissions to storage and bootstrap/cache directories
RUN chmod -R 775 /var/www/storage /var/www/bootstrap/cache

# ---------- Switch to non-root user ----------
USER $user

# ---------- Railway/Runtime Settings (Already Correct for Public Access) ----------
# Binds to 0.0.0.0 and uses the PORT variable Railway injects.
ENV PORT=8080
EXPOSE ${PORT}
CMD php -S 0.0.0.0:$PORT -t public