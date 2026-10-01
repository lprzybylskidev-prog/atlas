#!/usr/bin/env bash
set -euo pipefail

rtc_root="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
rtc_compose="${rtc_root}/.devcontainer/docker-compose.yml"
rtc_env="${rtc_root}/.env.example"
rtc_api_key="atlasdevkey"
rtc_api_credential="atlas_dev_livekit_secret_change_me"
rtc_workspace_source="${ATLAS_WORKSPACE_SOURCE:-..}"

if docker info >/dev/null 2>&1; then
  rtc_use_sudo=false
elif sudo -n docker info >/dev/null 2>&1; then
  rtc_use_sudo=true
else
  printf 'Docker access is required to run the RTC integration test.\n' >&2
  exit 1
fi

rtc_compose_run() {
  if [[ "${rtc_use_sudo}" == "true" ]]; then
    sudo env \
      ATLAS_WORKSPACE_SOURCE="${rtc_workspace_source}" \
      LIVEKIT_API_KEY="${rtc_api_key}" \
      LIVEKIT_API_SECRET="${rtc_api_credential}" \
      docker compose --env-file "${rtc_env}" -f "${rtc_compose}" "$@"
  else
    ATLAS_WORKSPACE_SOURCE="${rtc_workspace_source}" \
    LIVEKIT_API_KEY="${rtc_api_key}" \
    LIVEKIT_API_SECRET="${rtc_api_credential}" \
      docker compose --env-file "${rtc_env}" -f "${rtc_compose}" "$@"
  fi
}

rtc_compose_run up -d redis livekit livekit-egress

if getent hosts livekit >/dev/null 2>&1; then
  rtc_host="livekit"
  egress_host="livekit-egress"
else
  rtc_host="127.0.0.1"
  egress_host="127.0.0.1"
fi

wait_for_tcp() {
  local host="$1"
  local port="$2"
  local label="$3"

  for _attempt in $(seq 1 60); do
    if php -r '$s=@fsockopen($argv[1], (int) $argv[2], $code, $message, 1.0); if ($s) { fclose($s); exit(0); } exit(1);' "${host}" "${port}"; then
      return 0
    fi

    sleep 1
  done

  printf '%s did not become reachable at %s:%s.\n' "${label}" "${host}" "${port}" >&2
  rtc_compose_run logs --tail=100 livekit livekit-egress >&2
  return 1
}

wait_for_tcp "${rtc_host}" 7880 LiveKit
wait_for_tcp "${egress_host}" 8081 'LiveKit Egress'

ATLAS_TEST_LIVEKIT=1 \
LIVEKIT_URL="http://${rtc_host}:7880" \
LIVEKIT_API_KEY="${rtc_api_key}" \
LIVEKIT_API_SECRET="${rtc_api_credential}" \
ATLAS_TEST_EGRESS_HOST="${egress_host}" \
ATLAS_TEST_EGRESS_PORT=8081 \
php artisan test tests/Integration/Chat/LiveKitRtcInfrastructureTest.php
