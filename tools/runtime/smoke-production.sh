#!/usr/bin/env bash
set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
COMPOSE_FILE="${ROOT_DIR}/docker/production/compose.yml"
SMOKE_PARENT="${ROOT_DIR}/storage/framework/testing"
mkdir -p "${SMOKE_PARENT}"
SMOKE_DIR="$(mktemp -d "${SMOKE_PARENT}/runtime-smoke.XXXXXX")"
if [[ -n "${ATLAS_WORKSPACE_SOURCE:-}" ]]; then
  DOCKER_SMOKE_DIR="${ATLAS_WORKSPACE_SOURCE}/storage/framework/testing/$(basename "${SMOKE_DIR}")"
else
  DOCKER_SMOKE_DIR="${SMOKE_DIR}"
fi
PROJECT_NAME="atlas-p28-smoke-$RANDOM"
HTTP_PORT="${ATLAS_SMOKE_HTTP_PORT:-18080}"
ENV_FILE="${SMOKE_DIR}/runtime.env"

cleanup() {
  local status=$?
  if (( status != 0 )) && declare -p compose >/dev/null 2>&1; then
    "${compose[@]}" ps --all >&2 || true
    "${compose[@]}" logs --no-color --tail=100 >&2 || true
  fi
  docker compose --project-name "${PROJECT_NAME}" --env-file "${ENV_FILE}" -f "${COMPOSE_FILE}" down --volumes --remove-orphans >/dev/null 2>&1 || true
  rm -rf "${SMOKE_DIR}"
  return "${status}"
}
trap cleanup EXIT

write_secret() {
  local name="$1"
  local value="$2"
  printf '%s' "${value}" >"${SMOKE_DIR}/${name}"
  chmod 0600 "${SMOKE_DIR}/${name}"
}

write_secret app_key "base64:$(openssl rand -base64 32 | tr -d '\n')"
write_secret postgres_password 'atlas-smoke-postgres-password'
write_secret redis_password 'atlas-smoke-redis-password'
write_secret meilisearch_master_key 'atlas-smoke-meilisearch-master-key'
write_secret mail_password 'atlas-smoke-mail-password'
write_secret sentry_laravel_dsn ''
write_secret files_s3_access_key_id 'atlas-smoke-access-key'
write_secret files_s3_secret_access_key 'atlas-smoke-secret-key'

cat >"${ENV_FILE}" <<EOF
ATLAS_RELEASE_VERSION=0.1.0-smoke
ATLAS_RELEASE_ID=p28-smoke
APP_URL=http://127.0.0.1:${HTTP_PORT}
ATLAS_HTTP_BIND=127.0.0.1
ATLAS_HTTP_PORT=${HTTP_PORT}
DB_DATABASE=atlas
DB_USERNAME=atlas
MAIL_HOST=smtp.example.invalid
MAIL_PORT=587
MAIL_FROM_ADDRESS=no-reply@example.invalid
ATLAS_FILES_DISK=atlas_files
ATLAS_FILES_S3_BUCKET=
ATLAS_SECRET_APP_KEY_FILE=${DOCKER_SMOKE_DIR}/app_key
ATLAS_SECRET_POSTGRES_PASSWORD_FILE=${DOCKER_SMOKE_DIR}/postgres_password
ATLAS_SECRET_REDIS_PASSWORD_FILE=${DOCKER_SMOKE_DIR}/redis_password
ATLAS_SECRET_MEILISEARCH_KEY_FILE=${DOCKER_SMOKE_DIR}/meilisearch_master_key
ATLAS_SECRET_MAIL_PASSWORD_FILE=${DOCKER_SMOKE_DIR}/mail_password
ATLAS_SECRET_SENTRY_LARAVEL_DSN_FILE=${DOCKER_SMOKE_DIR}/sentry_laravel_dsn
ATLAS_SECRET_FILES_S3_ACCESS_KEY_ID_FILE=${DOCKER_SMOKE_DIR}/files_s3_access_key_id
ATLAS_SECRET_FILES_S3_SECRET_ACCESS_KEY_FILE=${DOCKER_SMOKE_DIR}/files_s3_secret_access_key
EOF

compose=(docker compose --project-name "${PROJECT_NAME}" --env-file "${ENV_FILE}" -f "${COMPOSE_FILE}")

"${ROOT_DIR}/tools/quality/check-runtime-contract.sh"
"${compose[@]}" build php-fpm nginx backup

