<?php

use App\Ark\Installation\Mail\InstallationMailConfiguration;
use App\Ark\Installation\Mail\InstallationMailHostGuard;
use App\Ark\Operations\Settings\ShopSettings;
use App\Ark\Runtime\HostedRuntime;

beforeEach(function () {
    config([
        'mail.default' => 'array',
        'mail.from.address' => null,
        'mail.mailers.smtp.host' => null,
    ]);
});

it('prefers enabled shop settings over environment MAIL_*', function () {
    ShopSettings::current()->persistTrusted([
        'installation_mail_enabled' => true,
        'installation_mail_mailer' => 'smtp',
        'installation_mail_host' => 'smtp.example.test',
        'installation_mail_port' => 587,
        'installation_mail_encryption' => 'tls',
        'installation_mail_from_address' => 'shop@example.test',
        'installation_mail_from_name' => 'Demo Shop',
    ]);

    config([
        'mail.default' => 'smtp',
        'mail.from.address' => 'env@example.test',
        'mail.mailers.smtp.host' => 'env-smtp.example.test',
        'mail.mailers.smtp.port' => 587,
    ]);

    $resolved = app(InstallationMailConfiguration::class)->activeResolved();

    expect($resolved)->not->toBeNull()
        ->and($resolved['source'])->toBe(InstallationMailConfiguration::SOURCE_SHOP)
        ->and($resolved['from_address'])->toBe('shop@example.test')
        ->and($resolved['host'])->toBe('smtp.example.test');
});

it('falls back to environment smtp when shop settings are disabled', function () {
    ShopSettings::current()->persistTrusted([
        'installation_mail_enabled' => false,
    ]);

    config([
        'mail.default' => 'smtp',
        'mail.from.address' => 'env@example.test',
        'mail.from.name' => 'Env Shop',
        'mail.mailers.smtp.host' => 'smtp.example.test',
        'mail.mailers.smtp.port' => 587,
        'mail.mailers.smtp.encryption' => 'tls',
    ]);

    $config = app(InstallationMailConfiguration::class);

    expect($config->activeSource())->toBe(InstallationMailConfiguration::SOURCE_ENVIRONMENT)
        ->and($config->allowsCustomerDelivery())->toBeTrue();
});

it('rejects log and array mailers as customer delivery', function () {
    config([
        'mail.default' => 'log',
        'mail.from.address' => 'shop@example.test',
    ]);

    expect(app(InstallationMailConfiguration::class)->allowsCustomerDelivery())->toBeFalse();
});

it('never allows installation delivery when the hosted tenants catalog is configured', function () {
    ShopSettings::current()->persistTrusted([
        'installation_mail_enabled' => true,
        'installation_mail_mailer' => 'smtp',
        'installation_mail_host' => 'smtp.example.test',
        'installation_mail_port' => 587,
        'installation_mail_from_address' => 'shop@example.test',
    ]);

    $catalog = tempnam(sys_get_temp_dir(), 'ark-hosted-tenants-');
    file_put_contents($catalog, "{}");
    $previous = getenv('ARK_HOSTED_TENANTS');
    putenv('ARK_HOSTED_TENANTS='.$catalog);
    $_ENV['ARK_HOSTED_TENANTS'] = $catalog;

    try {
        expect(HostedRuntime::catalogConfigured())->toBeTrue()
            ->and(app(InstallationMailConfiguration::class)->allowsCustomerDelivery())->toBeFalse()
            ->and(app(InstallationMailConfiguration::class)->activeResolved())->toBeNull();
    } finally {
        if ($previous === false) {
            putenv('ARK_HOSTED_TENANTS');
            unset($_ENV['ARK_HOSTED_TENANTS']);
        } else {
            putenv('ARK_HOSTED_TENANTS='.$previous);
            $_ENV['ARK_HOSTED_TENANTS'] = $previous;
        }
        @unlink($catalog);
    }
});

it('blocks cloud metadata SMTP hosts', function () {
    $guard = new InstallationMailHostGuard;

    expect(fn () => $guard->assertSafe('169.254.169.254', 587))
        ->toThrow(InvalidArgumentException::class)
        ->and(fn () => $guard->assertSafe('metadata.google.internal', 587))
        ->toThrow(InvalidArgumentException::class)
        ->and(fn () => $guard->assertSafe('smtp.example.test', 22))
        ->toThrow(InvalidArgumentException::class);
});
