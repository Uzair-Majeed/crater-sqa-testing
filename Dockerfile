FROM php:8.1-fpm

# ---------- Build arguments ----------
# Set the user and group IDs for the application user
ARG user=laravel
ARG uid=1000
ARG gid=1000

# ---------- System dependencies and PHP extensions (Run as root) ----------
RUN apt-get update && apt-get install -y \
    git curl libpng-dev libonig-dev libxml2-dev zip unzip libzip-dev \
    libmagickwand-dev mariadb-client \
    # Clean up APT caches to reduce image size
    && apt-get clean && rm -rf /var/lib/apt/lists/*

# Install ImageMagick for PDF and image processing
RUN pecl install imagick && docker-php-ext-enable imagick

# Install required PHP extensions
RUN docker-php-ext-install pdo_mysql mbstring zip exif pcntl bcmath gd

# ---------- Composer installation ----------
# Copy Composer from the official dedicated image
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# ---------- Create system user (The 'laravel' user) ----------
RUN groupadd -g $gid $user || true
RUN useradd -u $uid -g $gid -m -s /bin/bash $user || true
RUN mkdir -p /home/$user/.composer && chown -R $user:$user /home/$user

# ---------- Set application working directory and copy code ----------
WORKDIR /var/www

# Copy the entire application source code into the container
COPY . /var/www

# =======================================================
# 🚀 CRITICAL LARAVEL SETUP FIXES (Build-Time Execution) 🚀
# =======================================================

# 1. Ensure .env file exists for Composer/Artisan (must be done first)
RUN cp .env.example .env

# 2. Fix permissions before running Composer and Artisan.
# This prevents permission errors when Composer attempts to write cache/vendor files.
RUN chown -R $user:$user /var/www
RUN chmod -R 775 /var/www/storage /var/www/bootstrap/cache

# 3. Install PHP dependencies
# Use --prefer-dist for faster builds and --no-dev for production
RUN composer install --optimize-autoloader --no-dev --prefer-dist

# 4. Generate Application Key (Requires dependencies to be installed)
# --force is used to confirm key generation in non-interactive mode
RUN php artisan key:generate --force

# 5. Run Database Migrations
# This command requires DB environment variables to be set on Railway
RUN php artisan migrate --force

# 6. Optimize and Cache
RUN php artisan config:cache
RUN php artisan route:cache
RUN php artisan view:cache

# 7. Storage Link (Essential for file uploads/avatars)
RUN php artisan storage:link

# =======================================================

# ---------- Switch to the non-root user for security ----------
USER $user

# ---------- Runtime Settings (Crater uses a simple PHP web server) ----------
# Railway will inject the PORT variable. Use 8080 as a standard default.
ENV PORT=8080
EXPOSE ${PORT}

# CMD uses the exec form, which is best practice for Docker to handle signals correctly.
# This command starts the PHP built-in web server serving the 'public' directory.
CMD ["php", "-S", "0.0.0.0:8080", "-t", "public"]