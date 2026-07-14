FROM php:8.5-fpm-bookworm

ENV APP_TIMEZONE=Europe/Warsaw \
    TZ=Europe/Warsaw \
    COMPOSER_ALLOW_SUPERUSER=1

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

RUN apt-get update \
    && apt-get install -y --no-install-recommends \
      ca-certificates \
      curl \
      libfreetype6-dev \
      libicu-dev \
      libjpeg62-turbo-dev \
      libonig-dev \
      libpng-dev \
      libpq-dev \
      libzip-dev \
      unzip \
      zip \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j"$(nproc)" bcmath \
    && docker-php-ext-install -j"$(nproc)" gd \
    && docker-php-ext-install -j"$(nproc)" intl \
    && docker-php-ext-install -j"$(nproc)" pcntl \
    && docker-php-ext-install -j"$(nproc)" pdo_pgsql \
    && docker-php-ext-install -j"$(nproc)" pgsql \
    && docker-php-ext-install -j"$(nproc)" zip \
    && pecl install redis \
    && docker-php-ext-enable redis \
    && usermod -u 1000 www-data \
    && groupmod -g 1000 www-data \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/* /tmp/pear

WORKDIR /var/www/html

COPY --chown=www-data:www-data . /var/www/html

USER www-data
