# syntax=docker/dockerfile:1.12

ARG PHP_VERSION=8.5.8
ARG NODE_VERSION=24.15.0
ARG COMPOSER_VERSION=2.9.7
ARG PNPM_VERSION=11.18.0

FROM node:${NODE_VERSION}-bookworm AS frontend-dependencies
ARG PNPM_VERSION
ENV COREPACK_ENABLE_PROJECT_SPEC=0 \
    PNPM_CONFIG_MANAGE_PACKAGE_MANAGER_VERSIONS=false
WORKDIR /build
RUN corepack enable && corepack prepare "pnpm@${PNPM_VERSION}" --activate
COPY package.json pnpm-lock.yaml pnpm-workspace.yaml ./
RUN pnpm install --frozen-lockfile

FROM frontend-dependencies AS frontend-build
COPY resources ./resources
COPY public ./public
COPY eslint.config.js package.json prettier.config.js stylelint.config.js tsconfig*.json vite.config.js ./
RUN pnpm build

FROM frontend-dependencies AS frontend-runtime-dependencies
RUN pnpm prune --prod

FROM composer:${COMPOSER_VERSION} AS composer-binary

FROM php:${PHP_VERSION}-fpm-bookworm AS php-runtime
ARG NODE_VERSION
ENV APP_TIMEZONE=Europe/Warsaw \
    TZ=Europe/Warsaw \
    HOME=/tmp \
    XDG_CONFIG_HOME=/tmp/.config \
    XDG_CACHE_HOME=/tmp/.cache \
    PLAYWRIGHT_BROWSERS_PATH=/usr/bin \
    PLAYWRIGHT_SKIP_BROWSER_DOWNLOAD=1

RUN apt-get update \
    && apt-get install -y --no-install-recommends \
      ca-certificates \
      chromium \
      chromium-sandbox \
      curl \
      libfreetype6 \
      libfreetype6-dev \
      libicu72 \
      libicu-dev \
      libjpeg62-turbo \
      libjpeg62-turbo-dev \
      libonig5 \
      libonig-dev \
      libpng16-16 \
      libpng-dev \
      libpq5 \
      libpq-dev \
      libzip4 \
      libzip-dev \
      unzip \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j"$(nproc)" bcmath gd intl mbstring pcntl pdo_pgsql pgsql zip \
    && pecl install redis \
    && docker-php-ext-enable redis \
    && apt-get purge -y --auto-remove \
      libfreetype6-dev \
      libicu-dev \
      libjpeg62-turbo-dev \
      libonig-dev \
      libpng-dev \
      libpq-dev \
      libzip-dev \
    && rm -rf /var/lib/apt/lists/* /tmp/pear

COPY --from=frontend-dependencies /usr/local/bin/node /usr/local/bin/node
COPY --from=composer-binary /usr/bin/composer /usr/local/bin/composer-build

WORKDIR /var/www/html
COPY composer.json composer.lock ./
RUN composer-build install \
      --no-dev \
      --no-interaction \
      --no-progress \
      --no-scripts \
      --prefer-dist \
      --classmap-authoritative

COPY --chown=www-data:www-data app ./app
COPY --chown=www-data:www-data artisan ./artisan
COPY --chown=www-data:www-data bootstrap ./bootstrap
COPY --chown=www-data:www-data config ./config
COPY --chown=www-data:www-data database ./database
COPY --chown=www-data:www-data lang ./lang
COPY --chown=www-data:www-data public ./public
COPY --chown=www-data:www-data resources/views ./resources/views
COPY --chown=www-data:www-data routes ./routes
COPY --chown=www-data:www-data tools/reports ./tools/reports
COPY --from=frontend-build --chown=www-data:www-data /build/public/build ./public/build
COPY --from=frontend-runtime-dependencies --chown=www-data:www-data /build/node_modules ./node_modules
COPY --chown=root:root docker/production/php/php-fpm.conf /usr/local/etc/php-fpm.d/zz-atlas.conf
COPY --chown=root:root docker/production/php/entrypoint.sh /usr/local/bin/atlas-entrypoint

RUN chmod 0755 /usr/local/bin/atlas-entrypoint \
    && mkdir -p \
      storage/app/private \
      storage/framework/cache \
      storage/framework/sessions \
      storage/framework/views \
      storage/logs \
    && chown -R www-data:www-data storage bootstrap/cache \
    && composer-build dump-autoload --no-dev --classmap-authoritative --no-interaction --no-scripts \
    && rm -f /usr/local/bin/composer-build \
    && php artisan package:discover --ansi \
    && php artisan view:cache \
    && rm -f bootstrap/cache/config.php bootstrap/cache/routes-v7.php bootstrap/cache/events.php \
    && chmod 0755 artisan tools/reports/render-pdf.mjs

ENTRYPOINT ["atlas-entrypoint"]
CMD ["php-fpm", "-F"]
