#!/usr/bin/env bash
set -euo pipefail

forbidden_patterns=(
  '(^|/)package-lock\.json$'
  '(^|/)npm-shrinkwrap\.json$'
  '(^|/)yarn\.lock$'
  '(^|/)bun\.lockb$'
  '(^|/)\.env$'
  '(^|/)\.env\..+'
  '(^|/)public/hot$'
  '(^|/)public/fonts-manifest\.dev\.json$'
  '^node_modules/'
  '^vendor/'
)

allowed_patterns=(
  '(^|/)\.env\.example$'
)

is_allowed() {
  local file="$1"

  for allowed in "${allowed_patterns[@]}"; do
    if [[ "$file" =~ $allowed ]]; then
      return 0
    fi
  done

  return 1
}

violations=()

while IFS= read -r -d '' file; do
  if is_allowed "$file"; then
    continue
  fi

  for forbidden in "${forbidden_patterns[@]}"; do
    if [[ "$file" =~ $forbidden ]]; then
      violations+=("$file")
      break
    fi
  done
done < <(git ls-files -z)

if [[ ${#violations[@]} -gt 0 ]]; then
  printf 'Forbidden file tracked by Git:\n' >&2
  printf ' - %s\n' "${violations[@]}" >&2
  exit 1
fi
