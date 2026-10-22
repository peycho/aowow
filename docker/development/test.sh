#!/usr/bin/env bash
set -Eeuo pipefail
cd /opt/aowow
mkdir -p cache/sessions
browser_root=$(mktemp -d /tmp/aowow-dev-browser-XXXXXX)
export XDG_CONFIG_HOME="$browser_root/config" XDG_CACHE_HOME="$browser_root/cache"
mkdir -p "$XDG_CONFIG_HOME" "$XDG_CACHE_HOME"
case "${1:-all}" in
    all) groups=(lint php javascript browser sql) ;;
    lint|php|javascript|browser|sql) groups=("$1") ;;
    *) echo 'Usage: make dev-test ARGS={all|lint|php|javascript|browser|sql|apache}' >&2; exit 2 ;;
esac

# The existing SQL gate deliberately accepts loopback only. This proxy has a
# fixed destination on the dedicated fixture network, never deployment settings.
socat TCP-LISTEN:3307,bind=127.0.0.1,reuseaddr,fork TCP:test-db:3306 &
proxy=$!
trap 'kill "$proxy" 2>/dev/null || true; rm -rf -- "$browser_root"' EXIT
for ((attempt=0; attempt<30; attempt++)); do
    if php -r '$s = @stream_socket_client("tcp://127.0.0.1:3307", $e, $m, 1); exit($s ? 0 : 1);'; then break; fi
    sleep 1
done
for group in "${groups[@]}"; do
    echo "Running development gate: $group"
    bash tests/ci/run.sh "$group"
done
