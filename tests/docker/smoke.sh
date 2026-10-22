#!/usr/bin/env bash
# Requires the built image, Docker Compose, Python 3, curl and GNU timeout.
# All data belongs to this unique disposable project; no existing stack is used.
set -Eeuo pipefail
cd -- "$(dirname -- "${BASH_SOURCE[0]}")/../.."
root=$PWD
work=$(mktemp -d /tmp/aowow-docker-smoke-XXXXXX)
project="aowow-docker-test-$(date +%s)-$$"
compose=(docker compose --project-name "$project" --env-file "$work/env" -f "$root/docker-compose.yml" -f "$work/ports.yml")
cleanup() {
    result=$?
    if [[ "$result" != 0 ]]; then "${compose[@]}" logs --tail=60 >&2 || true; fi
    "${compose[@]}" --profile local-db --profile trinity-db down --volumes --remove-orphans >/dev/null 2>&1 || true
    rm -rf -- "$work"
    exit "$result"
}
trap cleanup EXIT
python3 - "$work" <<'PY'
import os, secrets, socket, sys
from pathlib import Path
work = Path(sys.argv[1])
port = None
for candidate in range(8010, 8021):
    with socket.socket() as sock:
        try:
            sock.bind(('127.0.0.1', candidate))
        except OSError:
            continue
        port = candidate
        break
if port is None:
    raise SystemExit('No available local HTTP port in 8010-8020.')
values = dict(COMPOSE_PROFILES='local-db,trinity-db', AOWOW_IMAGE=os.environ.get('AOWOW_IMAGE', 'aowow:local'),
              HTTP_PORT=str(port), MYSQL_ROOT_PASSWORD=secrets.token_hex(24),
              TRINITY_MYSQL_ROOT_PASSWORD=secrets.token_hex(24), AOWOW_DB_PASSWORD=secrets.token_hex(24),
              WORLD_DB_PASSWORD=secrets.token_hex(24), AUTH_DB_NAME='auth', CHARACTERS_DB_NAME='characters',
              WORLD_DB_PORT='3307', AUTH_DB_PORT='3307', CHARACTERS_DB_PORT='3307')
values['AUTH_DB_PASSWORD'] = values['CHARACTERS_DB_PASSWORD'] = values['WORLD_DB_PASSWORD']
(work/'env').write_text(''.join(f'{k}={v}\n' for k,v in values.items()))
(work/'port').write_text(str(port))
(work/'reader-password').write_text(values['WORLD_DB_PASSWORD'])
(work/'ports.yml').write_text('services:\n  trinity-db:\n    command: ["--port=3307"]\n    environment:\n      MYSQL_TCP_PORT: "3307"\n')
PY
# These generated values are plain hexadecimal passwords and simple settings.
set -a
source "$work/env"
set +a
echo "Checking Compose modes and local HTTP port $(cat "$work/port")"
COMPOSE_PROFILES= HTTP_PORT=8010 MYSQL_ROOT_PASSWORD= TRINITY_MYSQL_ROOT_PASSWORD= docker compose --env-file "$work/env" -f docker-compose.yml config --format json > "$work/config.json"
python3 - "$work/config.json" <<'PY'
import json,sys
model=json.load(open(sys.argv[1]))
assert set(model['services']) == {'init','web'}
assert model['services']['web']['ports'][0]['published'] == '8010'
assert model['services']['web']['ports'][0]['host_ip'] == '127.0.0.1'
assert model['services']['web']['ports'][0]['target'] == 8080
assert not model['services']['web']['environment'].get('AOWOW_DB_PASSWORD')
PY
COMPOSE_PROFILES=local-db HTTP_PORT=8011 docker compose --env-file "$work/env" -f docker-compose.yml config --format json > "$work/config.json"
python3 - "$work/config.json" <<'PY'
import json,sys
model=json.load(open(sys.argv[1]))
assert set(model['services']) == {'init','web','db'}
assert model['services']['web']['ports'][0]['published'] == '8011'
PY
"${compose[@]}" config --format json > "$work/config.json"
python3 - "$work/config.json" <<'PY'
import json,sys
model=json.load(open(sys.argv[1]))
assert 'db' in model['services'] and 'trinity-db' in model['services']
assert not model['services']['db'].get('ports') and not model['services']['trinity-db'].get('ports')
PY
"${compose[@]}" up -d --no-build db trinity-db
"${compose[@]}" up -d --no-build web
wait_healthy() {
    local service=$1 state
    for ((attempt=0; attempt<120; attempt++)); do
        state=$(docker inspect --format '{{if .State.Health}}{{.State.Health.Status}}{{end}}' "$("${compose[@]}" ps -q "$service")")
        [[ "$state" != healthy ]] || return 0
        sleep 2
    done
    echo "$service did not become healthy" >&2
    return 1
}
wait_healthy db
wait_healthy trinity-db
reader=$(cat "$work/reader-password")
"${compose[@]}" exec -T trinity-db sh -c 'MYSQL_PWD="$MYSQL_ROOT_PASSWORD" exec mysql --user=root' <<SQL
CREATE DATABASE world;
CREATE TABLE world.version (db_version VARCHAR(100), cache_id INT);
INSERT INTO world.version VALUES ('TDB 335 fixture', 26091);
CREATE DATABASE auth;
CREATE DATABASE characters;
CREATE USER 'aowow_reader'@'%' IDENTIFIED BY '$reader';
GRANT SELECT ON world.* TO 'aowow_reader'@'%';
GRANT SELECT ON auth.* TO 'aowow_reader'@'%';
GRANT SELECT ON characters.* TO 'aowow_reader'@'%';
SQL
wait_healthy web
cli() { "${compose[@]}" run --rm --no-deps -T cli "$@"; }
cli php aowow --help >/dev/null
cli php aowow --build=robots >/dev/null
python3 tests/docker/check_interactive.py "${compose[@]}"
"${compose[@]}" run --rm --no-deps -T -v "$root/tests:/opt/aowow/tests:ro" cli php tests/docker/check.php
if cli php /usr/local/lib/aowow/import.php; then echo 'Importer accepted a populated database' >&2; exit 1; fi
if cli sh -c 'exit 23'; then echo 'CLI lost failure status' >&2; exit 1; else [[ "$?" == 23 ]]; fi
printf '%s\n' 'stdin-forwarded' | cli sh -c 'read -r value; test "$value" = stdin-forwarded'
# Graceful termination must restore deployment ownership just like normal exit.
maintenance="${project}-termination"
"${compose[@]}" run --detach --name "$maintenance" --no-deps cli sleep 120 >/dev/null
for ((attempt=0; attempt<30; attempt++)); do
    [[ "$(docker exec "$maintenance" stat -c %u config/config.php)" != 33 ]] || break
    sleep 1
