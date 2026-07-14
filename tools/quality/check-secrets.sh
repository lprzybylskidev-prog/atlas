#!/usr/bin/env bash
set -euo pipefail

patterns=(
  '-----BEGIN (RSA |DSA |EC |OPENSSH |PGP )?PRIVATE KEY-----'
  'AKIA[0-9A-Z]{16}'
  'ASIA[0-9A-Z]{16}'
  'xox[baprs]-[0-9A-Za-z-]{10,}'
  'gh[pousr]_[0-9A-Za-z_]{36,}'
  'github_pat_[0-9A-Za-z_]{20,}'
  'sk-[A-Za-z0-9]{32,}'
  '(password|passwd|pwd|secret|token|api[_-]?key|access[_-]?key)[[:space:]]*[:=][[:space:]]*["'\'']?[A-Za-z0-9_./+=@:-]{16,}'
)

excluded_paths='(^|/)(composer\.lock|pnpm-lock\.yaml|package-lock\.json|yarn\.lock|bun\.lockb|public/build/|tests/e2e/.*\.png$)'

files=()
while IFS= read -r -d '' file; do
  if [[ ! "$file" =~ $excluded_paths ]]; then
    files+=("$file")
  fi
done < <(git ls-files -z)

if [[ ${#files[@]} -eq 0 ]]; then
  exit 0
fi

for pattern in "${patterns[@]}"; do
  if rg --pcre2 --line-number --hidden --no-messages -- "$pattern" "${files[@]}"; then
    echo "Potential secret detected. Remove the value or explicitly document a safe test fixture." >&2
    exit 1
  fi
done
