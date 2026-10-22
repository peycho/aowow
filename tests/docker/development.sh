#!/usr/bin/env bash
# Verify live source updates without using an existing site's configuration/data.
set -Eeuo pipefail
cd -- "$(dirname -- "${BASH_SOURCE[0]}")/../.."
work=$(mktemp -d /tmp/aowow-dev-watch-XXXXXX)
project="aowow-dev-watch-$(date +%s)-$$"
probe="aowow-dev-watch-$$.js"
compose=(docker compose --env-file "$work/env" --project-name "$project" -f docker-compose.yml -f docker-compose.dev.yml)
watcher=''
cleanup() {
    result=$?
    [[ -z "$watcher" ]] || { kill "$watcher" 2>/dev/null || true; wait "$watcher" 2>/dev/null || true; }
    if [[ "$result" != 0 ]]; then cat "$work/watch.log" >&2 2>/dev/null || true; fi
    "${compose[@]}" down --volumes --remove-orphans >/dev/null 2>&1 || true
    rm -f -- "static/js/$probe"
    rm -rf -- "$work"
    exit "$result"
}
trap cleanup EXIT
AOWOW_DEV_ENV_FILE="$work/env" bash docker/development/dev.sh init
port=$(python3 - <<'PY'
import socket
for port in range(8011,8021):
    with socket.socket() as sock:
        try: sock.bind(('127.0.0.1',port))
        except OSError: continue
        print(port)
        break
else: raise SystemExit('No available development test port in 8011-8020.')
PY
)
export HTTP_PORT=$port
"${compose[@]}" config --format json > "$work/model.json"
python3 - "$work/model.json" <<'PY'
import json,sys
model=json.load(open(sys.argv[1]))
web=model['services']['web']
assert web.get('read_only', False) is False
assert any(v['target']=='/opt/aowow/config' and v['read_only'] for v in web['volumes'])
assert not model['services']['db'].get('ports')
PY
"${compose[@]}" run --rm --no-deps -T init init
"${compose[@]}" up -d --no-build --no-deps web
printf '%s\n' 'watch-initial' > "static/js/$probe"
COMPOSE_PROJECT_NAME="$project" AOWOW_DEV_ENV_FILE="$work/env" bash docker/development/dev.sh watch > "$work/watch.log" 2>&1 &
watcher=$!
for ((attempt=0; attempt<30; attempt++)); do
    if grep -q 'Watch enabled' "$work/watch.log"; then break; fi
    kill -0 "$watcher"
    sleep 1
done
expect() {
    for ((attempt=0; attempt<60; attempt++)); do
        actual=$(curl --silent --fail "http://127.0.0.1:$port/static/js/$probe" || true)
        [[ "$actual" != "$1" ]] || return 0
        kill -0 "$watcher"
        sleep 1
    done
    echo 'Development source update did not arrive.' >&2; return 1
}
expect watch-initial
printf '%s\n' 'watch-updated' > "static/js/$probe"
expect watch-updated
kill "$watcher"
wait "$watcher" || true
watcher=''
printf '%s\n' 'watch-before-restart' > "static/js/$probe"
COMPOSE_PROJECT_NAME="$project" AOWOW_DEV_ENV_FILE="$work/env" bash docker/development/dev.sh watch > "$work/watch.log" 2>&1 &
watcher=$!
expect watch-before-restart
"${compose[@]}" exec -T web php -r '
    if (is_writable("config/config.php")) exit(1);
    if (!is_writable("includes/kernel.php")) exit(1);
'
COMPOSE_PROJECT_NAME="$project" AOWOW_DEV_ENV_FILE="$work/env" bash docker/development/dev.sh cli --help > "$work/cli.log" 2>&1
"${compose[@]}" run --rm --no-deps -T cli php -r '
    $url = "http://127.0.0.1:".getenv("AOWOW_DEV_HTTP_PORT")."/static/js/".$argv[1];
    if (trim(file_get_contents($url)) !== "watch-before-restart") exit(1);
' "$probe"
set +e
"${compose[@]}" run --rm --no-deps -T cli php -r 'exit(7);' > "$work/status.log" 2>&1
status=$?
set -e
[[ "$status" == 7 ]] || { echo 'Development CLI did not preserve command status.' >&2; exit 1; }
echo 'PASS: development sync, writable source, private config isolation, CLI HTTP forwarding and exit status'