done
docker stop --time 10 "$maintenance" >/dev/null
docker inspect --format 'Maintenance termination exit status: {{.State.ExitCode}}' "$maintenance"
docker logs "$maintenance"
docker rm "$maintenance" >/dev/null
maintenance="${project}-interrupt"
"${compose[@]}" run --detach --name "$maintenance" --no-deps cli sleep 120 >/dev/null
for ((attempt=0; attempt<30; attempt++)); do
    [[ "$(docker exec "$maintenance" stat -c %u config/config.php)" != 33 ]] || break
    sleep 1
done
docker kill --signal INT "$maintenance" >/dev/null
[[ "$(timeout 10s docker wait "$maintenance")" == 130 ]]
docker rm "$maintenance" >/dev/null
"${compose[@]}" exec -T web php -r '
    foreach (["config/config.php", "config/security.php"] as $file) {
        if (is_writable($file) || fileowner($file) !== 0 || (fileperms($file) & 0777) !== 0640) exit(1);
    }
    if (!is_readable("cache/docker-smoke") || !is_writable("cache/docker-smoke")) exit(1);
    if (@file_put_contents("config/config.php", "forbidden") !== false) exit(1);
    if (@file_put_contents("includes/forbidden.php", "forbidden") !== false) exit(1);
'
# Exercise the manual importer against an independently provisioned empty schema.
"${compose[@]}" exec -T db sh -c 'MYSQL_PWD="$MYSQL_ROOT_PASSWORD" exec mysql --user=root' <<'SQL'
CREATE DATABASE aowow_external;
CREATE DATABASE aowow_partial;
GRANT ALL ON aowow_external.* TO 'aowow'@'%';
GRANT ALL ON aowow_partial.* TO 'aowow'@'%';
SQL
"${compose[@]}" run --rm --no-deps -T -e AOWOW_DB_NAME=aowow_external cli php /usr/local/lib/aowow/import.php --environment
printf '%s\n' 'CREATE TABLE before_failure (id INT);' 'THIS IS INVALID SQL;' > "$work/broken.sql"
if "${compose[@]}" run --rm --no-deps -T -e AOWOW_DB_NAME=aowow_partial \
    -v "$work/broken.sql:/opt/aowow/setup/sql/01-db_structure.sql:ro" cli php /usr/local/lib/aowow/import.php --environment; then
    echo 'Invalid SQL did not stop import' >&2; exit 1
