#!/usr/bin/env bash
set -euo pipefail

cd "$(dirname "$0")/.."

if [[ ! -f pnpm-lock.yaml ]]; then
    echo "pnpm-lock.yaml is required." >&2
    exit 1
fi

for forbidden_lockfile in package-lock.json npm-shrinkwrap.json yarn.lock bun.lock bun.lockb; do
    if [[ -e "$forbidden_lockfile" ]]; then
        echo "$forbidden_lockfile is forbidden. Atlas uses pnpm only." >&2
        exit 1
    fi
done

echo "JavaScript package manager check passed."
