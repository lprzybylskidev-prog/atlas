#!/usr/bin/env bash
set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
PRODUCTION_ENV="${ROOT_DIR}/docker/production/.env.example"
COMPOSE_FILE="${ROOT_DIR}/docker/production/compose.yml"

fail() {
  printf 'Runtime contract check failed: %s\n' "$1" >&2
  exit 1
}

package_manager="$(node -e "const p=require('${ROOT_DIR}/package.json'); process.stdout.write(p.packageManager || '')")"
[[ "${package_manager}" == "pnpm@11.18.0" ]] || fail 'package.json must pin pnpm@11.18.0.'

for dockerfile in docker/dev/app/Dockerfile docker/production/php/Dockerfile docker/production/nginx/Dockerfile; do
  rg -q 'PNPM_VERSION=11\.18\.0' "${ROOT_DIR}/${dockerfile}" || fail "${dockerfile} does not pin pnpm 11.18.0."
  ! rg -q 'pnpm@latest|npm install -g pnpm(@latest)?' "${ROOT_DIR}/${dockerfile}" || fail "${dockerfile} installs floating pnpm."
done

for pin in \
  'docker/production/nginx/Dockerfile:NGINX_VERSION=1.29.8-alpine' \
  'docker/production/backup/Dockerfile:FROM postgres:18.4-bookworm' \
  'docker/production/compose.yml:image: postgres:18.4-bookworm' \
  'docker/production/compose.yml:image: redis:8.4.0-bookworm' \
  'docker/production/compose.yml:image: getmeili/meilisearch:v1.15.2'; do
  file="${pin%%:*}"
  expected="${pin#*:}"
  rg -F -q "${expected}" "${ROOT_DIR}/${file}" || fail "${file} lost the pinned ${expected} base image contract."
done

rg -F -q 'image: clamav/clamav:1.4.5-debian13-slim' "${COMPOSE_FILE}" \
  || fail 'Production ClamAV image is not pinned.'

! rg -n '^COPY[[:space:]]+(--[^[:space:]]+[[:space:]]+)*\.[[:space:]]' \
  "${ROOT_DIR}/docker/production/php/Dockerfile" \
  "${ROOT_DIR}/docker/production/nginx/Dockerfile" >/dev/null \
  || fail 'Production Dockerfiles must use an explicit COPY allowlist.'

for ignored in .git .env vendor node_modules public/build storage tests docs playwright-report docker/production/secrets/\*.txt; do
  rg -F -q "${ignored}" "${ROOT_DIR}/.dockerignore" || fail ".dockerignore does not exclude ${ignored}."
done

if rg -n 'DB_SEARCH_PATH=[^[:space:]]*,' \
  "${ROOT_DIR}/.env.example" "${PRODUCTION_ENV}" "${COMPOSE_FILE}" >/dev/null; then
  fail 'A broad PostgreSQL search path is configured.'
fi

for template in .env.example phpunit.xml playwright.config.ts docker/production/.env.example; do
  [[ -f "${ROOT_DIR}/${template}" ]] || fail "Environment contract ${template} is missing."
done

for secret in APP_KEY DB_PASSWORD REDIS_PASSWORD MEILISEARCH_KEY MAIL_PASSWORD SENTRY_LARAVEL_DSN ATLAS_FILES_S3_ACCESS_KEY_ID ATLAS_FILES_S3_SECRET_ACCESS_KEY; do
  rg -q "^[[:space:]]+${secret}$" "${ROOT_DIR}/docker/production/php/entrypoint.sh" \
    || fail "Runtime entrypoint does not register ${secret} for _FILE loading."
done
rg -F -q 'file_name="${name}_FILE"' "${ROOT_DIR}/docker/production/php/entrypoint.sh" \
  || fail 'Runtime entrypoint does not implement the generic _FILE convention.'

