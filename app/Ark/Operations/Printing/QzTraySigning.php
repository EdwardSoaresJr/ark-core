<?php

declare(strict_types=1);

namespace App\Ark\Operations\Printing;

use Illuminate\Support\Facades\Cache;
use RuntimeException;

/**
 * QZ Tray message signing (server-held private key, public cert in the browser).
 *
 * The shop's own pair lives in storage_path('app/private/qz'): digital-certificate.txt (signing
 * certificate), private-key.pem (its key, mode 600) and override.crt (the root workstations trust).
 * A hosted platform scopes storage_path() per shop, so each shop signs with its own pair. When that
 * folder holds neither file, QZ_CERTIFICATE_PATH / QZ_PRIVATE_KEY_PATH (and QZ_TRUST_ROOT_PATH,
 * default override.crt beside the certificate) are used.
 *
 * @see https://qz.io/docs/signing
 */
final class QzTraySigning
{
    private const SELF_TEST_PAYLOAD = 'ark-sms-qz-signing-selftest-v1';

    public const SHOP_DIRECTORY = 'app/private/qz';

    public const CERTIFICATE_FILE = 'digital-certificate.txt';

    public const PRIVATE_KEY_FILE = 'private-key.pem';

    public const TRUST_ROOT_FILE = 'override.crt';

    public const SOURCE_SHOP = 'shop';

    public const SOURCE_CONFIGURED = 'configured';

    public const SOURCE_NONE = 'none';

    /** A signing certificate this close to its end date is reported as expiring. */
    public const EXPIRY_WARNING_DAYS = 45;

    public static function shopDirectory(): string
    {
        return storage_path(self::SHOP_DIRECTORY);
    }

    /**
     * Where the signing pair comes from: the shop's own folder when it holds either file,
     * otherwise the configured paths.
     *
     * @return self::SOURCE_*
     */
    public static function source(): string
    {
        $dir = self::shopDirectory();
        if (is_file($dir.'/'.self::CERTIFICATE_FILE) || is_file($dir.'/'.self::PRIVATE_KEY_FILE)) {
            return self::SOURCE_SHOP;
        }

        $configured = trim((string) config('printing.qz.certificate_path', '')) !== ''
            || trim((string) config('printing.qz.private_key_path', '')) !== '';

        return $configured ? self::SOURCE_CONFIGURED : self::SOURCE_NONE;
    }

    private static function certificatePathConfigured(): ?string
    {
        return match (self::source()) {
            self::SOURCE_SHOP => self::shopDirectory().'/'.self::CERTIFICATE_FILE,
            self::SOURCE_CONFIGURED => self::configuredPath('printing.qz.certificate_path'),
            default => null,
        };
    }

    private static function privateKeyPathConfigured(): ?string
    {
        return match (self::source()) {
            self::SOURCE_SHOP => self::shopDirectory().'/'.self::PRIVATE_KEY_FILE,
            self::SOURCE_CONFIGURED => self::configuredPath('printing.qz.private_key_path'),
            default => null,
        };
    }

    private static function trustRootPathConfigured(): ?string
    {
        return match (self::source()) {
            self::SOURCE_SHOP => self::shopDirectory().'/'.self::TRUST_ROOT_FILE,
            self::SOURCE_CONFIGURED => self::configuredPath('printing.qz.trust_root_path')
                ?? (($cert = self::configuredPath('printing.qz.certificate_path')) !== null
                    ? dirname($cert).'/'.self::TRUST_ROOT_FILE
                    : null),
            default => null,
        };
    }

    private static function configuredPath(string $key): ?string
    {
        $p = trim((string) config($key, ''));

        return $p !== '' ? $p : null;
    }

    /**
     * Resolve a PEM file path from .env: accepts absolute paths, or paths relative to the
     * Laravel base directory (PHP-FPM cwd is often not the project root).
     */
    private static function resolvePemFilesystemPath(?string $configured): ?string
    {
        if ($configured === null || $configured === '') {
            return null;
        }
        if (is_file($configured) && is_readable($configured)) {
            return $configured;
        }
        if (! self::isAbsoluteFilesystemPath($configured)) {
            $fromBase = base_path($configured);
            if (is_file($fromBase) && is_readable($fromBase)) {
                return $fromBase;
            }
        }

        return null;
    }

