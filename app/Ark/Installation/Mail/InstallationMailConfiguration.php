<?php

namespace App\Ark\Installation\Mail;

use App\Ark\Operations\Settings\ShopSettings;
use App\Ark\Runtime\HostedRuntime;
use Illuminate\Support\Facades\Schema;

/**
 * Resolves standalone customer mail settings.
 *
 * Modes (explicit, no silent crossover):
 * - disabled: no installation customer delivery (env SMTP must not reactivate)
 * - shop_settings: only the saved shop SMTP/sendmail (invalid → not configured)
 * - environment: only Laravel MAIL_* (smtp/sendmail)
 *
 * Hosted SaaS never exposes or uses installation SMTP for customer delivery.
 */
final class InstallationMailConfiguration
{
    public const MODE_DISABLED = 'disabled';

    public const MODE_SHOP = 'shop_settings';

    public const MODE_ENVIRONMENT = 'environment';

    public const SOURCE_SHOP = 'shop_settings';

    public const SOURCE_ENVIRONMENT = 'environment';

    public const SOURCE_NONE = 'none';

    public function __construct(
        private readonly InstallationMailHostGuard $hostGuard = new InstallationMailHostGuard,
    ) {}

    public function hostedIsolated(): bool
    {
        return HostedRuntime::isHostedProduct();
    }

    public function allowsCustomerDelivery(): bool
    {
        if ($this->hostedIsolated()) {
            return false;
        }

        return $this->activeResolved() !== null;
    }

    public function mode(): string
    {
        if ($this->hostedIsolated() || ! $this->hasShopColumns()) {
            return self::MODE_DISABLED;
        }

        $settings = ShopSettings::current();
        $mode = strtolower(trim((string) ($settings->installation_mail_mode ?? '')));

        if (in_array($mode, [self::MODE_DISABLED, self::MODE_SHOP, self::MODE_ENVIRONMENT], true)) {
            return $mode;
        }

        // Legacy rows: enabled flag without mode column value.
        if ((bool) ($settings->installation_mail_enabled ?? false)) {
            return self::MODE_SHOP;
        }

        return self::MODE_DISABLED;
    }

    /**
     * @return array{
     *     source: string,
     *     mailer: string,
     *     host: ?string,
     *     port: ?int,
     *     encryption: ?string,
     *     username: ?string,
     *     password: ?string,
     *     from_address: string,
     *     from_name: ?string,
     *     reply_to: ?string,
     *     password_configured: bool,
     * }|null
     */
    public function activeResolved(): ?array
    {
        if ($this->hostedIsolated()) {
            return null;
        }

        return match ($this->mode()) {
            self::MODE_SHOP => $this->shopResolved(),
            self::MODE_ENVIRONMENT => $this->environmentResolved(),
            default => null,
        };
    }

    public function activeSource(): string
    {
        $resolved = $this->activeResolved();

        return $resolved['source'] ?? self::SOURCE_NONE;
    }

    public function modeLabel(): string
    {
        return match ($this->mode()) {
            self::MODE_SHOP => 'Installation Settings',
            self::MODE_ENVIRONMENT => 'Environment Managed',
            default => 'Disabled',
        };
    }

    /**
     * Fingerprint of connection settings used to invalidate prior successful tests.
     */
    public function connectionFingerprint(?array $config = null): string
    {
        $config ??= $this->activeResolved() ?? [];

        return hash('sha256', implode('|', [
            (string) ($config['mailer'] ?? ''),
            (string) ($config['host'] ?? ''),
            (string) ($config['port'] ?? ''),
            (string) ($config['encryption'] ?? ''),
            (string) ($config['username'] ?? ''),
            (string) ($config['from_address'] ?? ''),
            (string) ($config['reply_to'] ?? ''),
            ! empty($config['password_configured']) || filled($config['password'] ?? null) ? '1' : '0',
        ]));
    }

    /**
     * @return array{
     *     source: string,
     *     mailer: string,
     *     host: ?string,
     *     port: ?int,
     *     encryption: ?string,
     *     username: ?string,
     *     password: ?string,
     *     from_address: string,
     *     from_name: ?string,
     *     reply_to: ?string,
     *     password_configured: bool,
     * }|null
     */
    public function shopResolved(): ?array
    {
        if ($this->hostedIsolated() || ! $this->hasShopColumns()) {
            return null;
        }

        $settings = ShopSettings::current();
        $mailer = strtolower(trim((string) ($settings->installation_mail_mailer ?: 'smtp')));
        if (! in_array($mailer, ['smtp', 'sendmail'], true)) {
            return null;
        }

        $from = strtolower(trim((string) ($settings->installation_mail_from_address ?: '')));
        if ($from === '' || ! filter_var($from, FILTER_VALIDATE_EMAIL)) {
            return null;
        }

        if ($mailer === 'smtp') {
            $host = trim((string) ($settings->installation_mail_host ?: ''));
            $port = (int) ($settings->installation_mail_port ?: 0);
            if ($host === '' || $port < 1) {
                return null;
            }

            try {
                $this->hostGuard->assertSafe($host, $port);
            } catch (\InvalidArgumentException) {
                return null;
            }
        }

        $password = $settings->installation_mail_password;
        $password = is_string($password) && $password !== '' ? $password : null;

        return [
            'source' => self::SOURCE_SHOP,
            'mailer' => $mailer,
            'host' => $mailer === 'smtp' ? trim((string) $settings->installation_mail_host) : null,
            'port' => $mailer === 'smtp' ? (int) $settings->installation_mail_port : null,
            'encryption' => $this->normalizeEncryption($settings->installation_mail_encryption),
            'username' => filled($settings->installation_mail_username) ? (string) $settings->installation_mail_username : null,
            'password' => $password,
            'from_address' => $from,
            'from_name' => filled($settings->installation_mail_from_name)
                ? (string) $settings->installation_mail_from_name
                : (filled($settings->shop_name) ? (string) $settings->shop_name : null),
            'reply_to' => filled($settings->installation_mail_reply_to)
                ? (string) $settings->installation_mail_reply_to
                : ($settings->customerReplyToAddress() ?: null),
            'password_configured' => $password !== null,
        ];
    }

