#!/usr/bin/env bash
set -euo pipefail

php artisan reverb:start --host=127.0.0.1 --port=8085 &
reverb_pid=$!

cleanup() {
  kill "${reverb_pid}" >/dev/null 2>&1 || true
}
trap cleanup EXIT INT TERM

php -S 127.0.0.1:8086 -t public