    private static function isAbsoluteFilesystemPath(string $path): bool
    {
        if (str_starts_with($path, '/')) {
            return true;
        }

        return (bool) preg_match('/^[A-Za-z]:[\\\\\\/]/', $path);
    }

    private static function certificateFilesystemPath(): ?string
    {
        return self::resolvePemFilesystemPath(self::certificatePathConfigured());
    }

    private static function privateKeyFilesystemPath(): ?string
    {
        return self::resolvePemFilesystemPath(self::privateKeyPathConfigured());
    }

    private static function pathNotReadableHint(string $configured): string
    {
        if (is_file($configured) && ! is_readable($configured)) {
            return __('File exists but is not readable by the PHP user (chmod / ownership / open_basedir).');
        }

        if (self::source() === self::SOURCE_SHOP) {
            return __('The shop\'s printing folder has a private key but no signing certificate (:file).', ['file' => self::CERTIFICATE_FILE]);
        }

        $checked = self::isAbsoluteFilesystemPath($configured)
            ? ''
            : ' '.(__('Also tried: :path', ['path' => base_path($configured)]));

        return __('No readable certificate file at the configured path. Use an absolute path on the server (recommended for PHP-FPM), or a path relative to the Laravel project root (not the PHP working directory).').$checked;
    }

    private static function privateKeyNotReadableHint(string $configured): string
    {
        if (is_file($configured) && ! is_readable($configured)) {
            return __('File exists but is not readable by the PHP user (chmod 600 typical; check ownership / open_basedir).');
        }

        if (self::source() === self::SOURCE_SHOP) {
            return __('The shop\'s printing folder has a signing certificate but no private key (:file).', ['file' => self::PRIVATE_KEY_FILE]);
        }

        $checked = self::isAbsoluteFilesystemPath($configured)
            ? ''
            : ' '.(__('Also tried: :path', ['path' => base_path($configured)]));

        return __('No readable private key file at the configured path. Use an absolute path on the server, or a path relative to the Laravel project root.').$checked;
    }

    /**
     * Trim, strip UTF-8 BOM, normalize newlines (fixes common editor / Windows exports).
     */
    private static function normalizePemFileContents(string $raw): string
    {
        $s = str_replace("\r\n", "\n", $raw);
        $s = trim($s);
        if (str_starts_with($s, "\xEF\xBB\xBF")) {
            $s = trim(substr($s, 3));
        }

        return trim($s);
    }

    private static function drainOpenSslErrorQueue(): void
    {
        while (openssl_error_string() !== false) {
        }
    }

    /**
     * @return list<string>
     */
    private static function collectOpenSslErrors(int $max = 6): array
    {
        $out = [];
        while (count($out) < $max) {
            $e = openssl_error_string();
            if ($e === false) {
                break;
            }
            $out[] = $e;
        }

        return $out;
    }

    /**
     * First PEM certificate block only (full chain files, or noise after the cert).
     */
    private static function firstCertificatePemBlock(string $normalized): ?string
    {
        if (preg_match('/-----BEGIN CERTIFICATE-----[\s\S]+?-----END CERTIFICATE-----/', $normalized, $m)) {
            return trim($m[0]);
        }

        return null;
    }

    /**
     * Resolve PEM string suitable for openssl_x509_read / browser injection.
     */
    private static function resolveCertificatePemString(string $raw): ?string
    {
        $normalized = self::normalizePemFileContents($raw);
        if ($normalized === '') {
            return null;
        }
        self::drainOpenSslErrorQueue();
        if (@openssl_x509_read($normalized) !== false) {
            return $normalized;
        }
        $first = self::firstCertificatePemBlock($normalized);
        if ($first !== null) {
            self::drainOpenSslErrorQueue();
            if (@openssl_x509_read($first) !== false) {
                return $first;
            }
        }

        return null;
    }

    private static function resolvePrivateKeyPemString(string $raw): string
    {
        return self::normalizePemFileContents($raw);
    }

    public static function isFullyConfigured(): bool
    {
        return self::healthSnapshot()['ready'];
    }

