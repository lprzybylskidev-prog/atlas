#!/usr/bin/env bash
set -euo pipefail

if [[ -n "${PGPASSWORD_FILE:-}" && -f "${PGPASSWORD_FILE}" ]]; then
  export PGPASSWORD
  PGPASSWORD="$(cat "${PGPASSWORD_FILE}")"
fi

timestamp="$(date -u +%Y%m%dT%H%M%SZ)"
target="/backups/atlas-${timestamp}.dump"

pg_dump \
  --host="${DB_HOST}" \
  --port="${DB_PORT:-5432}" \
  --username="${DB_USERNAME}" \
  --format=custom \
  --file="${target}" \
  "${DB_DATABASE}"

printf 'Created backup: %s\n' "${target}"
