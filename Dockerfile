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

# ---------- Install PHP dependencies ----------
RUN composer install --optimize-autoloader

# ---------- Fix permissions ----------
RUN chown -R $user:$user /var/www

# ---------- Switch to non-root user ----------
USER $user

# ---------- Railway settings ----------
ENV PORT=8080
CMD php -S 0.0.0.0:$PORT -t public
