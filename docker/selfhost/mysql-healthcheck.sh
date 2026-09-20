#!/bin/sh
# Ping MySQL with the generated application password. Do not echo it.
set -eu

SECRETS_FILE="${ARK_INSTALL_SECRETS_FILE:-/run/ark/secrets/install.env}"

if [ ! -s "$SECRETS_FILE" ]; then
    exit 1
fi

set -a
# shellcheck disable=SC1090
. "$SECRETS_FILE"
set +a

mysqladmin ping -h 127.0.0.1 -u "${DB_USERNAME:-ark}" -p"${DB_PASSWORD}" --silent
