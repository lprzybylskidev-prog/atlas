FROM node:24-bookworm AS node

FROM php:8.5-fpm-bookworm

ENV APP_TIMEZONE=Europe/Warsaw \
    TZ=Europe/Warsaw \
    COMPOSER_ALLOW_SUPERUSER=1 \
    PLAYWRIGHT_SKIP_BROWSER_DOWNLOAD=1 \
    COREPACK_ENABLE_PROJECT_SPEC=0 \
    PNPM_CONFIG_MANAGE_PACKAGE_MANAGER_VERSIONS=false \
    PATH="/var/www/html/node_modules/.bin:${PATH}"

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer
COPY --from=node /usr/local /usr/local

RUN apt-get update \
    && apt-get install -y --no-install-recommends \
      ca-certificates \
      chromium \
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
    && npm install -g pnpm@latest \
    && usermod -u 1000 www-data \
    && groupmod -g 1000 www-data \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/* /tmp/pear

WORKDIR /var/www/html

COPY --chown=www-data:www-data . /var/www/html

RUN pnpm install --frozen-lockfile --prod

USER www-data
