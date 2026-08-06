#!/usr/bin/env bash
set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"

package_version="$(node -e "const p=require('${ROOT_DIR}/package.json'); const v=p.dependencies?.playwright ?? p.devDependencies?.playwright; if (!v) process.exit(1); console.log(String(v).replace(/^[^0-9]*/, ''))")"
test_package_version="$(node -e "const p=require('${ROOT_DIR}/package.json'); const v=p.devDependencies?.['@playwright/test']; if (!v) process.exit(1); console.log(String(v).replace(/^[^0-9]*/, ''))")"
docker_version="$(sed -n 's/^ARG PLAYWRIGHT_VERSION=//p' "${ROOT_DIR}/docker/dev/app/Dockerfile")"

if [[ -z "${package_version}" || -z "${test_package_version}" || -z "${docker_version}" ]]; then
  echo "Unable to read Playwright versions from package.json and docker/dev/app/Dockerfile." >&2
  exit 1
fi

if [[ "${package_version}" != "${test_package_version}" || "${package_version}" != "${docker_version}" ]]; then
  echo "Playwright version drift detected:" >&2
  echo " - package.json playwright: ${package_version}" >&2
  echo " - package.json @playwright/test: ${test_package_version}" >&2
  echo " - docker/dev/app/Dockerfile PLAYWRIGHT_VERSION: ${docker_version}" >&2
  exit 1
fi

if ! rg -F -q "playwright@${package_version}:" "${ROOT_DIR}/pnpm-lock.yaml"; then
  echo "pnpm-lock.yaml does not contain playwright@${package_version}." >&2
  exit 1
fi

if ! rg -F -q "'@playwright/test@${package_version}':" "${ROOT_DIR}/pnpm-lock.yaml"; then
  echo "pnpm-lock.yaml does not contain @playwright/test@${package_version}." >&2
  exit 1
fi