fi
if "${compose[@]}" run --rm --no-deps -T -e AOWOW_DB_NAME=aowow_partial cli php /usr/local/lib/aowow/import.php --environment; then
    echo 'Partial import was replayed' >&2; exit 1
fi
"${compose[@]}" exec -T db sh -c 'MYSQL_PWD="$MYSQL_ROOT_PASSWORD" exec mysql --user=root' <<'SQL'
DROP DATABASE aowow_partial;
DROP DATABASE aowow_external;
SQL
cli php -r '
    foreach ([".hidden", "temp", "screenshots/pending"] as $dir) {
        @mkdir("static/uploads/".$dir, 0755, true);
        file_put_contents("static/uploads/".$dir."/probe.txt", "PRIVATE-SMOKE");
    }
    file_put_contents("static/uploads/docker-smoke.php", "<?php echo \"PRIVATE-SMOKE\";");
    file_put_contents("static/uploads/docker-smoke.php.txt", "PRIVATE-SMOKE");
'
python3 tests/docker/check_http.py "http://127.0.0.1:$(cat "$work/port")"
# Refresh from the same image emulates stale shipped assets in an existing volume.
cli php -r '
    file_put_contents("static/js/basic.js", "stale shipped code");
    define("AOWOW_REVISION", 1);
    require "config/config.php";
    $AoWoWconf["externalLinks"] = ["sentinel" => ["enabled" => false]];
    $AoWoWconf["characters"][2] = $AoWoWconf["characters"][1];
    file_put_contents("config/config.php", "<?php \$AoWoWconf = ".var_export($AoWoWconf, true).";");
    file_put_contents("cache/security-hash", hash_file("sha256", "config/security.php"));
'
"${compose[@]}" run --rm --no-deps -T init init
"${compose[@]}" run --rm --no-deps -T init configure
cli php -r '
    define("AOWOW_REVISION", 1);
    require "config/config.php";
    if (!isset($AoWoWconf["characters"][2], $AoWoWconf["externalLinks"]["sentinel"])) exit(1);
    if (hash_file("sha256", "config/security.php") !== file_get_contents("cache/security-hash")) exit(1);
    if (str_contains(file_get_contents("static/js/basic.js"), "stale shipped code")) exit(1);
'
"${compose[@]}" up -d --no-build --force-recreate web
wait_healthy web
"${compose[@]}" restart db
wait_healthy db
"${compose[@]}" exec -T web php -r '
    foreach (["cache/docker-smoke" => "persistent-cache", "static/uploads/docker-smoke.txt" => "persistent-upload", "datasets/docker-smoke" => "persistent-dataset"] as $file => $expected) {
        if (file_get_contents($file) !== $expected) exit(1);
    }
'
# Environment-to-file refresh with a Compose secret must preserve the existing key.
printf '%s' "$AOWOW_DB_PASSWORD" > "$work/db-password"
chmod 0444 "$work/db-password"
cat > "$work/secrets.yml" <<YAML
services:
  init:
    environment:
      AOWOW_DB_PASSWORD: ''
      AOWOW_DB_PASSWORD_FILE: /run/secrets/aowow-password
    secrets: [aowow-password]
  cli:
    environment:
      AOWOW_DB_PASSWORD: ''
      AOWOW_DB_PASSWORD_FILE: /run/secrets/aowow-password
    secrets: [aowow-password]
secrets:
  aowow-password:
    file: $work/db-password
YAML
"${compose[@]}" -f "$work/secrets.yml" run --rm --no-deps -T init configure
"${compose[@]}" -f "$work/secrets.yml" run --rm --no-deps -T cli php -r '
    require "/usr/local/lib/aowow/environment.php";
    $db = dockerDatabase("AOWOW");
    $link = new mysqli($db["host"], $db["user"], $db["pass"], $db["db"], $db["port"]);
    if (!$link->query("SELECT 1")) exit(1);
'
"${compose[@]}" exec -T web php /usr/local/lib/aowow/healthcheck.php
# Reject conflicting plain/file inputs before replacing private configuration.
if "${compose[@]}" -f "$work/secrets.yml" run --rm --no-deps -T -e AOWOW_DB_PASSWORD=conflict init configure; then
    echo 'Conflicting secret inputs accepted' >&2; exit 1
fi
echo 'PASS: Docker initialization, grants, import guard, configuration, HTTP, permissions and persistence'
