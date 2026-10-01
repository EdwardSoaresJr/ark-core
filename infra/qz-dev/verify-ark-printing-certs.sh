#!/usr/bin/env bash
# Verify ARK dev printing certs against stock QZ Tray expectations.
set -euo pipefail

script_dir="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
cert_dir="${script_dir}/certs"
leaf="${cert_dir}/digital-certificate.txt"
key="${cert_dir}/private-key.pem"
root="${cert_dir}/override.crt"
fail=0

check_file() {
    local path="$1"
    if [[ ! -f "${path}" ]]; then
        printf 'MISSING %s\n' "${path}" >&2
        fail=1
        return 1
    fi
}

note() { printf 'OK   %s\n' "$1"; }
warn() { printf 'WARN %s\n' "$1"; }
bad()  { printf 'FAIL %s\n' "$1" >&2; fail=1; }

printf '%s\n' '=== ARK QZ dev certificate verification ==='

check_file "${leaf}" || exit 1
check_file "${key}" || exit 1
check_file "${root}" || exit 1

if grep -q 'BEGIN ENCRYPTED PRIVATE KEY' "${key}"; then
    bad 'Private key is encrypted; QZ server-side PHP signing expects unencrypted PKCS#8.'
else
    note 'Private key is unencrypted PKCS#8 PEM'
fi

if openssl rsa -in "${key}" -check -noout 2>/dev/null; then
    bits="$(openssl rsa -in "${key}" -text -noout 2>/dev/null | sed -n 's/.*Private-Key: (\([0-9]*\) bit.*/\1/p' | head -1)"
    if [[ "${bits}" == "2048" ]]; then
        note 'Private key is 2048-bit RSA'
    else
        bad "Private key size is ${bits:-unknown}; QZ requires 2048-bit"
    fi
else
    bad 'Private key is not a readable RSA PEM'
fi

if openssl x509 -in "${leaf}" -noout -text >/dev/null 2>&1; then
    note 'Leaf file is valid X.509 PEM'
else
    bad 'Leaf certificate is not valid X.509 PEM'
fi

if openssl x509 -in "${root}" -noout -text >/dev/null 2>&1; then
    note 'Root override file is valid X.509 PEM'
else
    bad 'Root override certificate is not valid X.509 PEM'
fi

issuer="$(openssl x509 -in "${leaf}" -noout -issuer 2>/dev/null | sed 's/^issuer=//' || true)"
subject_root="$(openssl x509 -in "${root}" -noout -subject 2>/dev/null | sed 's/^subject=//' || true)"
if [[ -n "${issuer}" && -n "${subject_root}" && "${issuer}" == "${subject_root}" ]]; then
    note 'Leaf issuer matches ARK root subject (chain links)'
else
    warn "Leaf issuer may not match root subject (issuer=${issuer:-?} root=${subject_root:-?})"
fi

if openssl verify -CAfile "${root}" "${leaf}" >/dev/null 2>&1; then
    note 'openssl verify: leaf chains to ARK root'
else
    bad 'openssl verify: leaf does NOT chain to ARK root'
fi

# Round-trip sign/verify the same way QzTraySigning does.
payload='ark-qz-dev-feasibility-selftest'
sig="$(printf '%s' "${payload}" | openssl dgst -sha512 -sign "${key}" | openssl base64 -A)"
if printf '%s' "${payload}" | openssl dgst -sha512 -verify <(openssl x509 -in "${leaf}" -pubkey -noout) -signature <(printf '%s' "${sig}" | openssl base64 -d -A) >/dev/null 2>&1; then
    note 'SHA512 sign/verify round-trip matches QzTraySigning algorithm default'
else
    bad 'SHA512 sign/verify round-trip failed'
fi

ext="$(openssl x509 -in "${leaf}" -noout -text 2>/dev/null || true)"
if grep -q 'CA:TRUE' <<<"${ext}"; then
    bad 'Leaf certificate has CA:TRUE (should be end-entity only)'
else
    note 'Leaf is end-entity (not a CA cert)'
fi

if grep -q 'Digital Signature' <<<"${ext}"; then
    note 'Leaf keyUsage includes Digital Signature'
else
    warn 'Leaf keyUsage may not list Digital Signature explicitly'
fi

not_after="$(openssl x509 -in "${leaf}" -noout -enddate 2>/dev/null | sed 's/notAfter=//')"
printf '\nLeaf expires: %s\n' "${not_after}"

if [[ "${fail}" -eq 0 ]]; then
    printf '\n%s\n' 'All required checks passed. Install override.crt into stock QZ Tray, then run the local POC.'
    exit 0
fi

printf '\n%s\n' 'One or more checks failed.' >&2
exit 1
