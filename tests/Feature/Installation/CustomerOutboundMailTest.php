<?php

use App\Ark\Installation\Mail\CustomerOutboundMail;
use App\Ark\Mail\OutboundTransactionalMail;
use App\Ark\Mail\TransactionalMailResult;
use App\Ark\Operations\Settings\ShopSettings;
use Illuminate\Support\Facades\Http;

it('marks outbound ready when installation smtp is configured', function () {
    ShopSettings::current()->persistTrusted([
        'installation_mail_enabled' => true,
        'installation_mail_mailer' => 'smtp',
        'installation_mail_host' => 'smtp.example.test',
        'installation_mail_port' => 587,
        'installation_mail_from_address' => 'shop@example.test',
        'cloud_status' => 'disconnected',
        'ark_mail_status' => null,
        'ark_mail_from_email' => null,
    ]);
    config(['mail.default' => 'array']);

    $outbound = app(OutboundTransactionalMail::class);

    expect($outbound->providerMode())->toBe('installation')
        ->and($outbound->isReady())->toBeTrue()
        ->and(app(CustomerOutboundMail::class)->canSend())->toBeTrue()
        ->and($outbound->statusLabel())->toContain('Installation SMTP');
});

it('prefers ark mail over installation smtp when platform mail is configured', function () {
    $this->app['env'] = 'production';

    ShopSettings::current()->persistTrusted([
        'cloud_status' => 'connected',
        'cloud_base_url' => 'https://cloud.example.test',
        'cloud_credential' => 'cloud-install-secret',
        'ark_mail_status' => 'connected',
        'ark_mail_credential' => 'cloud-install-secret',
        'ark_mail_from_email' => 'shop@mail.example.test',
        'installation_mail_enabled' => true,
        'installation_mail_mailer' => 'smtp',
        'installation_mail_host' => 'smtp.example.test',
        'installation_mail_port' => 587,
        'installation_mail_from_address' => 'local@example.test',
    ]);

    Http::fake([
        'cloud.example.test/*' => Http::response([
            'ok' => true,
            'correlation_id' => 'corr-platform',
            'message_id' => 'm-1',
        ], 200),
    ]);

    expect(app(OutboundTransactionalMail::class)->providerMode())->toBe('ark_mail');

    $result = app(CustomerOutboundMail::class)->send(
        operation: 'estimate.send',
        recipientEmail: 'customer@example.test',
        variables: ['shop_name' => 'Demo'],
    );

    expect($result->ok())->toBeTrue();
    Http::assertSentCount(1);
});

it('does not treat not-configured as success', function () {
    ShopSettings::current()->persistTrusted([
        'installation_mail_enabled' => false,
        'cloud_status' => 'disconnected',
        'ark_mail_from_email' => null,
    ]);
    config(['mail.default' => 'log']);

    $result = app(CustomerOutboundMail::class)->send(
        operation: 'estimate.send',
        recipientEmail: 'customer@example.test',
        variables: ['shop_name' => 'Demo'],
    );

    expect($result->status)->toBe(TransactionalMailResult::STATUS_NOT_CONFIGURED)
        ->and($result->ok())->toBeFalse();
});
