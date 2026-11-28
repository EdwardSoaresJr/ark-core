<?php

use App\Ark\Installation\Mail\InstallationMailConfiguration;
use App\Ark\Operations\Settings\ShopSettings;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schema;

it('has installation mail columns after migrate on a fresh schema path', function () {
    expect(Schema::hasColumn('shop_settings', 'installation_mail_enabled'))->toBeTrue()
        ->and(Schema::hasColumn('shop_settings', 'installation_mail_mode'))->toBeTrue()
        ->and(Schema::hasColumn('shop_settings', 'installation_mail_password'))->toBeTrue()
        ->and(Schema::hasTable('installation_mail_audit_events'))->toBeTrue();
});

it('defaults fresh shops to disabled and does not inherit env smtp until Environment Managed is chosen', function () {
    config([
        'mail.default' => 'smtp',
        'mail.from.address' => 'env@example.test',
        'mail.mailers.smtp.host' => '127.0.0.1',
        'mail.mailers.smtp.port' => 587,
    ]);

    $settings = ShopSettings::current();
    // Fresh row after RefreshDatabase: mode defaults to disabled.
    if (Schema::hasColumn('shop_settings', 'installation_mail_mode')) {
        $settings->persistTrusted([
            'installation_mail_mode' => InstallationMailConfiguration::MODE_DISABLED,
            'installation_mail_enabled' => false,
        ]);
    }

    $config = app(InstallationMailConfiguration::class);

    expect($config->mode())->toBe(InstallationMailConfiguration::MODE_DISABLED)
        ->and($config->allowsCustomerDelivery())->toBeFalse();

    $settings->persistTrusted([
        'installation_mail_mode' => InstallationMailConfiguration::MODE_ENVIRONMENT,
        'installation_mail_enabled' => false,
    ]);

    expect($config->mode())->toBe(InstallationMailConfiguration::MODE_ENVIRONMENT)
        ->and($config->allowsCustomerDelivery())->toBeTrue()
        ->and($config->activeSource())->toBe(InstallationMailConfiguration::SOURCE_ENVIRONMENT);
});

it('upgrades legacy enabled flag into shop_settings mode without env fallback while disabled', function () {
    // Simulate pre-mode rows that only had the boolean.
    ShopSettings::current()->persistTrusted([
        'installation_mail_mode' => InstallationMailConfiguration::MODE_DISABLED,
        'installation_mail_enabled' => false,
        'installation_mail_mailer' => 'smtp',
        'installation_mail_host' => '127.0.0.1',
        'installation_mail_port' => 587,
        'installation_mail_from_address' => 'legacy@example.test',
    ]);

    config([
        'mail.default' => 'smtp',
        'mail.from.address' => 'env@example.test',
        'mail.mailers.smtp.host' => '127.0.0.1',
        'mail.mailers.smtp.port' => 587,
    ]);

    expect(app(InstallationMailConfiguration::class)->allowsCustomerDelivery())->toBeFalse();

    ShopSettings::current()->persistTrusted([
        'installation_mail_mode' => InstallationMailConfiguration::MODE_SHOP,
        'installation_mail_enabled' => true,
    ]);

    expect(app(InstallationMailConfiguration::class)->activeSource())
        ->toBe(InstallationMailConfiguration::SOURCE_SHOP);
});

it('keeps migrations idempotent when re-run', function () {
    $code = Artisan::call('migrate', ['--force' => true]);

    expect($code)->toBe(0)
        ->and(Schema::hasColumn('shop_settings', 'installation_mail_mode'))->toBeTrue();
});
