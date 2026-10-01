<?php

declare(strict_types=1);

namespace App\Ark\Operations\Printing;

use Illuminate\Support\Facades\Cache;
use RuntimeException;

/**
 * QZ Tray message signing (server-held private key, public cert in the browser).
 *
 * Configure via config/printing.php using env: QZ_CERTIFICATE_PATH, QZ_PRIVATE_KEY_PATH
 * (paths on disk, outside public/). chmod 600 private key, 644 cert typical.
 *
 * @see https://qz.io/docs/signing
 */
final class QzTraySigning
{
    private const SELF_TEST_PAYLOAD = 'ark-sms-qz-signing-selftest-v1';

    private static function certificatePathConfigured(): ?string
    {
        $p = trim((string) config('printing.qz.certificate_path', ''));

        return $p !== '' ? $p : null;
    }

    private static function privateKeyPathConfigured(): ?string
    {
        $p = trim((string) config('printing.qz.private_key_path', ''));

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
    public static function healthSnapshot(): array
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
     * Base64-encoded RSA signature (short-lived cache per tenant + payload hash).
     */
    public static function signBase64(string $data): string
    {
        $tenantSegment = 'shop';
        $ttl = max(5, min(300, (int) config('printing.qz.sign_cache_ttl', 30)));
        $cacheKey = 'qz:sign:'.hash('sha256', $tenantSegment.'|'.$data);

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
