#!/usr/bin/env bash
set -euo pipefail
cd -- "$(dirname -- "${BASH_SOURCE[0]}")"
source ./identity.sh
docker compose exec -T --user "$WB_DEV_UID:$WB_DEV_GID" web \
    php -d auto_prepend_file=/opt/website-backup-dev/bootstrap.php "$@"
