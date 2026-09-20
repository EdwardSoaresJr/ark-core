#!/bin/sh
# Load generated install secrets, then start the official MySQL entrypoint.
set -eu

SECRETS_FILE="${ARK_INSTALL_SECRETS_FILE:-/run/ark/secrets/install.env}"

i=0
while [ ! -s "$SECRETS_FILE" ]; do
    i=$((i + 1))
    if [ "$i" -ge 30 ]; then
        echo "mysql-with-secrets: install secrets file is missing." >&2
        exit 1
    fi
    sleep 1
done

set -a
# shellcheck disable=SC1090
. "$SECRETS_FILE"
set +a

export MYSQL_DATABASE="${DB_DATABASE:-ark}"
export MYSQL_USER="${DB_USERNAME:-ark}"
export MYSQL_PASSWORD="${DB_PASSWORD}"
export MYSQL_ROOT_PASSWORD="${MYSQL_ROOT_PASSWORD}"

exec docker-entrypoint.sh "$@"
