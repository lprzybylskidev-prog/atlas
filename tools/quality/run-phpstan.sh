#!/usr/bin/env bash
set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
PHPSTAN_CONFIG="${ROOT_DIR}/phpstan.neon"
MEMORY_LIMIT="${PHPSTAN_MEMORY_LIMIT:-512M}"

configured_paths() {
  awk '
    /^[[:space:]]*paths:[[:space:]]*$/ { in_paths=1; next }
    in_paths && /^[^[:space:]]/ { in_paths=0 }
    in_paths && /^[[:space:]]*-[[:space:]]*/ {
      value=$0
      sub(/^[[:space:]]*-[[:space:]]*/, "", value)
      gsub(/["'\'']/, "", value)
      if (value != "") print value
    }
  ' "${PHPSTAN_CONFIG}"
}

append_existing_php_target() {
  local target="$1"

  if [[ -d "${ROOT_DIR}/${target}" ]]; then
    if find "${ROOT_DIR}/${target}" -type f -name '*.php' -print -quit | grep -q .; then
      printf '%s\n' "${target}"
    fi
    return
  fi

  if [[ -f "${ROOT_DIR}/${target}" && "${target}" == *.php ]]; then
    printf '%s\n' "${target}"
  fi
}

analysis_targets() {
  local path

  while IFS= read -r path; do
    if [[ "${path}" == "app" ]]; then
      if [[ -d "${ROOT_DIR}/app/Modules" ]]; then
        find "${ROOT_DIR}/app/Modules" -mindepth 2 -maxdepth 2 -type d \
          | sed "s#^${ROOT_DIR}/##" \
          | sort \
          | while IFS= read -r module_target; do append_existing_php_target "${module_target}"; done
      fi

      if [[ -d "${ROOT_DIR}/app/Shared" ]]; then
        find "${ROOT_DIR}/app/Shared" -mindepth 1 -maxdepth 1 -type d \
          | sed "s#^${ROOT_DIR}/##" \
          | sort \
          | while IFS= read -r shared_target; do append_existing_php_target "${shared_target}"; done
      fi

      find "${ROOT_DIR}/app" -mindepth 1 -maxdepth 1 \( -type f -name '*.php' -o -type d \) \
        ! -path "${ROOT_DIR}/app/Modules" \
        ! -path "${ROOT_DIR}/app/Shared" \
        | sed "s#^${ROOT_DIR}/##" \
        | sort \
        | while IFS= read -r app_target; do append_existing_php_target "${app_target}"; done
      continue
    fi

    append_existing_php_target "${path}"
  done < <(configured_paths) | awk '!seen[$0]++'
}

list_targets() {
  mapfile -t targets < <(analysis_targets)

  if [[ ${#targets[@]} -eq 0 ]]; then
    echo "PHPStan target discovery returned no analyzable paths." >&2
    exit 1
  fi

  printf '%s\n' "${targets[@]}"
}

verify_coverage() {
  mapfile -t roots < <(configured_paths)
  mapfile -t targets < <(analysis_targets)

  if [[ ${#roots[@]} -eq 0 || ${#targets[@]} -eq 0 ]]; then
    echo "PHPStan coverage verification cannot run with empty configured roots or targets." >&2
    exit 1
  fi

  missing=()

  while IFS= read -r file; do
    covered=false

    for target in "${targets[@]}"; do
      if [[ "${file}" == "${target}" || "${file}" == "${target}/"* ]]; then
        covered=true
        break
      fi
    done

    if [[ "${covered}" == false ]]; then
      missing+=("${file}")
    fi
  done < <(
    for root in "${roots[@]}"; do
      if [[ -d "${ROOT_DIR}/${root}" ]]; then
        find "${ROOT_DIR}/${root}" -type f -name '*.php' | sed "s#^${ROOT_DIR}/##"
      elif [[ -f "${ROOT_DIR}/${root}" && "${root}" == *.php ]]; then
        printf '%s\n' "${root}"
      fi
    done | sort
  )

  if [[ ${#missing[@]} -gt 0 ]]; then
    echo "PHPStan public target list does not cover these configured PHP files:" >&2
    printf ' - %s\n' "${missing[@]}" >&2
    exit 1
  fi
}

case "${1:-}" in
  --list-targets)
    list_targets
    exit 0
    ;;
  --verify-coverage)
    verify_coverage
    exit 0
    ;;
esac

mapfile -t targets < <(analysis_targets)

if [[ ${#targets[@]} -eq 0 ]]; then
  echo "PHPStan target discovery returned no analyzable paths." >&2
  exit 1
fi

for target in "${targets[@]}"; do
  vendor/bin/phpstan analyse "${target}" --memory-limit="${MEMORY_LIMIT}"
done
