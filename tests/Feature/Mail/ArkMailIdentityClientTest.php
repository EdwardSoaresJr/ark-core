<?php

use App\Ark\Install\InstallationIdentity;
use App\Ark\Mail\ArkMailIdentityClient;
use App\Ark\Operations\Settings\ShopSettings;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

beforeEach(function () {
    InstallationIdentity::write((string) Str::uuid());
});

it('pushes shop reply-to identity to cloud when connected', function () {
    ShopSettings::current()->persistTrusted([
        'shop_name' => 'Casey Auto',
        'email' => 'shop@casey.example.test',
        'postmark_reply_to' => 'service@casey.example.test',
        'postmark_reply_to_name' => 'Casey Service',
        'cloud_status' => 'connected',
        'cloud_base_url' => 'https://cloud.example.test',
        'cloud_credential' => 'cloud-install-secret',
    ]);

    Http::fake([
        'cloud.example.test/api/v1/services/mail/identity' => Http::response(['ok' => true], 200),
    ]);

    expect(app(ArkMailIdentityClient::class)->syncShopReplyTo())->toBeTrue();

    Http::assertSent(function ($request) {
        $data = $request->data();

        return $request->method() === 'PUT'
            && str_ends_with($request->url(), '/api/v1/services/mail/identity')
            && ($data['reply_to_email'] ?? null) === 'service@casey.example.test'
            && ($data['reply_to_name'] ?? null) === 'Casey Service'
            && ($data['shop_display_name'] ?? null) === 'Casey Auto';
    });
});

it('falls back to shop email when reply-to is empty', function () {
    ShopSettings::current()->persistTrusted([
        'shop_name' => 'Casey Auto',
        'email' => 'shop@casey.example.test',
        'postmark_reply_to' => null,
        'cloud_status' => 'connected',
        'cloud_base_url' => 'https://cloud.example.test',
        'cloud_credential' => 'cloud-install-secret',
    ]);

    Http::fake([
        'cloud.example.test/api/v1/services/mail/identity' => Http::response(['ok' => true], 200),
    ]);

    expect(app(ArkMailIdentityClient::class)->syncShopReplyTo())->toBeTrue();

    Http::assertSent(fn ($request) => ($request->data()['reply_to_email'] ?? null) === 'shop@casey.example.test');
});

it('logs failure without throwing when cloud rejects identity sync', function () {
    ShopSettings::current()->persistTrusted([
        'email' => 'shop@casey.example.test',
        'cloud_status' => 'connected',
        'cloud_base_url' => 'https://cloud.example.test',
        'cloud_credential' => 'cloud-install-secret',
    ]);

    Http::fake([
        'cloud.example.test/api/v1/services/mail/identity' => Http::response([
            'ok' => false,
            'reason_code' => 'mail_not_enabled',
        ], 422),
    ]);

    expect(app(ArkMailIdentityClient::class)->syncShopReplyTo())->toBeFalse();
});
