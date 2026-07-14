FROM node:24-bookworm AS node

FROM php:8.5-cli-bookworm

ARG USERNAME=vscode
ARG USER_UID=1000
ARG USER_GID=1000

ENV APP_TIMEZONE=Europe/Warsaw \
    TZ=Europe/Warsaw \
    COMPOSER_ALLOW_SUPERUSER=1 \
    PLAYWRIGHT_BROWSERS_PATH=/ms-playwright \
    PATH="/home/${USERNAME}/.composer/vendor/bin:/workspace/node_modules/.bin:${PATH}"

COPY --from=node /usr/local /usr/local
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

RUN apt-get update \
    && apt-get install -y --no-install-recommends \
      bash-completion \
      ca-certificates \
      curl \
      git \
      gnupg \
      libfreetype6-dev \
      libicu-dev \
      libjpeg62-turbo-dev \
      libldap2-dev \
      libonig-dev \
      libpng-dev \
      libpq-dev \
      libssl-dev \
      libxml2-dev \
      libxslt1-dev \
      libzip-dev \
      postgresql-client \
      procps \
      redis-tools \
      sudo \
      unzip \
      wget \
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
    && npx -y playwright@latest install --with-deps chromium firefox webkit \
    && groupadd --gid "${USER_GID}" "${USERNAME}" \
    && useradd --uid "${USER_UID}" --gid "${USER_GID}" -m "${USERNAME}" \
    && echo "${USERNAME} ALL=(root) NOPASSWD:ALL" > "/etc/sudoers.d/${USERNAME}" \
    && chmod 0440 "/etc/sudoers.d/${USERNAME}" \
    && mkdir -p /workspace "/home/${USERNAME}/.composer" "/home/${USERNAME}/.local/share/pnpm" \
    && chown -R "${USERNAME}:${USERNAME}" /workspace "/home/${USERNAME}" /ms-playwright \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/* /tmp/pear

WORKDIR /workspace

USER ${USERNAME}
