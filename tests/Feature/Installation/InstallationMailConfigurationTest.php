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

it('does not reactivate environment smtp when mode is disabled', function () {
    ShopSettings::current()->persistTrusted([
        'installation_mail_mode' => InstallationMailConfiguration::MODE_DISABLED,
        'installation_mail_enabled' => false,
        'installation_mail_mailer' => 'smtp',
        'installation_mail_host' => '127.0.0.1',
        'installation_mail_port' => 587,
        'installation_mail_from_address' => 'shop@example.test',
    ]);

    config([
        'mail.default' => 'smtp',
        'mail.from.address' => 'env@example.test',
        'mail.mailers.smtp.host' => '127.0.0.1',
        'mail.mailers.smtp.port' => 587,
    ]);

    $config = app(InstallationMailConfiguration::class);

    expect($config->mode())->toBe(InstallationMailConfiguration::MODE_DISABLED)
        ->and($config->modeLabel())->toBe('Disabled')
        ->and($config->allowsCustomerDelivery())->toBeFalse()
        ->and($config->activeResolved())->toBeNull();
});

it('does not fall back to environment when installation settings are invalid', function () {
    ShopSettings::current()->persistTrusted([
        'installation_mail_mode' => InstallationMailConfiguration::MODE_SHOP,
        'installation_mail_enabled' => true,
        'installation_mail_mailer' => 'smtp',
        'installation_mail_host' => '',
        'installation_mail_port' => null,
        'installation_mail_from_address' => null,
    ]);

    config([
        'mail.default' => 'smtp',
        'mail.from.address' => 'env@example.test',
        'mail.mailers.smtp.host' => '127.0.0.1',
        'mail.mailers.smtp.port' => 587,
    ]);

    expect(app(InstallationMailConfiguration::class)->allowsCustomerDelivery())->toBeFalse()
        ->and(app(InstallationMailConfiguration::class)->activeSource())->toBe(InstallationMailConfiguration::SOURCE_NONE);
});

it('uses only installation settings when mode is shop_settings', function () {
    ShopSettings::current()->persistTrusted([
        'installation_mail_mode' => InstallationMailConfiguration::MODE_SHOP,
        'installation_mail_enabled' => true,
        'installation_mail_mailer' => 'smtp',
        'installation_mail_host' => '127.0.0.1',
        'installation_mail_port' => 587,
        'installation_mail_encryption' => 'tls',
        'installation_mail_from_address' => 'shop@example.test',
        'installation_mail_from_name' => 'Demo Shop',
    ]);

    config([
        'mail.default' => 'smtp',
        'mail.from.address' => 'env@example.test',
        'mail.mailers.smtp.host' => '10.0.0.1',
        'mail.mailers.smtp.port' => 587,
    ]);

    $resolved = app(InstallationMailConfiguration::class)->activeResolved();

    expect($resolved)->not->toBeNull()
        ->and($resolved['source'])->toBe(InstallationMailConfiguration::SOURCE_SHOP)
        ->and($resolved['from_address'])->toBe('shop@example.test')
        ->and($resolved['host'])->toBe('127.0.0.1');
});

it('uses only environment when mode is environment', function () {
    ShopSettings::current()->persistTrusted([
        'installation_mail_mode' => InstallationMailConfiguration::MODE_ENVIRONMENT,
        'installation_mail_enabled' => false,
        'installation_mail_host' => '127.0.0.1',
        'installation_mail_from_address' => 'shop@example.test',
    ]);

    config([
        'mail.default' => 'smtp',
        'mail.from.address' => 'env@example.test',
        'mail.from.name' => 'Env Shop',
        'mail.mailers.smtp.host' => '127.0.0.1',
        'mail.mailers.smtp.port' => 587,
        'mail.mailers.smtp.encryption' => 'tls',
    ]);

    $config = app(InstallationMailConfiguration::class);

    expect($config->modeLabel())->toBe('Environment Managed')
        ->and($config->activeSource())->toBe(InstallationMailConfiguration::SOURCE_ENVIRONMENT)
        ->and($config->allowsCustomerDelivery())->toBeTrue();
});

it('rejects log and array mailers as customer delivery', function () {
    ShopSettings::current()->persistTrusted([
        'installation_mail_mode' => InstallationMailConfiguration::MODE_ENVIRONMENT,
    ]);

    config([
        'mail.default' => 'log',
        'mail.from.address' => 'shop@example.test',
    ]);

    expect(app(InstallationMailConfiguration::class)->allowsCustomerDelivery())->toBeFalse();
});

it('never allows installation delivery when the hosted tenants catalog is configured', function () {
    ShopSettings::current()->persistTrusted([
        'installation_mail_mode' => InstallationMailConfiguration::MODE_SHOP,
        'installation_mail_enabled' => true,
        'installation_mail_mailer' => 'smtp',
        'installation_mail_host' => '127.0.0.1',
        'installation_mail_port' => 587,
        'installation_mail_from_address' => 'shop@example.test',
    ]);

    $catalog = tempnam(sys_get_temp_dir(), 'ark-hosted-tenants-');
    file_put_contents($catalog, '{}');
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
        ->and(fn () => $guard->assertSafe('127.0.0.1', 22))
        ->toThrow(InvalidArgumentException::class);
});
