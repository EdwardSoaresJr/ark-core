#!/usr/bin/env bash
# ARK Self-Signed Printing — development certificate generator (DO NOT deploy to production).
#
# Produces an ARK-owned chain for stock QZ Tray evaluation:
#   ARK Root CA → ARK Printing Signing Certificate
#
# Outputs land in infra/qz-dev/certs/ (gitignored).
set -euo pipefail

script_dir="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
out_dir="${script_dir}/certs"
days_root=3650
days_signing=825
org_name="${ARK_PRINTING_ORG:-Auto Repair Keeper}"
org_unit="${ARK_PRINTING_OU:-Printing}"
common_name="${ARK_PRINTING_CN:-ARK Printing Signing}"
root_cn="${ARK_PRINTING_ROOT_CN:-ARK Root CA}"
country="${ARK_PRINTING_COUNTRY:-US}"
state="${ARK_PRINTING_STATE:-Florida}"
locality="${ARK_PRINTING_LOCALITY:-Tampa}"

mkdir -p "${out_dir}"
chmod 700 "${out_dir}"

work="$(mktemp -d)"
trap 'rm -rf "${work}"' EXIT

write_openssl_cnf() {
    local cn="$1"
    local ca_ext="$2"
    local outfile="$3"

    cat >"${outfile}" <<EOF
[req]
default_bits = 2048
prompt = no
default_md = sha256
distinguished_name = dn
x509_extensions = ext

[dn]
C = ${country}
ST = ${state}
L = ${locality}
O = ${org_name}
OU = ${org_unit}
CN = ${cn}

[ext]
${ca_ext}
EOF
}

write_signing_cnf() {
    cat >"${work}/signing.cnf" <<EOF
[req]
default_bits = 2048
prompt = no
default_md = sha256
distinguished_name = dn
req_extensions = ext

[dn]
C = ${country}
ST = ${state}
L = ${locality}
O = ${org_name}
OU = ${org_unit}
CN = ${common_name}

[ext]
basicConstraints = CA:FALSE
keyUsage = critical, digitalSignature, keyEncipherment
extendedKeyUsage = codeSigning, clientAuth
subjectKeyIdentifier = hash
EOF
}

printf '%s\n' '[ark-qz-dev] Generating 2048-bit ARK Root CA …'
write_openssl_cnf "${root_cn}" "basicConstraints = critical, CA:true, pathlen:1
keyUsage = critical, keyCertSign, cRLSign
subjectKeyIdentifier = hash" "${work}/root.cnf"

openssl genrsa -out "${work}/ark-root-ca.key.pem" 2048
openssl req -x509 -new -nodes \
    -key "${work}/ark-root-ca.key.pem" \
    -sha256 -days "${days_root}" \
    -config "${work}/root.cnf" \
    -out "${work}/ark-root-ca.crt.pem"

printf '%s\n' '[ark-qz-dev] Generating ARK Printing Signing certificate …'
write_signing_cnf

openssl genrsa -out "${work}/ark-printing-signing.key.pem" 2048
openssl req -new \
    -key "${work}/ark-printing-signing.key.pem" \
    -config "${work}/signing.cnf" \
    -out "${work}/ark-printing-signing.csr.pem"

cat >"${work}/signing-ext.cnf" <<EOF
basicConstraints = CA:FALSE
keyUsage = critical, digitalSignature, keyEncipherment
extendedKeyUsage = codeSigning, clientAuth
subjectKeyIdentifier = hash
authorityKeyIdentifier = keyid,issuer
EOF

openssl x509 -req \
    -in "${work}/ark-printing-signing.csr.pem" \
    -CA "${work}/ark-root-ca.crt.pem" \
    -CAkey "${work}/ark-root-ca.key.pem" \
    -CAcreateserial \
    -out "${work}/ark-printing-signing.crt.pem" \
    -days "${days_signing}" -sha256 \
    -extfile "${work}/signing-ext.cnf"

printf '%s\n' '[ark-qz-dev] Converting signing key to PKCS#8 (QZ expects PKCS#8 PEM) …'
openssl pkcs8 -topk8 -nocrypt \
    -in "${work}/ark-printing-signing.key.pem" \
    -out "${work}/private-key.pem"

install -m 600 "${work}/ark-root-ca.key.pem" "${out_dir}/ark-root-ca.key.pem"
install -m 644 "${work}/ark-root-ca.crt.pem" "${out_dir}/ark-root-ca.crt.pem"
install -m 644 "${work}/ark-printing-signing.crt.pem" "${out_dir}/ark-printing-signing.crt.pem"
install -m 600 "${work}/private-key.pem" "${out_dir}/private-key.pem"

# QZ naming aliases used in official docs.
cp "${out_dir}/ark-printing-signing.crt.pem" "${out_dir}/digital-certificate.txt"

# override.crt replaces QZ Tray's trusted root with ARK Root CA (stock QZ, no fork).
cp "${out_dir}/ark-root-ca.crt.pem" "${out_dir}/override.crt"

# Full chain for browsers that want leaf + issuer in one PEM.
cat "${out_dir}/ark-printing-signing.crt.pem" "${out_dir}/ark-root-ca.crt.pem" > "${out_dir}/digital-certificate-chain.txt"

cat >"${out_dir}/README.txt" <<EOF
ARK development printing certificates (NOT FOR PRODUCTION)

Generated: $(date -u +%Y-%m-%dT%H:%M:%SZ)
Organization: ${org_name}
Signing CN: ${common_name}

Files:
  digital-certificate.txt   → inject in browser (leaf cert for QZ setCertificatePromise)
  private-key.pem           → server signing only (QZ_PRIVATE_KEY_PATH)
  override.crt              → install into stock QZ Tray as trusted root override
  ark-root-ca.crt.pem       → same as override.crt
  ark-root-ca.key.pem       → keep offline; never deploy to web servers

Local .env (development only):
  QZ_CERTIFICATE_PATH=infra/qz-dev/certs/digital-certificate.txt
  QZ_PRIVATE_KEY_PATH=infra/qz-dev/certs/private-key.pem
  QZ_SIGNATURE_ALGORITHM=sha512

See docs/printing/ark-self-signed-feasibility.md for workstation trust steps.
EOF

printf '%s\n' "[ark-qz-dev] Done. Certificates written to ${out_dir}"
printf '%s\n' '[ark-qz-dev] Next: bash infra/qz-dev/verify-ark-printing-certs.sh'
