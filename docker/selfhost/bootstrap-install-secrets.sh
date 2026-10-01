#!/bin/sh
# Generate per-installation secrets before MySQL initializes.
#
# Fresh install: write unique APP_KEY, DB_PASSWORD, MYSQL_ROOT_PASSWORD,
# REVERB_APP_KEY, and REVERB_APP_SECRET.
# Existing secrets file: leave it alone (never rotate).
# Existing MySQL data without a secrets file: fail (do not invent new DB passwords).
#
# Do not print secret values.

set -eu

SECRETS_FILE="${ARK_INSTALL_SECRETS_FILE:-/ark/secrets/install.env}"
MYSQL_DATADIR="${ARK_MYSQL_DATADIR:-/var/lib/mysql}"

ark_random_alnum() {
    length="$1"
    dd if=/dev/urandom bs=48 count=1 2>/dev/null | base64 | tr -d '\n+/=' | cut -c1-"$length"
}

ark_random_app_key() {
    raw="$(dd if=/dev/urandom bs=32 count=1 2>/dev/null | base64 | tr -d '\n')"
    printf 'base64:%s' "$raw"
}

ark_write_secrets() {
    dir="$(dirname "$SECRETS_FILE")"
    mkdir -p "$dir"

    app_key="$(ark_random_app_key)"
    db_password="$(ark_random_alnum 48)"
    root_password="$(ark_random_alnum 48)"
    reverb_key="$(ark_random_alnum 32)"
    reverb_secret="$(ark_random_alnum 48)"

    if [ "$db_password" = "$root_password" ]; then
        echo "install-secrets: generated database passwords collided; retry." >&2
        return 1
    fi

    tmp="${SECRETS_FILE}.tmp"
    umask 077
    cat > "$tmp" <<EOF
APP_KEY="${app_key}"
DB_DATABASE=ark
DB_USERNAME=ark
DB_PASSWORD=${db_password}
MYSQL_ROOT_PASSWORD=${root_password}
REVERB_APP_ID=ark
REVERB_APP_KEY=${reverb_key}
REVERB_APP_SECRET=${reverb_secret}
EOF
    mv "$tmp" "$SECRETS_FILE"
    chmod 600 "$SECRETS_FILE"
}

if [ -f "$SECRETS_FILE" ] && [ -s "$SECRETS_FILE" ]; then
    chmod 600 "$SECRETS_FILE" 2>/dev/null || true
    echo "install-secrets: restored"
    exit 0
fi

if [ -d "${MYSQL_DATADIR}/mysql" ]; then
    echo "install-secrets: MySQL data exists but install secrets are missing. Restore the secrets volume or reset this installation." >&2
    exit 1
fi

ark_write_secrets
echo "install-secrets: generated"
