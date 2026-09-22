#!/usr/bin/env bash
# Backup a Compose ARK Box: MySQL dump + install secrets + storage.
# Redis, images, and logs are not shop truth — omit them.
#
# Run on the Box host from the Compose project directory, or set ARK_COMPOSE_DIR.
set -euo pipefail

ARK_COMPOSE_DIR="${ARK_COMPOSE_DIR:-/opt/ark}"
BACKUP_ROOT="${ARK_BOX_BACKUP_ROOT:-/var/backups/ark-box}"
KEEP="${ARK_BOX_BACKUP_KEEP:-3}"
STAMP="$(date -u +%Y%m%dT%H%M%SZ)"
DEST="${BACKUP_ROOT}/${STAMP}"

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

if ! compose ps --status running --services 2>/dev/null | grep -qx mysql; then
    echo "backup-box: mysql service is not running in ${ARK_COMPOSE_DIR}" >&2
    exit 1
fi

MYSQL_CONTAINER="$(compose ps -q mysql)"
APP_CONTAINER="$(compose ps -q app || true)"
umask 077
mkdir -p "${DEST}"

echo "backup-box: writing ${DEST}"

compose exec -T mysql sh -c '
    set -eu
    . /run/ark/secrets/install.env
    umask 077
    conf=/tmp/ark-box-dump.cnf
    printf "[client]\nuser=root\npassword=%s\n" "$MYSQL_ROOT_PASSWORD" > "$conf"
    mysqldump --defaults-extra-file="$conf" \
        --single-transaction --routines --triggers --no-tablespaces \
        "${DB_DATABASE:-ark}"
    rm -f "$conf"
' | gzip -c > "${DEST}/ark.sql.gz"

SECRETS_VOL="$(docker inspect -f '{{range .Mounts}}{{if eq .Destination "/run/ark/secrets"}}{{.Name}}{{end}}{{end}}' "${MYSQL_CONTAINER}")"
STORAGE_VOL=""
if [ -n "${APP_CONTAINER}" ]; then
    STORAGE_VOL="$(docker inspect -f '{{range .Mounts}}{{if eq .Destination "/app/storage"}}{{.Name}}{{end}}{{end}}' "${APP_CONTAINER}")"
fi

docker run --rm -v "${SECRETS_VOL}:/src:ro" -v "${DEST}:/dst" alpine:3.20 \
    tar czf /dst/secrets.tar.gz -C /src .

if [ -n "${STORAGE_VOL}" ]; then
    docker run --rm -v "${STORAGE_VOL}:/src:ro" -v "${DEST}:/dst" alpine:3.20 \
        tar czf /dst/storage.tar.gz -C /src .
else
    echo "backup-box: app storage volume not found; secrets + SQL only" >&2
fi

IMAGE=""
DIGEST=""
if [ -n "${APP_CONTAINER}" ]; then
    IMAGE="$(docker inspect -f '{{.Config.Image}}' "${APP_CONTAINER}")"
    IMAGE_ID="$(docker inspect -f '{{.Image}}' "${APP_CONTAINER}")"
    DIGEST="$(docker inspect -f '{{if .RepoDigests}}{{index .RepoDigests 0}}{{end}}' "${IMAGE_ID}" 2>/dev/null || true)"
fi

{
    echo "box_backup 1"
    echo "created_at ${STAMP}"
    echo "hostname $(hostname)"
    echo "compose_dir ${ARK_COMPOSE_DIR}"
    echo "image ${IMAGE}"
    echo "digest ${DIGEST}"
    echo "sql_bytes $(wc -c < "${DEST}/ark.sql.gz" | tr -d ' ')"
    echo "secrets_bytes $(wc -c < "${DEST}/secrets.tar.gz" | tr -d ' ')"
    if [ -f "${DEST}/storage.tar.gz" ]; then
        echo "storage_bytes $(wc -c < "${DEST}/storage.tar.gz" | tr -d ' ')"
    fi
} > "${DEST}/manifest.txt"

gzip -t "${DEST}/ark.sql.gz"
tar tzf "${DEST}/secrets.tar.gz" >/dev/null

ln -sfn "${STAMP}" "${BACKUP_ROOT}/latest"
echo "${DEST}" > "${BACKUP_ROOT}/LATEST"

if [ "${KEEP}" -gt 0 ]; then
    # shellcheck disable=SC2012
    ls -1dt "${BACKUP_ROOT}"/20* 2>/dev/null | tail -n "+$((KEEP + 1))" | while read -r old; do
        rm -rf "${old}"
    done
fi

echo "backup-box: ok ${DEST}"
cat "${DEST}/manifest.txt"
