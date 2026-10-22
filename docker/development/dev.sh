#!/usr/bin/env bash
set -Eeuo pipefail
cd -- "$(dirname -- "${BASH_SOURCE[0]}")/../.."
env_file=${AOWOW_DEV_ENV_FILE:-.env.dev}
compose=(docker compose --env-file "$env_file" -f docker-compose.yml -f docker-compose.dev.yml)

initialize() {
    if [[ ! -f "$env_file" ]]; then
        python3 - "$env_file" <<'PY'
import os, re, secrets, sys
from pathlib import Path
text = Path('.env.dev.example').read_text()
for name in ['MYSQL_ROOT_PASSWORD', 'TRINITY_MYSQL_ROOT_PASSWORD', 'AOWOW_DB_PASSWORD', 'WORLD_DB_PASSWORD']:
    text = re.sub('^'+name+'=$', name+'='+secrets.token_hex(24), text, flags=re.MULTILINE)
fd = os.open(sys.argv[1], os.O_WRONLY | os.O_CREAT | os.O_EXCL, 0o600)
with os.fdopen(fd, 'w') as stream:
    stream.write(text)
PY
        echo "Created $env_file with private development credentials."
    fi
}
build() {
    initialize
    # Explicit builds avoid dependence on service build ordering across images.
    runtime_image=$(docker compose --env-file "$env_file" -f docker-compose.yml config --format json |
        python3 -c 'import json,sys; print(json.load(sys.stdin)["services"]["web"]["image"])')
    if [[ "${1:-}" != snapshot ]] || ! docker image inspect "$runtime_image" >/dev/null 2>&1; then
        docker compose --env-file "$env_file" -f docker-compose.yml build web
    fi
    "${compose[@]}" build web
}
case "${1:-}" in
    init) initialize ;;
    build) build ;;
    up) initialize; "${compose[@]}" up -d --no-build ;;
    watch)
        build snapshot
        "${compose[@]}" up -d --no-build --force-recreate web
        exec "${compose[@]}" watch --no-up web
        ;;
    down) initialize; "${compose[@]}" --profile dev-tools down ;;
    cli) build snapshot; shift; "${compose[@]}" run --rm cli php aowow "$@" ;;
    shell) build snapshot; "${compose[@]}" run --rm cli bash ;;
    test)
        initialize
        group=${2:-all}
        case "$group" in
            all|lint|php|javascript|browser|sql|apache) ;;
            *) echo 'Unknown development test group.' >&2; exit 2 ;;
        esac
        if [[ "$group" != apache ]]; then
            # Snapshot current source/tests and locked dev dependencies each run.
            build snapshot
            test_compose=("${compose[@]}" --project-name "aowow-dev-test-$(date +%s)-$$")
            cleanup_tests() {
                result=$?
                "${test_compose[@]}" --profile dev-tools down --volumes --remove-orphans >/dev/null 2>&1 || true
                exit "$result"
            }
            trap cleanup_tests EXIT
            "${test_compose[@]}" run --rm tests "$group"
        fi
        if [[ "$group" == apache || "$group" == all ]]; then
            # Run the Docker-owning orchestration on the host; no socket in PHP.
            python3 - <<'PY'
import socket
with socket.socket() as sock:
    sock.bind(('127.0.0.1', 8012))
PY
            AOWOW_TEST_APACHE_PORT=8012 bash tests/ci/run.sh apache
        fi
        ;;
    *) echo 'Usage: bash docker/development/dev.sh {init|build|up|watch|down|cli|shell|test}' >&2; exit 2 ;;
esac
