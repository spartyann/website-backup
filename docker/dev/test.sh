#!/usr/bin/env bash
set -euo pipefail
cd -- "$(dirname -- "${BASH_SOURCE[0]}")"
source ./identity.sh
# Pas de navigateur, réseau externe, service de production ou suppression de données.
docker compose exec -T --user "$WB_DEV_UID:$WB_DEV_GID" web php /workspace/tests/api_security.php
docker compose exec -T --user "$WB_DEV_UID:$WB_DEV_GID" web php -n /workspace/tests/remote_scripts_security.php
docker compose exec -T --user "$WB_DEV_UID:$WB_DEV_GID" web php /opt/website-backup-dev/smoke.php
