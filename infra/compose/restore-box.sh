#!/usr/bin/env bash
# Restore a backup created by backup-box.sh onto this Compose host.
#
# Same host (SQL + storage, keep live secrets):
#   RESTORE_CONFIRM=yes ./restore-box.sh /var/backups/ark-box/20260922T031000Z
#
# New host (secrets + SQL + storage). MySQL must already be running with the
# restored secrets volume mounted:
#   RESTORE_CONFIRM=yes ARK_RESTORE_SECRETS=yes ./restore-box.sh /path/to/stamp
set -euo pipefail

BACKUP_DIR="${1:-}"
ARK_COMPOSE_DIR="${ARK_COMPOSE_DIR:-/opt/ark}"
RESTORE_SECRETS="${ARK_RESTORE_SECRETS:-no}"

if [ "${RESTORE_CONFIRM:-}" != "yes" ]; then
    echo "restore-box: set RESTORE_CONFIRM=yes to run" >&2
    exit 1
fi

if [ -z "${BACKUP_DIR}" ] || [ ! -f "${BACKUP_DIR}/ark.sql.gz" ]; then
    echo "restore-box: usage: RESTORE_CONFIRM=yes $0 /path/to/backup-stamp" >&2
    exit 1
fi

gzip -t "${BACKUP_DIR}/ark.sql.gz"

cd "${ARK_COMPOSE_DIR}"

if [ -z "${ARK_COMPOSE_FILES:-}" ]; then
    ARK_COMPOSE_FILES="-f docker-compose.yml"
    for extra in docker-compose.vultr.yml docker-compose.image.yml docker-compose.demo.yml docker-compose.override.yml; do
        if [ -f "${extra}" ]; then
            ARK_COMPOSE_FILES="${ARK_COMPOSE_FILES} -f ${extra}"
        fi
    done
fi

compose() {
    # shellcheck disable=SC2086
    docker compose ${ARK_COMPOSE_FILES} "$@"
}

MYSQL_CONTAINER="$(compose ps -q mysql)"
APP_CONTAINER="$(compose ps -q app || true)"

if [ -z "${MYSQL_CONTAINER}" ]; then
    echo "restore-box: mysql is not running" >&2
    exit 1
fi

if [ "${RESTORE_SECRETS}" = "yes" ]; then
    SECRETS_VOL="$(docker inspect -f '{{range .Mounts}}{{if eq .Destination "/run/ark/secrets"}}{{.Name}}{{end}}{{end}}' "${MYSQL_CONTAINER}")"
    docker run --rm -v "${SECRETS_VOL}:/dst" -v "${BACKUP_DIR}:/src:ro" alpine:3.20 \
        sh -c 'rm -rf /dst/* /dst/.[!.]* ; tar xzf /src/secrets.tar.gz -C /dst'
    compose restart mysql
    for _ in $(seq 1 30); do
        if compose exec -T mysql sh -c '. /run/ark/secrets/install.env && mysqladmin ping -h 127.0.0.1 -uroot -p"$MYSQL_ROOT_PASSWORD" --silent'; then
            break
        fi
        sleep 2
    done
fi

echo "restore-box: importing SQL"
gunzip -c "${BACKUP_DIR}/ark.sql.gz" | compose exec -T mysql sh -c '
    set -eu
    . /run/ark/secrets/install.env
    umask 077
    conf=/tmp/ark-box-restore.cnf
    printf "[client]\nuser=root\npassword=%s\n" "$MYSQL_ROOT_PASSWORD" > "$conf"
    mysql --defaults-extra-file="$conf" "${DB_DATABASE:-ark}"
    rm -f "$conf"
'

if [ -f "${BACKUP_DIR}/storage.tar.gz" ] && [ -n "${APP_CONTAINER}" ]; then
    STORAGE_VOL="$(docker inspect -f '{{range .Mounts}}{{if eq .Destination "/app/storage"}}{{.Name}}{{end}}{{end}}' "${APP_CONTAINER}")"
    echo "restore-box: restoring storage"
    compose stop app || true
    docker run --rm -v "${STORAGE_VOL}:/dst" -v "${BACKUP_DIR}:/src:ro" alpine:3.20 \
        sh -c 'rm -rf /dst/* /dst/.[!.]* ; tar xzf /src/storage.tar.gz -C /dst'
    compose start app || compose up -d app
fi

echo "restore-box: ok ${BACKUP_DIR}"
