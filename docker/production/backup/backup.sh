#!/usr/bin/env bash
set -euo pipefail
umask 077

read_password() {
  if [[ -n "${PGPASSWORD_FILE:-}" ]]; then
    [[ -r "${PGPASSWORD_FILE}" ]] || { echo 'PGPASSWORD_FILE is not readable.' >&2; exit 78; }
    PGPASSWORD="$(<"${PGPASSWORD_FILE}")"
    export PGPASSWORD
  fi

  [[ -n "${PGPASSWORD:-}" ]] || { echo 'A PostgreSQL password secret is required.' >&2; exit 78; }
}

require_connection() {
  local name
  for name in DB_HOST DB_DATABASE DB_USERNAME; do
    [[ -n "${!name:-}" ]] || { printf '%s is required.\n' "${name}" >&2; exit 78; }
  done
  [[ "${DB_PORT:-5432}" =~ ^[0-9]+$ ]] || { echo 'DB_PORT must be numeric.' >&2; exit 78; }
}

create_backup() {
  local backup_directory="${BACKUP_DIRECTORY:-/backups}"
  local timestamp target temporary

  [[ "${backup_directory}" == /backups || "${backup_directory}" == /backups/* ]] || {
    echo 'BACKUP_DIRECTORY must stay below /backups.' >&2
    exit 78
  }
  [[ -d "${backup_directory}" && -w "${backup_directory}" ]] || { echo 'Backup directory is not writable.' >&2; exit 73; }

  timestamp="$(date -u +%Y%m%dT%H%M%SZ)"
  target="${backup_directory}/atlas-${timestamp}.dump"
  temporary="${target}.partial"
  [[ ! -e "${target}" && ! -e "${temporary}" ]] || { echo 'Refusing to overwrite a backup artifact.' >&2; exit 73; }

  trap 'rm -f "${temporary}"' EXIT
  pg_dump \
    --host="${DB_HOST}" \
    --port="${DB_PORT:-5432}" \
    --username="${DB_USERNAME}" \
    --format=custom \
    --compress=9 \
    --file="${temporary}" \
    "${DB_DATABASE}"
  pg_restore --list "${temporary}" >/dev/null
  mv "${temporary}" "${target}"
  trap - EXIT

  printf '%s\n' "${target}"
}

verify_backup() {
  local artifact="${1:-}"
  [[ "${artifact}" == /backups/*.dump && -r "${artifact}" ]] || {
    echo 'verify requires a readable /backups/*.dump artifact.' >&2
    exit 64
  }
  pg_restore --list "${artifact}" >/dev/null
  printf 'Backup artifact is readable: %s\n' "${artifact}"
}

case "${1:-}" in
  create)
    require_connection
    read_password
    create_backup
    ;;
  verify)
    verify_backup "${2:-}"
    ;;
  *)
    echo 'Usage: atlas-backup create | atlas-backup verify /backups/<artifact>.dump' >&2
    exit 64
    ;;
esac
