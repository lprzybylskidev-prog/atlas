#!/usr/bin/env bash
set -euo pipefail

env_file="${1:-.env}"

if [[ -f "${env_file}" ]]; then
  set -a
  # shellcheck disable=SC1090
  source <(grep -E '^[A-Za-z_][A-Za-z0-9_]*=' "${env_file}" | sed 's/\r$//')
  set +a
fi

required_vars=(
  APP_NAME
  APP_ENV
  APP_TIMEZONE
  PRODUCTION_DEPLOYED
  DB_CONNECTION
  DB_HOST
  DB_DATABASE
  DB_USERNAME
  DB_PASSWORD
  REDIS_HOST
  MEILISEARCH_HOST
)

missing=()

for var in "${required_vars[@]}"; do
  if [[ -z "${!var:-}" ]]; then
    missing+=("${var}")
  fi
done

if (( ${#missing[@]} > 0 )); then
  printf 'Missing required environment variables:\n' >&2
  printf ' - %s\n' "${missing[@]}" >&2
  exit 1
fi

if [[ "${APP_TIMEZONE}" != "Europe/Warsaw" ]]; then
  printf 'APP_TIMEZONE must default to Europe/Warsaw for Atlas bootstrap.\n' >&2
  exit 1
fi

if [[ "${PRODUCTION_DEPLOYED}" != "false" && "${PRODUCTION_DEPLOYED}" != "true" ]]; then
  printf 'PRODUCTION_DEPLOYED must be either true or false.\n' >&2
  exit 1
fi

if [[ -n "${DOCKER_GID:-}" && ! "${DOCKER_GID}" =~ ^[0-9]+$ ]]; then
  printf 'DOCKER_GID must be numeric when set.\n' >&2
  exit 1
fi

if [[ "${APP_ENV}" == "production" && "${PRODUCTION_DEPLOYED}" != "true" ]]; then
  printf 'Production environment requires PRODUCTION_DEPLOYED=true after first real deployment.\n' >&2
  exit 1
fi

printf 'Atlas environment validation passed for %s.\n' "${env_file}"