    /**
     * Certificate and private key paths are set and files are readable (PEM validity not checked).
     */
    public static function isConfigured(): bool
    {
        $cPath = self::certificateFilesystemPath();
        $kPath = self::privateKeyFilesystemPath();

        return $cPath !== null && $kPath !== null;
    }

    /**
     * Path and readability checks for dashboards and monitoring (no sign/verify).
     *
     * @return array{
     *     configured: bool,
     *     cert_exists: bool,
     *     key_exists: bool,
     *     cert_readable: bool,
     *     key_readable: bool,
     *     ready: bool,
     * }
     */
    public static function health(): array
    {
        $cConfigured = self::certificatePathConfigured();
        $kConfigured = self::privateKeyPathConfigured();
        $cPath = self::certificateFilesystemPath();
        $kPath = self::privateKeyFilesystemPath();

        $certExists = $cConfigured !== null && ($cPath !== null || @file_exists($cConfigured));
        $keyExists = $kConfigured !== null && ($kPath !== null || @file_exists($kConfigured));
        $certReadable = $cPath !== null;
        $keyReadable = $kPath !== null;

        return [
            'configured' => self::isConfigured(),
            'cert_exists' => $certExists,
            'key_exists' => $keyExists,
            'cert_readable' => $certReadable,
            'key_readable' => $keyReadable,
            'ready' => self::isFullyConfigured(),
        ];
    }

    /**
     * `ready` means the server can sign: a parseable certificate in its validity window and the
     * private key that belongs to it. The trust root and file mode are reported, not required.
     *
     * @return array{
     *     ready: bool,
     *     source: string,
     *     certificate_path_set: bool,
     *     certificate_readable: bool,
     *     certificate_valid: bool,
     *     private_key_path_set: bool,
     *     private_key_readable: bool,
     *     private_key_valid: bool,
     *     private_key_matches_certificate: bool,
     *     private_key_permissions_ok: bool,
     *     certificate_in_validity_window: bool,
     *     certificate_expires_soon: bool,
     *     certificate: ?array{common_name: string, organization: string, not_before: string, not_after: string, sha256: string, sha1: string},
     *     trust_root: ?array{common_name: string, organization: string, not_before: string, not_after: string, sha256: string, sha1: string},
     *     trust_root_valid: bool,
     *     chains_to_trust_root: bool,
     *     certificate_error_hint: string,
     *     private_key_error_hint: string,
     *     trust_root_error_hint: string,
     * }
     */
    public static function healthSnapshot(): array
    {
        $snapshot = self::pemHealth();

        $certificate = null;
        $inWindow = false;
        $expiresSoon = false;
        $matches = false;
        $certPem = $snapshot['certificate_valid'] ? self::certificateContents() : null;
        if ($certPem !== null) {
            $certificate = self::describeCertificate($certPem);
            $now = now()->getTimestamp();
            $notBefore = (int) (openssl_x509_parse($certPem)['validFrom_time_t'] ?? 0);
            $notAfter = (int) (openssl_x509_parse($certPem)['validTo_time_t'] ?? 0);
            $inWindow = $notBefore <= $now && $now <= $notAfter;
            $expiresSoon = $inWindow && $notAfter - $now < self::EXPIRY_WARNING_DAYS * 86400;
            if (! $inWindow) {
                $snapshot['certificate_error_hint'] = $notAfter < $now
                    ? __('The signing certificate expired on :date. Issue a new pair (see docs/printing/workstation-setup.md).', ['date' => gmdate('Y-m-d', $notAfter)])
                    : __('The signing certificate is not valid until :date.', ['date' => gmdate('Y-m-d', $notBefore)]);
            }
        }

        if ($certPem !== null && $snapshot['private_key_valid']) {
            $key = self::privateKeyHandle();
            $matches = $key !== null && openssl_x509_check_private_key($certPem, $key);
            if (! $matches) {
                $snapshot['private_key_error_hint'] = __('The private key does not belong to the signing certificate. Put the matching pair together.');
            }
        }

        $permissionsOk = true;
        $keyPath = self::privateKeyFilesystemPath();
        if ($keyPath !== null && DIRECTORY_SEPARATOR === '/') {
            $mode = @fileperms($keyPath);
            $permissionsOk = $mode !== false && ($mode & 0o077) === 0;
        }

        [$trustRoot, $trustRootValid, $chains, $trustRootHint] = self::trustRootHealth($certPem);

        $snapshot['ready'] = $snapshot['ready'] && $inWindow && $matches;

        return $snapshot + [
            'source' => self::source(),
            'private_key_matches_certificate' => $matches,
            'private_key_permissions_ok' => $permissionsOk,
            'certificate_in_validity_window' => $inWindow,
            'certificate_expires_soon' => $expiresSoon,
            'certificate' => $certificate,
            'trust_root' => $trustRoot,
            'trust_root_valid' => $trustRootValid,
            'chains_to_trust_root' => $chains,
            'trust_root_error_hint' => $trustRootHint,
        ];
    }

