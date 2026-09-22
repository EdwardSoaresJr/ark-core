#!/usr/bin/env bash
# Run backup-box.sh on a remote Compose Box and copy the stamp off-box.
# Default: ARK Demo → this host's /data/ark-backups/demo
set -euo pipefail

BOX_HOST="${ARK_BOX_BACKUP_SSH:-root@104.238.144.183}"
SSH_KEY="${ARK_BOX_BACKUP_KEY:-/root/.ssh/ark_demo_backup}"
REMOTE_ROOT="${ARK_BOX_BACKUP_REMOTE_ROOT:-/var/backups/ark-box}"
LOCAL_ROOT="${ARK_BOX_BACKUP_LOCAL_ROOT:-/data/ark-backups/demo}"
KEEP="${ARK_BOX_BACKUP_LOCAL_KEEP:-14}"

ssh_box() {
    ssh -o BatchMode=yes -o IdentitiesOnly=yes -i "${SSH_KEY}" "${BOX_HOST}" "$@"
}

ssh_box '/usr/local/sbin/ark-box-backup'
STAMP="$(ssh_box "readlink ${REMOTE_ROOT}/latest")"
if [ -z "${STAMP}" ]; then
    echo "pull-box-backup: remote latest stamp missing" >&2
    exit 1
fi

umask 077
mkdir -p "${LOCAL_ROOT}/${STAMP}"
scp -o BatchMode=yes -o IdentitiesOnly=yes -i "${SSH_KEY}" -r \
    "${BOX_HOST}:${REMOTE_ROOT}/${STAMP}/." "${LOCAL_ROOT}/${STAMP}/"
ln -sfn "${STAMP}" "${LOCAL_ROOT}/latest"
echo "${LOCAL_ROOT}/${STAMP}" > "${LOCAL_ROOT}/LATEST"
gzip -t "${LOCAL_ROOT}/${STAMP}/ark.sql.gz"

if [ "${KEEP}" -gt 0 ]; then
    # shellcheck disable=SC2012
    ls -1dt "${LOCAL_ROOT}"/20* 2>/dev/null | tail -n "+$((KEEP + 1))" | while read -r old; do
        rm -rf "${old}"
    done
fi

echo "pull-box-backup: ok ${LOCAL_ROOT}/${STAMP}"
cat "${LOCAL_ROOT}/${STAMP}/manifest.txt"
