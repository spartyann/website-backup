#!/usr/bin/env bash
# Même principe que TyFramework : conserver l'identité hôte, même sous sudo.
if [[ -z ${WB_DEV_UID:-} && -z ${WB_DEV_GID:-} ]]; then
    if [[ $(id -u) == 0 ]]; then
        WB_DEV_UID=${SUDO_UID:-}
        WB_DEV_GID=${SUDO_GID:-}
    else
        WB_DEV_UID=$(id -u)
        WB_DEV_GID=$(id -g)
    fi
fi
if [[ ! ${WB_DEV_UID:-} =~ ^[1-9][0-9]{0,9}$ || ! ${WB_DEV_GID:-} =~ ^[1-9][0-9]{0,9}$ ]] \
    || (( WB_DEV_UID > 2147483647 || WB_DEV_GID > 2147483647 )); then
    echo 'Identité DEV invalide : utiliser un compte non root ou définir WB_DEV_UID et WB_DEV_GID.' >&2
    return 1 2>/dev/null || exit 1
fi
export WB_DEV_UID WB_DEV_GID
