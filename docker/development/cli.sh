#!/usr/bin/env bash
set -Eeuo pipefail

# Configured localhost URLs must reach the web service from maintenance commands
# too. This listener exists only inside this development CLI container.
port=${AOWOW_DEV_HTTP_PORT:-8011}
case "$port" in
    801[0-9]|8020) ;;
    *) echo 'Development HTTP port must be within 8010-8020.' >&2; exit 1 ;;
esac
proxy_log=$(mktemp /tmp/aowow-dev-http-XXXXXX.log)
socat "TCP-LISTEN:$port,bind=127.0.0.1,reuseaddr,fork" TCP:web:8080 2>"$proxy_log" &
proxy=$!
result=1
cleanup() {
    if [[ "$result" != 0 && -s "$proxy_log" ]]; then cat "$proxy_log" >&2; fi
    kill "$proxy" 2>/dev/null || true
    rm -f -- "$proxy_log"
}
trap cleanup EXIT

set +e
/usr/local/lib/aowow/entrypoint.sh cli "$@" <&0 &
maintenance=$!
trap 'kill -TERM "$maintenance" 2>/dev/null || true' TERM INT
wait "$maintenance"
result=$?
if kill -0 "$maintenance" 2>/dev/null; then
    wait "$maintenance"
    result=$?
fi
exit "$result"
