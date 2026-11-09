#!/usr/bin/env bash
set -euo pipefail
cd -- "$(dirname -- "${BASH_SOURCE[0]}")"
source ./identity.sh
# Conserver la base et les fichiers de démonstration.
docker compose down
