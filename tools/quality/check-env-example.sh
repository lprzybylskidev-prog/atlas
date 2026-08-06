#!/usr/bin/env bash
set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
ENV_EXAMPLE="${ROOT_DIR}/.env.example"

if [[ ! -f "${ENV_EXAMPLE}" ]]; then
  echo ".env.example is missing." >&2
  exit 1
fi

duplicates="$(
  awk '
    /^[[:space:]]*#/ { next }
    /^[[:space:]]*$/ { next }
    /^[A-Z0-9_]+=/ {
      key=$0
      sub(/=.*/, "", key)
      lines[key] = lines[key] ? lines[key] "," NR : NR
      counts[key]++
    }
    END {
      for (key in counts) {
        if (counts[key] > 1) {
          printf "%s:%s\n", key, lines[key]
        }
      }
    }
  ' "${ENV_EXAMPLE}" | sort
)"

if [[ -n "${duplicates}" ]]; then
  echo ".env.example contains duplicate active keys:" >&2
  while IFS=: read -r key lines; do
    printf ' - %s on lines %s\n' "${key}" "${lines}" >&2
  done <<< "${duplicates}"
  exit 1
fi
