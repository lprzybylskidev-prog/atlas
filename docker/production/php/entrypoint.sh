#!/usr/bin/env bash
set -euo pipefail

secret_names=(
  APP_KEY
  DB_PASSWORD
  REDIS_PASSWORD
  MEILISEARCH_KEY
  MAIL_PASSWORD
  SENTRY_LARAVEL_DSN
  ATLAS_FILES_S3_ACCESS_KEY_ID
  ATLAS_FILES_S3_SECRET_ACCESS_KEY
)

for name in "${secret_names[@]}"; do
  file_name="${name}_FILE"
  file_path="${!file_name:-}"

  if [[ -z "${file_path}" ]]; then
    continue
  fi

  if [[ -n "${!name:-}" ]]; then
    printf 'Both %s and %s are set; choose exactly one secret source.\n' "${name}" "${file_name}" >&2
    exit 78
  fi

  if [[ ! -r "${file_path}" ]]; then
    printf 'Secret file configured by %s is not readable.\n' "${file_name}" >&2
    exit 78
  fi

  printf -v "${name}" '%s' "$(<"${file_path}")"
  export "${name}"
done

require_non_empty() {
  local name="$1"

  if [[ -z "${!name:-}" ]]; then
    printf 'Required production variable %s is empty.\n' "${name}" >&2
    exit 78
  fi
}

require_boolean() {
  local name="$1"

  case "${!name:-}" in
    true|false|1|0) ;;
    *)
      printf 'Production variable %s must be a boolean.\n' "${name}" >&2
      exit 78
      ;;
  esac
}

require_port() {
  local name="$1"
  local value="${!name:-}"

  if [[ ! "${value}" =~ ^[0-9]+$ ]] || (( value < 1 || value > 65535 )); then
    printf 'Production variable %s must be a TCP port.\n' "${name}" >&2
    exit 78
  fi
}

if [[ "${APP_ENV:-}" == "production" ]]; then
  for name in \
    APP_KEY APP_URL APP_TIMEZONE ATLAS_RELEASE_VERSION ATLAS_RELEASE_ID \
    DB_HOST DB_PORT DB_DATABASE DB_USERNAME DB_PASSWORD DB_SEARCH_PATH \
    REDIS_HOST REDIS_PORT REDIS_PASSWORD MEILISEARCH_HOST MEILISEARCH_KEY \
    MAIL_MAILER MAIL_HOST MAIL_PORT MAIL_FROM_ADDRESS MAIL_FROM_NAME \
    ATLAS_FILES_DISK ATLAS_FILES_SCANNER; do
    require_non_empty "${name}"
  done

  require_boolean APP_DEBUG
  require_boolean PRODUCTION_DEPLOYED
  require_port DB_PORT
  require_port REDIS_PORT
  require_port MAIL_PORT

  [[ "${APP_DEBUG}" == "false" || "${APP_DEBUG}" == "0" ]] || { echo 'APP_DEBUG must be false in production.' >&2; exit 78; }
  [[ "${PRODUCTION_DEPLOYED}" == "true" || "${PRODUCTION_DEPLOYED}" == "1" ]] || { echo 'PRODUCTION_DEPLOYED must be true in production.' >&2; exit 78; }
  [[ "${APP_TIMEZONE}" == "Europe/Warsaw" ]] || { echo 'APP_TIMEZONE must be Europe/Warsaw.' >&2; exit 78; }
  [[ "${DB_SEARCH_PATH}" == "public" ]] || { echo 'DB_SEARCH_PATH must be the minimal public framework search path.' >&2; exit 78; }
  [[ "${ATLAS_FILES_SCANNER}" != "fake" ]] || { echo 'The fake file scanner is forbidden in production.' >&2; exit 78; }

  if [[ "${ATLAS_FILES_DISK}" == "atlas_files_s3" ]]; then
    for name in ATLAS_FILES_S3_ACCESS_KEY_ID ATLAS_FILES_S3_SECRET_ACCESS_KEY ATLAS_FILES_S3_BUCKET ATLAS_FILES_S3_DEFAULT_REGION; do
      require_non_empty "${name}"
    done
  fi
fi

if [[ "${ATLAS_RUNTIME_VALIDATE_ONLY:-false}" == "true" ]]; then
  exit 0
fi

if [[ "$(id -u)" == "0" ]]; then
  exec setpriv --reuid=www-data --regid=www-data --init-groups "$@"
fi

exec "$@"
