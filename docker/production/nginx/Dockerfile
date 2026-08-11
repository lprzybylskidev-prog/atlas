# syntax=docker/dockerfile:1.12

ARG NODE_VERSION=24.15.0
ARG NGINX_VERSION=1.29.8-alpine
ARG PNPM_VERSION=11.18.0

FROM node:${NODE_VERSION}-bookworm AS frontend-build
ARG PNPM_VERSION
ENV COREPACK_ENABLE_PROJECT_SPEC=0 \
    PNPM_CONFIG_MANAGE_PACKAGE_MANAGER_VERSIONS=false
WORKDIR /build
RUN corepack enable && corepack prepare "pnpm@${PNPM_VERSION}" --activate
COPY package.json pnpm-lock.yaml pnpm-workspace.yaml ./
RUN pnpm install --frozen-lockfile
COPY resources ./resources
COPY public ./public
COPY eslint.config.js package.json prettier.config.js stylelint.config.js tsconfig*.json vite.config.js ./
RUN pnpm build

FROM nginx:${NGINX_VERSION}
RUN apk add --no-cache curl \
    && rm -f /etc/nginx/conf.d/default.conf
COPY docker/production/nginx/default.conf /etc/nginx/conf.d/atlas.conf
COPY public /var/www/html/public
COPY --from=frontend-build /build/public/build /var/www/html/public/build
RUN find /var/www/html -type d -exec chmod 0755 {} + \
    && find /var/www/html -type f -exec chmod 0644 {} +
