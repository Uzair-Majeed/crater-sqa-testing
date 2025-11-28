FROM php:8.1-fpm

# ---------- Build arguments ----------
ARG user=laravel
ARG uid=1000
ARG gid=1000  # optional, for group

# ---------- Install system dependencies ----------
RUN apt-get update && apt-get install -y \
    git \
    curl \
    libpng-dev \
    libonig-dev \
    libxml2-dev \
    zip \
    unzip \
    libzip-dev \
    libmagickwand-dev \
    mariadb-client \
    && apt-get clean && rm -rf /var/lib/apt/lists/*

# Install PHP extensions
RUN pecl install imagick \
    && docker-php-ext-enable imagick

RUN docker-php-ext-install pdo_mysql mbstring zip exif pcntl bcmath gd

# Get latest Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# ---------- Create system user ----------
# Create group first
RUN groupadd -g $gid $user || true
# Create user with home directory
RUN useradd -u $uid -g $gid -m -s /bin/bash $user || true

# Ensure Composer cache directory exists
RUN mkdir -p /home/$user/.composer \
    && chown -R $user:$user /home/$user

# ---------- Set working directory ----------
WORKDIR /var/www

# Switch to non-root user
USER $user

# Optionally, copy project files later:
# COPY . /var/www


# For Railway, listen on port 8080
ENV PORT=8080
CMD php -S 0.0.0.0:$PORT -t public
