<?php

use App\Ark\Mail\ArkMailClient;
use App\Ark\Mail\OutboundTransactionalMail;
use App\Ark\Mail\TransactionalMailEnvelope;
use App\Ark\Mail\TransactionalMailOperation;
use App\Ark\Mail\TransactionalMailResult;
use App\Ark\Operations\Settings\ShopSettings;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

beforeEach(function () {
    config(['mail.default' => 'array']);
    Mail::fake();
});

function independenceMailable(): \Illuminate\Mail\Mailable
{
    return new class extends \Illuminate\Mail\Mailable
    {
        public function content(): \Illuminate\Mail\Mailables\Content
        {
            return new \Illuminate\Mail\Mailables\Content(htmlString: '<p>independence</p>');
        }

        public function envelope(): \Illuminate\Mail\Mailables\Envelope
        {
            return new \Illuminate\Mail\Mailables\Envelope(subject: 'Independence');
        }
    };
}

it('ARK SELF-HOSTED EMAIL INDEPENDENCE: production BYO Postmark works without Cloud', function () {
    $this->app['env'] = 'production';

    ShopSettings::current()->persistTrusted([
        'email_provider' => 'postmark',
        'postmark_token' => 'pm-shop-owned-token-for-tests',
        'postmark_message_stream_id' => 'outbound',
        'cloud_status' => null,
        'cloud_credential' => null,
        'cloud_base_url' => null,
        'ark_mail_status' => null,
        'ark_mail_credential' => null,
    ]);

    Http::fake(); // Cloud must not be contacted

    $outbound = app(OutboundTransactionalMail::class);

    expect($outbound->selectedProvider())->toBe('postmark')
        ->and($outbound->providerMode())->toBe('byo_postmark')
        ->and($outbound->isReady())->toBeTrue();

    $result = $outbound->sendMailable(
        TransactionalMailOperation::EstimateSend,
        'customer@example.test',
        independenceMailable(),
        'idem-'.Str::uuid(),
    );

    expect($result->ok())->toBeTrue();
    Mail::assertOutgoingCount(1);
    Http::assertNothingSent();
});

it('uses ARK Mail when selected and Cloud is connected', function () {
    $this->app['env'] = 'production';

    ShopSettings::current()->persistTrusted([
        'email_provider' => 'ark_mail',
        'postmark_token' => 'pm-should-not-be-used',
        'cloud_status' => 'connected',
        'cloud_base_url' => 'https://cloud.example.test',
        'cloud_credential' => 'cloud-install-secret',
        'ark_mail_status' => 'connected',
        'ark_mail_credential' => 'cloud-install-secret',
    ]);

    Http::fake([
        'cloud.example.test/*' => Http::response([
            'ok' => true,
            'correlation_id' => 'corr-1',
            'message_id' => 'm-1',
            'provider_message_id' => 'pm-1',
        ], 200),
    ]);

    $outbound = app(OutboundTransactionalMail::class);

    expect($outbound->providerMode())->toBe('ark_mail');

    $result = $outbound->sendMailable(
        TransactionalMailOperation::EstimateSend,
        'customer@example.test',
        independenceMailable(),
        'idem-'.Str::uuid(),
    );

    expect($result->ok())->toBeTrue();
    Mail::assertNothingOutgoing();
    Http::assertSentCount(1);
});

it('uses BYO Postmark even when Cloud is available', function () {
    $this->app['env'] = 'production';

    ShopSettings::current()->persistTrusted([
        'email_provider' => 'postmark',
        'postmark_token' => 'pm-shop-owned-token-for-tests',
        'cloud_status' => 'connected',
        'cloud_base_url' => 'https://cloud.example.test',
        'cloud_credential' => 'cloud-install-secret',
        'ark_mail_status' => 'connected',
        'ark_mail_credential' => 'cloud-install-secret',
    ]);

    Http::fake();

    $outbound = app(OutboundTransactionalMail::class);

    expect($outbound->providerMode())->toBe('byo_postmark');

    $result = $outbound->sendMailable(
        TransactionalMailOperation::InvoiceSend,
        'customer@example.test',
        independenceMailable(),
        'idem-'.Str::uuid(),
    );

    expect($result->ok())->toBeTrue();
    Mail::assertOutgoingCount(1);
    Http::assertNothingSent();
});

