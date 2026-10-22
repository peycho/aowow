#!/bin/sh
set -eu
if [ -n "${MYSQL_ROOT_PASSWORD_FILE:-}" ]; then
    MYSQL_PWD=$(cat "$MYSQL_ROOT_PASSWORD_FILE")
else
    MYSQL_PWD=${MYSQL_ROOT_PASSWORD:-}
fi
export MYSQL_PWD
# TCP is unavailable on the image's temporary initialization server.
result=$(mysql --protocol=TCP --host=127.0.0.1 --port="${MYSQL_TCP_PORT:-3306}" --user=root --batch --skip-column-names \
    --execute="SELECT 1 WHERE NOT EXISTS (SELECT 1 FROM information_schema.tables WHERE TABLE_NAME = '_aowow_docker_import_pending')")
test "$result" = 1
