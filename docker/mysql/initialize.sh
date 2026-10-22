# Sourced by the official MySQL entrypoint, only for a fresh data directory.
# Leave this file non-executable: docker_process_sql belongs to that entrypoint.
docker_process_sql --database="$MYSQL_DATABASE" <<'SQL'
CREATE TABLE `_aowow_docker_import_pending` (`started` TIMESTAMP DEFAULT CURRENT_TIMESTAMP);
SQL
for aowow_sql in 01-db_structure.sql 02-db_initial_data.sql 03-db_initial_articles.sql; do
    docker_process_sql --database="$MYSQL_DATABASE" < "/opt/aowow-sql/$aowow_sql"
done
docker_process_sql --database="$MYSQL_DATABASE" <<'SQL'
DROP TABLE `_aowow_docker_import_pending`;
SQL