it('returns not configured when neither provider is selected', function () {
    $this->app['env'] = 'production';

    ShopSettings::current()->persistTrusted([
        'email_provider' => null,
        'postmark_token' => null,
        'cloud_status' => null,
        'cloud_credential' => null,
        'ark_mail_credential' => null,
    ]);

    $outbound = app(OutboundTransactionalMail::class);

    expect($outbound->providerMode())->toBe('none')
        ->and($outbound->isReady())->toBeFalse();

    $result = $outbound->sendMailable(
        TransactionalMailOperation::DocumentSend,
        'customer@example.test',
        independenceMailable(),
        'idem-'.Str::uuid(),
    );

    expect($result->status)->toBe(TransactionalMailResult::STATUS_NOT_CONFIGURED)
        ->and($result->ok())->toBeFalse();
    Mail::assertNothingOutgoing();
});

it('does not fall back to a second provider when the selected provider fails', function () {
    $this->app['env'] = 'production';

    ShopSettings::current()->persistTrusted([
        'email_provider' => 'ark_mail',
        'postmark_token' => 'pm-shop-owned-token-for-tests',
        'cloud_status' => 'connected',
        'cloud_base_url' => 'https://cloud.example.test',
        'cloud_credential' => 'cloud-install-secret',
        'ark_mail_credential' => 'cloud-install-secret',
        'ark_mail_status' => 'connected',
    ]);

    Http::fake([
        'cloud.example.test/*' => Http::response([
            'ok' => false,
            'reason_code' => 'global_sending_disabled',
            'message' => 'Sending is disabled.',
        ], 403),
    ]);

    $result = app(OutboundTransactionalMail::class)->sendMailable(
        TransactionalMailOperation::EstimateSend,
        'customer@example.test',
        independenceMailable(),
        'idem-'.Str::uuid(),
    );

    expect($result->ok())->toBeFalse()
        ->and($result->reasonCode)->toBe('global_sending_disabled');
    Mail::assertNothingOutgoing();
    Http::assertSentCount(1);
});

it('does not expose shop Postmark token on Cloud Mail requests', function () {
    Http::fake([
        'cloud.example.test/*' => Http::response([
            'ok' => true,
            'correlation_id' => 'c-1',
            'message_id' => 'm-1',
            'provider_message_id' => 'p-1',
        ], 200),
    ]);

    ShopSettings::current()->persistTrusted([
        'email_provider' => 'ark_mail',
        'postmark_token' => 'pm-must-never-leave-the-box',
        'cloud_status' => 'connected',
        'cloud_base_url' => 'https://cloud.example.test',
        'cloud_credential' => 'cloud-install-secret',
        'ark_mail_status' => 'connected',
        'ark_mail_credential' => 'cloud-install-secret',
    ]);

    $client = app(ArkMailClient::class);
    $envelope = new TransactionalMailEnvelope(
        operation: TransactionalMailOperation::EstimateSend,
        recipientEmail: 'customer@example.test',
        subject: 'Estimate',
        htmlBody: '<p>hi</p>',
        textBody: 'hi',
        idempotencyKey: 'idem-'.Str::uuid(),
        domainObjectType: 'repair_order',
        domainObjectId: '1',
        attachments: [],
        correlationId: (string) Str::uuid(),
    );

    expect($client->send($envelope)->ok())->toBeTrue();

    Http::assertSent(function ($request) {
        $body = $request->body();

        return ! str_contains($body, 'pm-must-never-leave-the-box')
            && ! str_contains(json_encode($request->headers()), 'pm-must-never-leave-the-box');
    });
});
