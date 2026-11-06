<?php

declare(strict_types=1);

namespace App\Ark\Operations\Printing;

use RuntimeException;

/**
 * The downloadable ARK Print Setup for a workstation: the shop's public trust root (override.crt) and
 * its fingerprints embedded in a script that adds it to QZ Tray's authcert.override setting.
 * Only public material is embedded; the signing key never leaves the server.
 */
final class QzWorkstationSetup
{
    public const VERSION = '1.0.0';

    public const PLATFORMS = ['windows', 'macos'];

    private const TEMPLATES = [
        'windows' => 'ark-print-setup-windows.cmd',
        'macos' => 'ark-print-setup-macos.sh',
    ];

    public static function available(): bool
    {
        return QzTraySigning::trustRootContents() !== null;
    }

    /**
     * @return array{common_name: string, organization: string, not_before: string, not_after: string, sha256: string, sha1: string}|null
     */
    public static function trustRoot(): ?array
    {
        $pem = QzTraySigning::trustRootContents();

        return $pem !== null ? QzTraySigning::describeCertificate($pem) : null;
    }

    public static function fileName(string $platform, string $host): string
    {
        $label = self::hostLabel($host);

        return $platform === 'windows'
            ? 'ARK-Print-Setup-'.$label.'-'.self::VERSION.'.cmd'
            : 'ark-print-setup-'.$label.'-'.self::VERSION.'.sh';
    }

    public static function render(string $platform, string $host, string $setupUrl): string
    {
        if (! in_array($platform, self::PLATFORMS, true)) {
            throw new RuntimeException('Unknown workstation platform.');
        }
        $host = strtolower($host);
        if (preg_match('/^[a-z0-9]([a-z0-9-]*[a-z0-9])?(\.[a-z0-9]([a-z0-9-]*[a-z0-9])?)*$/', $host) !== 1 || strlen($host) > 253) {
            throw new RuntimeException('The shop host name cannot be used in a setup file.');
        }
        if (preg_match('#^https?://[A-Za-z0-9.\-:/_%?=&]+$#', $setupUrl) !== 1) {
            throw new RuntimeException('The setup page address cannot be used in a setup file.');
        }

        $pem = QzTraySigning::trustRootContents();
        if ($pem === null) {
            throw new RuntimeException('This shop has no valid printing trust root.');
        }
        if (preg_match('/^-----BEGIN CERTIFICATE-----\n[A-Za-z0-9+\/=\n]+\n-----END CERTIFICATE-----$/', $pem) !== 1) {
            throw new RuntimeException('The printing trust root is not a plain PEM certificate.');
        }
        $root = QzTraySigning::describeCertificate($pem);

        // Core's own resources: a host application's resource_path() is not Core's.
        $template = (string) file_get_contents(dirname(__DIR__, 4).'/resources/printing/workstation/'.self::TEMPLATES[$platform]);
        $script = strtr($template, [
            '@@ARK_SETUP_VERSION@@' => self::VERSION,
            '@@ARK_SHOP_HOST@@' => $host,
            '@@ARK_ROOT_SHA256@@' => strtolower($root['sha256']),
            '@@ARK_ROOT_SHA1@@' => strtolower($root['sha1']),
            '@@ARK_SETUP_URL@@' => $setupUrl,
            '@@ARK_ROOT_PEM@@' => $pem,
        ]);

        return $platform === 'windows' ? str_replace(["\r\n", "\n"], ["\n", "\r\n"], $script) : $script;
    }

    private static function hostLabel(string $host): string
    {
        return (string) preg_replace('/[^a-z0-9.-]+/', '-', strtolower($host));
    }
}
