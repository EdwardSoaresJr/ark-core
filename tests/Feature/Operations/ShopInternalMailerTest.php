<?php

use App\Ark\Operations\Settings\ShopIntegrationRuntimeConfig;
use App\Ark\Operations\Settings\ShopSettings;

test('broken postmark mailer falls back to log when no core token exists', function () {
    config()->set('mail.default', 'postmark');
    config()->set('services.postmark.token', null);

    ShopSettings::current()->persistTrusted([
        'postmark_token' => null,
    ]);

    ShopIntegrationRuntimeConfig::apply();

    expect(config('mail.default'))->toBe('log');
});

test('disconnected shop keeps postmark when a core token exists', function () {
    config()->set('mail.default', 'postmark');
    config()->set('services.postmark.token', null);

    ShopSettings::current()->persistTrusted([
        'platform_status' => null,
        'platform_credential' => null,
        'postmark_token' => 'shop-postmark-token',
    ]);

    ShopIntegrationRuntimeConfig::apply();

    expect(config('mail.default'))->toBe('postmark')
        ->and(config('services.postmark.token'))->toBe('shop-postmark-token');
});

test('connected shop does not apply leftover postmark tokens to laravel mail', function () {
    config()->set('mail.default', 'postmark');

    enableHostedPlatformMail();
    ShopIntegrationRuntimeConfig::apply();

    expect(config('mail.default'))->toBe('log')
        ->and(config('services.postmark.token'))->toBeNull()
        ->and(ShopSettings::current()->fresh()->postmark_token)->toBe('leftover-shop-postmark');
});

test('configured smtp and log mailers are left alone', function () {
    enableHostedPlatformMail();

    config()->set('mail.default', 'smtp');
    ShopIntegrationRuntimeConfig::apply();
    expect(config('mail.default'))->toBe('smtp');

    config()->set('mail.default', 'log');
    ShopIntegrationRuntimeConfig::apply();
    expect(config('mail.default'))->toBe('log');

    config()->set('mail.default', 'array');
    ShopIntegrationRuntimeConfig::apply();
    expect(config('mail.default'))->toBe('array');
});
