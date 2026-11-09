#!/bin/sh
set -eu
umask 077
if [ "${1:-}" = 'apache2-foreground' ]; then
    for identity in "${WB_DEV_UID:-}" "${WB_DEV_GID:-}"; do
        case "$identity" in ''|*[!0-9]*|0*) echo 'Identité DEV invalide.' >&2; exit 1 ;; esac
        if [ "$identity" -gt 2147483647 ]; then echo 'Identité DEV invalide.' >&2; exit 1; fi
    done
    if ! getent group "$WB_DEV_GID" >/dev/null; then
        groupadd -g "$WB_DEV_GID" "wbdev_$WB_DEV_GID"
    fi
    if ! getent passwd "$WB_DEV_UID" >/dev/null; then
        useradd -M -u "$WB_DEV_UID" -g "$WB_DEV_GID" -d /tmp -s /usr/sbin/nologin "wbdev_$WB_DEV_UID"
    fi
    export APACHE_RUN_USER="#$WB_DEV_UID"
    export APACHE_RUN_GROUP="#$WB_DEV_GID"
    setpriv --reuid "$WB_DEV_UID" --regid "$WB_DEV_GID" --clear-groups \
        composer install --no-interaction --prefer-dist --no-progress
    setpriv --reuid "$WB_DEV_UID" --regid "$WB_DEV_GID" --clear-groups \
        php -d auto_prepend_file=/opt/website-backup-dev/bootstrap.php /opt/website-backup-dev/seed.php
fi
exec docker-php-entrypoint "$@"
