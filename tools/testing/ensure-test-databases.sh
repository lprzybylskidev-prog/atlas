#!/usr/bin/env bash
set -euo pipefail

lane="${1:-all}"

env_or_file() {
  local name="$1"
  local fallback="$2"
  local current="${!name:-}"

  if [[ -n "$current" ]]; then
    printf '%s' "$current"
    return
  fi

  if [[ -f .env ]]; then
    local line
    line="$(grep -m 1 "^${name}=" .env || true)"

    if [[ -n "$line" ]]; then
      local value="${line#*=}"
      value="${value%\"}"
      value="${value#\"}"
      printf '%s' "$value"
      return
    fi
  fi

  printf '%s' "$fallback"
}

DB_HOST="$(env_or_file DB_HOST 127.0.0.1)"
DB_PORT="$(env_or_file DB_PORT 5432)"
DB_USERNAME="$(env_or_file DB_USERNAME atlas)"
DB_PASSWORD="$(env_or_file DB_PASSWORD atlas_dev_password)"
POSTGRES_MAINTENANCE_DATABASE="$(env_or_file POSTGRES_MAINTENANCE_DATABASE postgres)"

databases=()
case "$lane" in
  all)
    databases=(atlas_testing atlas_e2e)
    ;;
  phpunit)
    databases=(atlas_testing)
    ;;
  e2e)
    databases=(atlas_e2e)
    ;;
  *)
    echo "Unknown testing database lane [$lane]. Expected: all, phpunit, or e2e." >&2
    exit 1
    ;;
esac

database_exists() {
  local database="$1"

  PGPASSWORD="$DB_PASSWORD" psql \
    --host="$DB_HOST" \
    --port="$DB_PORT" \
    --username="$DB_USERNAME" \
    --dbname="$POSTGRES_MAINTENANCE_DATABASE" \
    --tuples-only \
    --no-align \
    --command="select 1 from pg_database where datname = '${database}';" \
    | grep --quiet '^1$'
}

create_database() {
  local database="$1"

  if [[ ! "$database" =~ ^[a-z0-9_]+$ ]]; then
    echo "Refusing to create invalid database name [$database]." >&2
    exit 1
  fi

  if database_exists "$database"; then
    return
  fi

  PGPASSWORD="$DB_PASSWORD" createdb \
    --host="$DB_HOST" \
    --port="$DB_PORT" \
    --username="$DB_USERNAME" \
    --owner="$DB_USERNAME" \
    "$database"
}

reset_atlas_schemas() {
  local database="$1"
  local schemas=(
    core_identity
    core_teams
    core_authorization
    core_audit
    core_settings
    core_notifications
    core_files
    core_privacy
    core_exports
    optional_integrations
    optional_managed_processes
    optional_imports
    optional_feature_flags
    optional_time_tracking
    shared
  )
  local schema

  for schema in "${schemas[@]}"; do
    PGPASSWORD="$DB_PASSWORD" psql \
      --host="$DB_HOST" \
      --port="$DB_PORT" \
      --username="$DB_USERNAME" \
      --dbname="$database" \
      --quiet \
      --command="set client_min_messages to warning; drop schema if exists \"${schema}\" cascade;"
  done
}

for database in "${databases[@]}"; do
  create_database "$database"
  reset_atlas_schemas "$database"
done