    /**
     * @return array{
     *     source: string,
     *     mailer: string,
     *     host: ?string,
     *     port: ?int,
     *     encryption: ?string,
     *     username: ?string,
     *     password: ?string,
     *     from_address: string,
     *     from_name: ?string,
     *     reply_to: ?string,
     *     password_configured: bool,
     * }|null
     */
    public function environmentResolved(): ?array
    {
        if ($this->hostedIsolated()) {
            return null;
        }

        $mailer = strtolower(trim((string) config('mail.default', 'smtp')));
        if (! in_array($mailer, ['smtp', 'sendmail'], true)) {
            return null;
        }

        $from = strtolower(trim((string) config('mail.from.address', '')));
        if ($from === '' || ! filter_var($from, FILTER_VALIDATE_EMAIL)) {
            return null;
        }

        if ($mailer === 'smtp') {
            $host = trim((string) config('mail.mailers.smtp.host', ''));
            $port = (int) config('mail.mailers.smtp.port', 0);
            if ($host === '' || $port < 1) {
                return null;
            }

            try {
                $this->hostGuard->assertSafe($host, $port);
            } catch (\InvalidArgumentException) {
                return null;
            }
        }

        $password = config('mail.mailers.smtp.password');
        $password = is_string($password) && $password !== '' ? $password : null;

        return [
            'source' => self::SOURCE_ENVIRONMENT,
            'mailer' => $mailer,
            'host' => $mailer === 'smtp' ? trim((string) config('mail.mailers.smtp.host')) : null,
            'port' => $mailer === 'smtp' ? (int) config('mail.mailers.smtp.port') : null,
            'encryption' => $this->normalizeEncryption(config('mail.mailers.smtp.encryption')),
            'username' => filled(config('mail.mailers.smtp.username')) ? (string) config('mail.mailers.smtp.username') : null,
            'password' => $password,
            'from_address' => $from,
            'from_name' => filled(config('mail.from.name')) ? (string) config('mail.from.name') : null,
            'reply_to' => filled(config('mail.reply_to.address')) ? (string) config('mail.reply_to.address') : null,
            'password_configured' => $password !== null,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function formDefaults(): array
    {
        if (! $this->hasShopColumns()) {
            return $this->emptyFormDefaults();
        }

        $settings = ShopSettings::current();
        $envAvailable = $this->environmentResolved() !== null;
        $mode = $this->mode();

        return [
            'mode' => $mode,
            'mode_label' => $this->modeLabel(),
            'enabled' => $mode === self::MODE_SHOP,
            'mailer' => (string) ($settings->installation_mail_mailer ?: 'smtp'),
            'host' => (string) ($settings->installation_mail_host ?: ''),
            'port' => (int) ($settings->installation_mail_port ?: 587),
            'encryption' => (string) ($settings->installation_mail_encryption ?: 'tls'),
            'username' => (string) ($settings->installation_mail_username ?: ''),
            'password_configured' => filled($settings->installation_mail_password),
            'from_address' => (string) ($settings->installation_mail_from_address ?: ($settings->email ?: '')),
            'from_name' => (string) ($settings->installation_mail_from_name ?: ($settings->shop_name ?: '')),
            'reply_to' => (string) ($settings->installation_mail_reply_to ?: ($settings->customerReplyToAddress() ?: '')),
            'last_tested_at' => $settings->installation_mail_last_tested_at,
            'last_test_status' => $settings->installation_mail_last_test_status,
            'last_error' => $settings->installation_mail_last_error,
            'active_source' => $this->activeSource(),
            'environment_available' => $envAvailable,
            'connection_fingerprint' => $this->connectionFingerprint(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function emptyFormDefaults(): array
    {
        return [
            'mode' => self::MODE_DISABLED,
            'mode_label' => 'Disabled',
            'enabled' => false,
            'mailer' => 'smtp',
            'host' => '',
            'port' => 587,
            'encryption' => 'tls',
            'username' => '',
            'password_configured' => false,
            'from_address' => '',
            'from_name' => '',
            'reply_to' => '',
            'last_tested_at' => null,
            'last_test_status' => null,
            'last_error' => null,
            'active_source' => self::SOURCE_NONE,
            'environment_available' => false,
            'connection_fingerprint' => '',
        ];
    }

    private function normalizeEncryption(mixed $value): ?string
    {
        $encryption = strtolower(trim((string) ($value ?? '')));

        return match ($encryption) {
            'tls', 'ssl' => $encryption,
            'null', 'none', '' => null,
            default => null,
        };
    }

    private function hasShopColumns(): bool
    {
        try {
            return Schema::hasColumn('shop_settings', 'installation_mail_enabled');
        } catch (\Throwable) {
            return false;
        }
    }
}