    /**
     * The public root workstations trust (override.crt), when it is a CA certificate in its validity window.
     */
    public static function trustRootContents(): ?string
    {
        $path = self::resolvePemFilesystemPath(self::trustRootPathConfigured());
        if ($path === null) {
            return null;
        }
        $raw = @file_get_contents($path);
        if (! is_string($raw)) {
            return null;
        }
        $pem = self::resolveCertificatePemString($raw);
        if ($pem === null || ! self::isCertificateAuthority($pem) || ! self::inValidityWindow($pem)) {
            return null;
        }

        return $pem;
    }

    /**
     * @return array{common_name: string, organization: string, not_before: string, not_after: string, sha256: string, sha1: string}
     */
    public static function describeCertificate(string $pem): array
    {
        $parsed = openssl_x509_parse($pem) ?: [];

        return [
            'common_name' => (string) ($parsed['subject']['CN'] ?? ''),
            'organization' => (string) ($parsed['subject']['O'] ?? ''),
            'not_before' => gmdate('Y-m-d', (int) ($parsed['validFrom_time_t'] ?? 0)),
            'not_after' => gmdate('Y-m-d', (int) ($parsed['validTo_time_t'] ?? 0)),
            'sha256' => (string) openssl_x509_fingerprint($pem, 'sha256'),
            'sha1' => (string) openssl_x509_fingerprint($pem, 'sha1'),
        ];
    }

    /**
     * @return array{0: ?array<string, string>, 1: bool, 2: bool, 3: string}
     */
    private static function trustRootHealth(?string $certPem): array
    {
        $path = self::trustRootPathConfigured();
        $readable = self::resolvePemFilesystemPath($path);
        if ($readable === null) {
            return [null, false, false, $path === null ? '' : __('No trust root (:file) beside the signing pair. Workstation setup needs it.', ['file' => self::TRUST_ROOT_FILE])];
        }
        $raw = @file_get_contents($readable);
        $pem = is_string($raw) ? self::resolveCertificatePemString($raw) : null;
        if ($pem === null) {
            return [null, false, false, __('The trust root is not a PEM certificate.')];
        }
        $described = self::describeCertificate($pem);
        if (! self::isCertificateAuthority($pem)) {
            return [$described, false, false, __('The trust root is not a CA certificate (basicConstraints CA:TRUE).')];
        }
        if (! self::inValidityWindow($pem)) {
            return [$described, false, false, __('The trust root is outside its validity window (until :date).', ['date' => $described['not_after']])];
        }
        $chains = $certPem !== null && self::issuedBy($certPem, $pem);

        return [$described, true, $chains, $chains || $certPem === null ? '' : __('The signing certificate was not issued by this trust root, so workstations that trust it will still prompt.')];
    }

    private static function issuedBy(string $leafPem, string $rootPem): bool
    {
        $leaf = openssl_x509_parse($leafPem) ?: [];
        $root = openssl_x509_parse($rootPem) ?: [];
        if (($leaf['issuer'] ?? null) !== ($root['subject'] ?? null)) {
            return false;
        }
        $rootKey = openssl_pkey_get_public($rootPem);

        return $rootKey !== false && openssl_x509_verify($leafPem, $rootKey) === 1;
    }

    private static function isCertificateAuthority(string $pem): bool
    {
        $constraints = (string) ((openssl_x509_parse($pem) ?: [])['extensions']['basicConstraints'] ?? '');

        return str_contains(strtoupper($constraints), 'CA:TRUE');
    }

