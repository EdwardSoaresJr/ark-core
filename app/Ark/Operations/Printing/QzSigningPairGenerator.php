<?php

declare(strict_types=1);

namespace App\Ark\Operations\Printing;

use RuntimeException;

/**
 * Creates a shop's QZ Tray signing pair in QzTraySigning::shopDirectory(): a root workstations trust
 * (override.crt), a signing certificate issued by it (digital-certificate.txt) and that certificate's
 * key (private-key.pem, PKCS#8, mode 600). The root's own key is never written: renewing means a new
 * pair and running Print Setup again on each workstation. Refuses when the shop already has any of
 * these files or signs with configured paths.
 */
final class QzSigningPairGenerator
{
    public const ROOT_DAYS = 3650;

    public const SIGNING_DAYS = 1825;

    /**
     * @return array{certificate: array<string, string>, trust_root: array<string, string>}
     */
    public function generate(string $organization): array
    {
        if (QzTraySigning::source() !== QzTraySigning::SOURCE_NONE || $this->anyShopFileExists()) {
            throw new RuntimeException('This shop already has a printing certificate or signs with configured paths. Nothing was changed.');
        }

        $organization = $this->organization($organization);
        $cnf = $this->writeOpenSslConfig();

        try {
            $options = ['config' => $cnf, 'digest_alg' => 'sha256', 'private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA];

            $rootKey = openssl_pkey_new($options);
            $rootCsr = $rootKey !== false ? openssl_csr_new(['organizationName' => $organization, 'organizationalUnitName' => 'Printing', 'commonName' => 'ARK Root CA'], $rootKey, $options) : false;
            $root = $rootCsr instanceof \OpenSSLCertificateSigningRequest
                ? openssl_csr_sign($rootCsr, null, $rootKey, self::ROOT_DAYS, $options + ['x509_extensions' => 'ark_root'], random_int(1, PHP_INT_MAX))
                : false;

            $leafKey = openssl_pkey_new($options);
            $leafCsr = $leafKey !== false ? openssl_csr_new(['organizationName' => $organization, 'organizationalUnitName' => 'Printing', 'commonName' => 'ARK Printing Signing'], $leafKey, $options) : false;
            $leaf = $root !== false && $leafCsr instanceof \OpenSSLCertificateSigningRequest
                ? openssl_csr_sign($leafCsr, $root, $rootKey, self::SIGNING_DAYS, $options + ['x509_extensions' => 'ark_signing'], random_int(1, PHP_INT_MAX))
                : false;

            if ($root === false || $leaf === false
                || ! openssl_x509_export($root, $rootPem)
                || ! openssl_x509_export($leaf, $leafPem)
                || ! openssl_pkey_export($leafKey, $keyPem, null, $options)) {
                throw new RuntimeException('OpenSSL could not create the printing certificates.');
            }
            unset($rootKey);
        } finally {
            @unlink($cnf);
        }

        if (! str_contains($keyPem, '-----BEGIN PRIVATE KEY-----')) {
            throw new RuntimeException('OpenSSL did not export a PKCS#8 key. Nothing was written.');
        }

        $this->write($rootPem, $leafPem, $keyPem);

        $health = QzTraySigning::healthSnapshot();
        if (! $health['ready'] || ! $health['chains_to_trust_root']) {
            throw new RuntimeException('The new pair did not pass the signing check: '.trim($health['certificate_error_hint'].' '.$health['private_key_error_hint'].' '.$health['trust_root_error_hint']));
        }

        return ['certificate' => (array) $health['certificate'], 'trust_root' => (array) $health['trust_root']];
    }

    private function anyShopFileExists(): bool
    {
        $dir = QzTraySigning::shopDirectory();
        foreach ([QzTraySigning::CERTIFICATE_FILE, QzTraySigning::PRIVATE_KEY_FILE, QzTraySigning::TRUST_ROOT_FILE] as $file) {
            if (file_exists($dir.'/'.$file)) {
                return true;
            }
        }

        return false;
    }

    private function organization(string $organization): string
    {
        $clean = trim((string) preg_replace('/[^\p{L}\p{N} .,&\'()-]+/u', '', $organization));

        return mb_substr($clean !== '' ? $clean : 'Auto Repair Keeper', 0, 64);
    }

    private function write(string $rootPem, string $leafPem, string $keyPem): void
    {
        $dir = QzTraySigning::shopDirectory();
        if (! is_dir($dir) && ! mkdir($dir, 0o700, true) && ! is_dir($dir)) {
            throw new RuntimeException('Could not create the shop printing folder.');
        }
        @chmod($dir, 0o700);

        $previous = umask(0o077);
        try {
            $this->writeFile($dir.'/'.QzTraySigning::PRIVATE_KEY_FILE, $keyPem, 0o600);
            $this->writeFile($dir.'/'.QzTraySigning::CERTIFICATE_FILE, $leafPem, 0o644);
            $this->writeFile($dir.'/'.QzTraySigning::TRUST_ROOT_FILE, $rootPem, 0o644);
        } finally {
            umask($previous);
        }
    }

    private function writeFile(string $path, string $contents, int $mode): void
    {
        $temporary = $path.'.'.bin2hex(random_bytes(4)).'.tmp';
        if (file_put_contents($temporary, $contents, LOCK_EX) === false || ! chmod($temporary, $mode) || ! rename($temporary, $path)) {
            @unlink($temporary);
            throw new RuntimeException('Could not write '.basename($path).'.');
        }
    }

    private function writeOpenSslConfig(): string
    {
        $path = tempnam(sys_get_temp_dir(), 'ark-qz-cnf-');
        if ($path === false) {
            throw new RuntimeException('Could not prepare the OpenSSL configuration.');
        }

        file_put_contents($path, <<<'CNF'
[ req ]
distinguished_name = ark_dn
prompt = no

[ ark_dn ]

[ ark_root ]
basicConstraints = critical, CA:TRUE, pathlen:0
keyUsage = critical, keyCertSign, cRLSign
subjectKeyIdentifier = hash

[ ark_signing ]
basicConstraints = critical, CA:FALSE
keyUsage = critical, digitalSignature, keyEncipherment
extendedKeyUsage = codeSigning, clientAuth
subjectKeyIdentifier = hash
authorityKeyIdentifier = keyid, issuer
CNF);

        return $path;
    }
}