runtime_image="atlas-runtime:p28-smoke"
nginx_image="atlas-nginx:p28-smoke"
backup_image="atlas-backup:p28-smoke"

[[ "$(docker run --rm "${runtime_image}" id -u)" == '33' ]] || {
  echo 'Runtime command does not execute as the non-root www-data user.' >&2
  exit 1
}

docker run --rm --entrypoint /bin/sh "${runtime_image}" -ec '
  test -f vendor/autoload.php
  test -f public/build/manifest.json
  test -x /usr/local/bin/node
  test ! -e tests
  test ! -e /usr/local/bin/composer
  ! command -v pnpm
  ! command -v npm
'

docker run --rm --entrypoint /bin/sh "${nginx_image}" -ec '
  test -f /var/www/html/public/build/manifest.json
  test ! -e /var/www/html/tests
'

if docker run --rm "${backup_image}" unsupported >/dev/null 2>&1; then
  echo 'Backup image accepted an unsafe unsupported operation.' >&2
  exit 1
fi

"${compose[@]}" up -d --wait postgres redis meilisearch clamav php-fpm
"${compose[@]}" run --rm --no-deps php-fpm php artisan migrate --force
"${compose[@]}" up -d --wait worker scheduler nginx

"${compose[@]}" exec -T nginx curl --fail --silent --show-error "http://127.0.0.1:8080/health/live" | grep -q '"status":"ok"'
"${compose[@]}" exec -T nginx curl --fail --silent --show-error "http://127.0.0.1:8080/health/ready" | grep -Eq '"status":"(healthy|degraded)"'
asset_path="$("${compose[@]}" exec -T nginx sh -ec "find /var/www/html/public/build/assets -type f | head -n 1 | sed 's#/var/www/html/public##'")"
[[ -n "${asset_path}" ]] || { echo 'No built Vite asset was found in nginx.' >&2; exit 1; }
"${compose[@]}" exec -T nginx curl --fail --silent --show-error --output /dev/null "http://127.0.0.1:8080${asset_path}"

"${compose[@]}" exec -T worker atlas-entrypoint php artisan horizon:status
"${compose[@]}" exec -T php-fpm atlas-entrypoint php artisan system:queue-smoke --timeout=90
"${compose[@]}" exec -T scheduler atlas-entrypoint php artisan system:scheduler-status
"${compose[@]}" exec -T meilisearch wget --no-verbose --spider http://127.0.0.1:7700/health

"${compose[@]}" exec -T clamav sh -ec "printf '%s' 'X5O!P%@AP[4\\PZX54(P^)7CC)7}\$EICAR-STANDARD-ANTIVIRUS-TEST-FILE!\$H+H*' >/tmp/atlas-eicar.com"
if "${compose[@]}" exec -T clamav clamdscan --no-summary /tmp/atlas-eicar.com; then
  echo 'ClamAV did not reject the EICAR smoke payload.' >&2
  exit 1
fi
"${compose[@]}" exec -T clamav rm -f /tmp/atlas-eicar.com

"${compose[@]}" exec -T php-fpm atlas-entrypoint sh -ec 'html="$(mktemp /tmp/atlas-pdf.XXXXXX.html)"; pdf="${html%.html}.pdf"; printf "%s" "<!doctype html><html><body><h1>Atlas PDF smoke</h1></body></html>" >"${html}"; timeout 120 node tools/reports/render-pdf.mjs "${html}" "${pdf}"; test -s "${pdf}"; head -c 5 "${pdf}" | grep -q "%PDF-"; rm -f "${html}" "${pdf}" "${pdf}.ok"'

"${compose[@]}" exec -T postgres psql -U atlas -d atlas -v ON_ERROR_STOP=1 -c \
  'CREATE TABLE public.p28_runtime_volume_probe (value text PRIMARY KEY); INSERT INTO public.p28_runtime_volume_probe VALUES ('"'"'persisted'"'"');' >/dev/null
"${compose[@]}" up -d --wait --force-recreate postgres
persisted="$("${compose[@]}" exec -T postgres psql -U atlas -d atlas -Atqc "SELECT value FROM public.p28_runtime_volume_probe")"
[[ "${persisted}" == 'persisted' ]] || { echo 'PostgreSQL data did not survive container recreation.' >&2; exit 1; }
"${compose[@]}" exec -T postgres psql -U atlas -d atlas -c 'DROP TABLE public.p28_runtime_volume_probe' >/dev/null

printf 'Production image and internal HTTP smoke stack passed.\n'
