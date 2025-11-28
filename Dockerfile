# ---------- base image ----------
FROM php:8.1-fpm  # or whichever version you use

# install system dependencies (example, adjust as needed)
RUN apt-get update && apt-get install -y \
    git \
    unzip \
    libzip-dev \
    zip \
    curl \
    && docker-php-ext-install zip pdo pdo_mysql \
    && apt-get clean && rm -rf /var/lib/apt/lists/*

# ---------- Create a non-root user ----------
ARG APP_USER=laravel
ARG APP_UID=1000
ARG APP_GID=1000

RUN groupadd -g $APP_GID $APP_USER \
    && useradd -u $APP_UID -g $APP_GID -m -s /bin/bash $APP_USER

# ensure composer/cache dirs are owned
RUN mkdir -p /home/$APP_USER/.composer \
    && chown -R $APP_USER:$APP_USER /home/$APP_USER

# set working directory
WORKDIR /var/www/html

# copy project files
COPY . /var/www/html

# run composer as non-root
USER $APP_USER

RUN composer install --no-dev --optimize-autoloader

# change back to root if you need to set permissions or do other root tasks
USER root

# e.g. permissions for storage
RUN chown -R $APP_USER:$APP_USER /var/www/html/storage /var/www/html/bootstrap/cache

# switch to non-root again for running
USER $APP_USER

CMD ["php-fpm"]