    private static function inValidityWindow(string $pem): bool
    {
        $parsed = openssl_x509_parse($pem) ?: [];
        $now = now()->getTimestamp();

        return (int) ($parsed['validFrom_time_t'] ?? PHP_INT_MAX) <= $now && $now <= (int) ($parsed['validTo_time_t'] ?? 0);
    }

    private static function privateKeyHandle(): ?\OpenSSLAsymmetricKey
    {
        $path = self::privateKeyFilesystemPath();
        $raw = $path !== null ? @file_get_contents($path) : false;
        if (! is_string($raw)) {
            return null;
        }
        $pass = (string) config('printing.qz.private_key_passphrase', '');
        $key = @openssl_pkey_get_private(self::resolvePrivateKeyPemString($raw), $pass !== '' ? $pass : null);

        return $key !== false ? $key : null;
    }

    /**
     * @return array{
     *     ready: bool,
     *     certificate_path_set: bool,
     *     certificate_readable: bool,
     *     certificate_valid: bool,
     *     private_key_path_set: bool,
     *     private_key_readable: bool,
     *     private_key_valid: bool,
     *     certificate_error_hint: string,
     *     private_key_error_hint: string,
     * }
     */
    private static function pemHealth(): array
    {
        $cConfigured = self::certificatePathConfigured();
        $kConfigured = self::privateKeyPathConfigured();
        $certificatePathSet = $cConfigured !== null;
        $privateKeyPathSet = $kConfigured !== null;

        $cPath = self::certificateFilesystemPath();
        $kPath = self::privateKeyFilesystemPath();
        $certificateReadable = $cPath !== null;
        $privateKeyReadable = $kPath !== null;

        $certificateValid = false;
        $certificateErrorHint = '';
        if ($certificateReadable) {
            $cRaw = @file_get_contents($cPath);
            if (! is_string($cRaw) || self::normalizePemFileContents($cRaw) === '') {
                $certificateErrorHint = __('File is empty after trim.');
            } else {
                $resolved = self::resolveCertificatePemString($cRaw);
                if ($resolved !== null) {
                    $certificateValid = true;
                } else {
                    self::drainOpenSslErrorQueue();
                    @openssl_x509_read(self::normalizePemFileContents($cRaw));
                    $errs = self::collectOpenSslErrors(4);
                    $certificateErrorHint = $errs !== []
                        ? implode('; ', $errs)
                        : __('Not a valid X.509 PEM (expect BEGIN CERTIFICATE).');
                }
            }
        } elseif ($certificatePathSet && $cConfigured !== null) {
            $certificateErrorHint = self::pathNotReadableHint($cConfigured);
        }

        $privateKeyValid = false;
        $privateKeyErrorHint = '';
        if ($privateKeyReadable) {
            $kRaw = @file_get_contents($kPath);
            $pass = (string) config('printing.qz.private_key_passphrase', '');
            if (! is_string($kRaw) || self::resolvePrivateKeyPemString($kRaw) === '') {
                $privateKeyErrorHint = __('File is empty after trim.');
            } else {
                $kPem = self::resolvePrivateKeyPemString($kRaw);
                self::drainOpenSslErrorQueue();
                $pkey = @openssl_pkey_get_private($kPem, $pass !== '' ? $pass : null);
                if ($pkey !== false) {
                    $privateKeyValid = true;
                    unset($pkey);
                } else {
                    $errs = self::collectOpenSslErrors(4);
                    $privateKeyErrorHint = $errs !== []
                        ? implode('; ', $errs)
                        : __('Could not parse private key (wrong passphrase, DER/PKCS12, or not PEM).');
                }
            }
        } elseif ($privateKeyPathSet && $kConfigured !== null) {
            $privateKeyErrorHint = self::privateKeyNotReadableHint($kConfigured);
        }

        $ready = $certificatePathSet && $privateKeyPathSet
            && $certificateReadable && $privateKeyReadable
            && $certificateValid && $privateKeyValid;

        return [
            'ready' => $ready,
            'certificate_path_set' => $certificatePathSet,
            'certificate_readable' => $certificateReadable,
            'certificate_valid' => $certificateValid,
            'private_key_path_set' => $privateKeyPathSet,
            'private_key_readable' => $privateKeyReadable,
            'private_key_valid' => $privateKeyValid,
            'certificate_error_hint' => $certificateErrorHint,
            'private_key_error_hint' => $privateKeyErrorHint,
        ];
    }

