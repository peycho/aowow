#!/usr/bin/env bash
set -Eeuo pipefail
cd /opt/aowow

lock_config() {
    chown -R root:www-data config
    find config -type d -exec chmod 0750 {} +
    find config -type f -exec chmod 0640 {} +
}

case "${1:-}" in
    init|configure)
        [[ "$(id -u)" == 0 ]] || { echo 'Initialization requires the maintenance service.' >&2; exit 1; }
        mkdir -p config
        exec 9>config/.docker-lock
        flock -x 9
        trap lock_config EXIT
        if [[ "$1" == init ]]; then
            php /usr/local/lib/aowow/initialize.php
            php /usr/local/lib/aowow/configure.php
        else
            php /usr/local/lib/aowow/configure.php --refresh
        fi
        ;;
    cli)
        shift
        [[ "$(id -u)" == 0 ]] || { echo 'Use the CLI Compose service.' >&2; exit 1; }
        [[ -f config/config.php ]] || { echo 'Run make docker-init first.' >&2; exit 1; }
        exec 9>config/.docker-lock
        flock -x 9
        trap lock_config EXIT
        chown -R www-data:www-data config
        # The separate web container always mounts this volume read-only.
        # PHP and generators use the web UID to preserve private cache ownership.
        child=''
        forward() { [[ -z "$child" ]] || kill -s "$1" -- "-$child" 2>/dev/null || true; }
        trap 'forward TERM' TERM
        trap 'forward INT' INT
        # Background jobs otherwise inherit ignored SIGINT/SIGQUIT from Bash.
        setsid gosu www-data env --default-signal=INT,QUIT "$@" <&0 &
        child=$!
        status=0
        wait "$child" || status=$?
        # A trapped signal interrupts wait before the child has necessarily exited.
        if kill -0 "$child" 2>/dev/null; then
            wait "$child" || status=$?
        fi
        exit "$status"
        ;;
    *)
        mkdir -p /tmp/apache2
        exec docker-php-entrypoint "$@"
        ;;
esac