compose_config="$(docker compose --profile operations --env-file "${PRODUCTION_ENV}" -f "${COMPOSE_FILE}" config)"
grep -q 'PGDATA: /var/lib/postgresql/18/docker' <<<"${compose_config}" || fail 'PostgreSQL 18 PGDATA is not explicit.'
grep -q 'source: postgres-data' <<<"${compose_config}" || fail 'PostgreSQL does not use its durable named volume.'
grep -q 'target: /var/lib/postgresql' <<<"${compose_config}" || fail 'PostgreSQL volume is mounted at the PG18 parent boundary.'
grep -q 'target: 8080' <<<"${compose_config}" || fail 'Internal nginx HTTP port 8080 is missing.'
! grep -q 'target: 443' <<<"${compose_config}" || fail 'Compose falsely publishes HTTPS without a TLS listener.'
grep -q 'DB_SEARCH_PATH: public' <<<"${compose_config}" || fail 'Production DB_SEARCH_PATH is not minimal.'
grep -q 'profiles:' <<<"${compose_config}" || fail 'The preliminary backup interface must be opt-in.'
grep -q 'ATLAS_FILES_SCANNER: clamav' <<<"${compose_config}" || fail 'Production Files scanner must be real ClamAV.'
grep -q 'ATLAS_HEALTH_CLAMAV_CRITICAL: "true"' <<<"${compose_config}" || fail 'Production ClamAV readiness must be blocking.'
grep -q 'ATLAS_HEALTH_CHROMIUM_CRITICAL: "true"' <<<"${compose_config}" || fail 'Production Chromium/PDF readiness must be blocking.'
grep -q 'ATLAS_CHROMIUM_BINARY: /usr/bin/chromium' <<<"${compose_config}" || fail 'Production Chromium binary is not explicit.'

canonical_queues='managed-processes,imports,exports,search,files,files-large,default'
rg -F -q "'queue' => ['managed-processes', 'imports', 'exports', 'search', 'files', 'files-large', 'default']" "${ROOT_DIR}/config/horizon.php" \
  || fail "Horizon queue list drifted from the canonical runtime order (${canonical_queues})."
rg -F -q 'command: ["php", "artisan", "horizon"]' "${COMPOSE_FILE}" \
  || fail 'Production worker is not managed by Horizon.'
rg -F -q 'command: ["php", "artisan", "horizon"]' "${ROOT_DIR}/.devcontainer/docker-compose.yml" \
  || fail 'Development worker is not managed by Horizon.'
[[ "$(rg -F -c 'user: "${USER_UID:-1000}:${USER_GID:-1000}"' "${ROOT_DIR}/.devcontainer/docker-compose.yml")" == "3" ]] \
  || fail 'Development PHP-FPM, scheduler, and worker must use the bind-mount owner UID/GID.'
rg -F -q 'PGADMIN_DEFAULT_EMAIL: ${PGADMIN_DEFAULT_EMAIL:-admin@atlas.example.com}' "${ROOT_DIR}/.devcontainer/docker-compose.yml" \
  || fail 'Development pgAdmin must use a default email accepted by pgAdmin validation.'
rg -F -q '\"php artisan horizon\"' "${ROOT_DIR}/composer.json" \
  || fail 'composer dev is not managed by Horizon.'

retry_after="$(sed -nE "s/.*env\('QUEUE_RETRY_AFTER', ([0-9]+)\).*/\1/p" "${ROOT_DIR}/config/queue.php")"
worker_timeout="$(sed -nE "s/.*'timeout' => ([0-9]+),.*/\1/p" "${ROOT_DIR}/config/horizon.php" | head -n 1)"
[[ -n "${retry_after}" && -n "${worker_timeout}" ]] || fail 'Queue timeout contract could not be read.'
(( retry_after > worker_timeout )) || fail 'Redis retry_after must exceed the Horizon worker timeout.'

rg -q 'public/build' "${ROOT_DIR}/docker/production/nginx/Dockerfile" || fail 'Nginx image does not contain built Vite assets.'
rg -q 'chromiumSandbox: false' "${ROOT_DIR}/tools/reports/render-pdf.mjs" || fail 'The container Chromium sandbox mode is not explicit.'
rg -q 'Cache-Control "public, immutable"' "${ROOT_DIR}/docker/production/nginx/default.conf" || fail 'Nginx immutable asset caching is missing.'
rg -q 'listen 8080' "${ROOT_DIR}/docker/production/nginx/default.conf" || fail 'Nginx does not expose the internal HTTP listener.'

printf 'Runtime configuration contract is valid.\n'
