#!/usr/bin/env bash
set -euo pipefail
cd -- "$(dirname -- "${BASH_SOURCE[0]}")"
source ./identity.sh
umask 077
command -v docker >/dev/null || { echo 'Installer Docker Engine et Docker Compose.' >&2; exit 1; }
docker compose version >/dev/null
if [[ ! -f .env ]]; then
    cp .env.example .env
    if [[ $(id -u) == 0 ]]; then chown "$WB_DEV_UID:$WB_DEV_GID" .env; fi
fi
# .env est lu par Compose, jamais exécuté comme un script shell.
for name in WB_DEV_UI_PASSWORD WB_DEV_API_KEY WB_DEV_URL_TOKEN WB_DEV_DB_PASSWORD WB_DEV_DB_ROOT_PASSWORD; do
    if ! grep -q "^${name}=." .env; then
        secret=$(od -An -N32 -tx1 /dev/urandom | tr -d ' \n')
        if grep -q "^${name}=" .env; then
            sed -i "s/^${name}=$/${name}=${secret}/" .env
        else
            printf '\n%s=%s\n' "$name" "$secret" >> .env
        fi
    fi
done
chmod 600 .env
if [[ $(id -u) == 0 ]]; then chown "$WB_DEV_UID:$WB_DEV_GID" .env; fi
# Préparer aussi le point de montage imbriqué, sans droits root sur le dépôt.
for directory in data data/vendor data/composer data/backups data/site data/integrity ../../src/vendor; do
    if [[ ! -d $directory ]]; then
        mkdir -p "$directory"
        if [[ $(id -u) == 0 ]]; then chown "$WB_DEV_UID:$WB_DEV_GID" "$directory"; fi
    fi
done
docker compose config --quiet
docker compose up --build -d --wait --wait-timeout 600
http_port=$(docker compose config --environment | sed -n 's/^HTTP_PORT=//p')
pma_port=$(docker compose config --environment | sed -n 's/^PHPMYADMIN_PORT=//p')
printf '\nInterface : http://127.0.0.1:%s/ui.php\nphpMyAdmin : http://127.0.0.1:%s\nIdentifiants : docker/dev/.env (WB_DEV_UI_PASSWORD)\n' "${http_port:-8090}" "${pma_port:-8091}"
docker compose ps