    /**
     * Sign a fixed payload and verify with the public cert (health check, no cache).
     */
    public static function selfTestSigningRoundTrip(): bool
    {
        if (! self::isFullyConfigured()) {
            return false;
        }
        try {
            $sig = self::signOpenSsl(self::SELF_TEST_PAYLOAD);

            return self::verifySignatureLocally(self::SELF_TEST_PAYLOAD, $sig);
        } catch (RuntimeException) {
            return false;
        }
    }

    public static function certificateContents(): ?string
    {
        $path = self::certificateFilesystemPath();
        if ($path === null) {
            return null;
        }
        $raw = file_get_contents($path);
        if ($raw === false) {
            return null;
        }
        $resolved = self::resolveCertificatePemString($raw);

        return $resolved !== null ? $resolved : null;
    }

    /**
     * @return 'SHA512'|'SHA256'|'SHA1'
     */
    public static function javascriptSignatureAlgorithm(): string
    {
        $normalized = strtolower((string) config('printing.qz.signature_algorithm', 'sha512'));

        return match ($normalized) {
            'sha1' => 'SHA1',
            'sha256' => 'SHA256',
            default => 'SHA512',
        };
    }

    /**
     * Base64-encoded RSA signature (short-lived cache per signing certificate + payload hash).
     */
    public static function signBase64(string $data): string
    {
        $certPem = self::certificateContents();
        $pairSegment = $certPem !== null ? (string) openssl_x509_fingerprint($certPem, 'sha256') : 'none';
        $ttl = max(5, min(300, (int) config('printing.qz.sign_cache_ttl', 30)));
        $cacheKey = 'qz:sign:'.hash('sha256', $pairSegment.'|'.$data);

        return Cache::remember($cacheKey, $ttl, function () use ($data) {
            return self::signOpenSsl($data);
        });
    }

    public static function verifySignatureLocally(string $data, string $signatureBase64): bool
    {
        $certPem = self::certificateContents();
        if ($certPem === null) {
            return false;
        }
        $cert = @openssl_x509_read($certPem);
        if ($cert === false) {
            return false;
        }
        $pub = @openssl_pkey_get_public($cert);
        if ($pub === false) {
            return false;
        }
        $bin = base64_decode($signatureBase64, true);
        if ($bin === false || $bin === '') {
            return false;
        }
        $v = openssl_verify($data, $bin, $pub, self::opensslAlgorithmConstant());

        return $v === 1;
    }

    /**
     * Base64-encoded RSA signature of the exact string QZ sends as `data`.
     */
    private static function signOpenSsl(string $data): string
    {
        $path = self::privateKeyFilesystemPath();
        if ($path === null) {
            throw new RuntimeException('QZ private key is not available.');
        }
        $keyPemRaw = file_get_contents($path);
        if ($keyPemRaw === false) {
            throw new RuntimeException('QZ private key could not be read.');
        }
        $keyPem = self::resolvePrivateKeyPemString($keyPemRaw);
        if ($keyPem === '') {
            throw new RuntimeException('QZ private key could not be read.');
        }

        $pass = (string) config('printing.qz.private_key_passphrase', '');
        $key = openssl_pkey_get_private($keyPem, $pass !== '' ? $pass : null);
        if ($key === false) {
            throw new RuntimeException('QZ private key could not be parsed (wrong passphrase or format).');
        }

        try {
            $algo = self::opensslAlgorithmConstant();
            $signature = '';
            if (! openssl_sign($data, $signature, $key, $algo)) {
                throw new RuntimeException('openssl_sign failed for QZ message.');
            }

            return base64_encode($signature);
        } finally {
            unset($key);
        }
    }

    private static function opensslAlgorithmConstant(): int
    {
        $normalized = strtolower((string) config('printing.qz.signature_algorithm', 'sha512'));

        return match ($normalized) {
            'sha1' => OPENSSL_ALGO_SHA1,
            'sha256' => OPENSSL_ALGO_SHA256,
            default => OPENSSL_ALGO_SHA512,
        };
    }
}
