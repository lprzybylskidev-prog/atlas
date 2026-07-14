FROM node:24-bookworm AS node

FROM php:8.5-cli-bookworm

ARG USERNAME=vscode
ARG USER_UID=1000
ARG USER_GID=1000
ARG DOCKER_GID=998

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
      bubblewrap \
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
      python-is-python3 \
      python3 \
      python3-pip \
      python3-venv \
      redis-tools \
      sudo \
      unzip \
      wget \
      zip \
    && install -m 0755 -d /etc/apt/keyrings \
    && curl -fsSL https://download.docker.com/linux/debian/gpg -o /etc/apt/keyrings/docker.asc \
    && chmod a+r /etc/apt/keyrings/docker.asc \
    && echo "deb [arch=$(dpkg --print-architecture) signed-by=/etc/apt/keyrings/docker.asc] https://download.docker.com/linux/debian bookworm stable" > /etc/apt/sources.list.d/docker.list \
    && apt-get update \
    && apt-get install -y --no-install-recommends \
      docker-ce-cli \
      docker-compose-plugin \
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
    && if getent group docker >/dev/null; then groupmod --gid "${DOCKER_GID}" docker; elif getent group "${DOCKER_GID}" >/dev/null; then groupadd --non-unique --gid "${DOCKER_GID}" docker; else groupadd --gid "${DOCKER_GID}" docker; fi \
    && useradd --uid "${USER_UID}" --gid "${USER_GID}" --shell /bin/bash -m "${USERNAME}" \
    && usermod --append --groups docker "${USERNAME}" \
    && echo "${USERNAME} ALL=(root) NOPASSWD:ALL" > "/etc/sudoers.d/${USERNAME}" \
    && chmod 0440 "/etc/sudoers.d/${USERNAME}" \
    && mkdir -p /workspace "/home/${USERNAME}/.composer" "/home/${USERNAME}/.local/share/pnpm" \
    && { \
      echo '"\\e[A": history-search-backward'; \
      echo '"\\e[B": history-search-forward'; \
      echo '"\\e[1;5C": forward-word'; \
      echo '"\\e[1;5D": backward-word'; \
    } > "/home/${USERNAME}/.inputrc" \
    && { \
      echo 'export SHELL=/bin/bash'; \
      echo 'export HISTSIZE=50000'; \
      echo 'export HISTFILESIZE=100000'; \
      echo 'export HISTCONTROL=ignoreboth:erasedups'; \
      echo 'shopt -s histappend'; \
      echo 'PROMPT_COMMAND="history -a; history -n${PROMPT_COMMAND:+; $PROMPT_COMMAND}"'; \
      echo 'alias ll="ls -lah"'; \
      echo 'alias gs="git status --short --branch"'; \
      echo 'alias art="php artisan"'; \
    } > "/home/${USERNAME}/.bash_aliases" \
    && chown -R "${USERNAME}:${USERNAME}" /workspace "/home/${USERNAME}" /ms-playwright \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/* /tmp/pear

WORKDIR /workspace

USER ${USERNAME}
