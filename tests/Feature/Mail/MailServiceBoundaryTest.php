<?php

use App\Ark\Mail\ArkMailClient;
use App\Ark\Mail\OutboundTransactionalMail;
use App\Ark\Mail\TransactionalMailEnvelope;
use App\Ark\Mail\TransactionalMailOperation;
use App\Ark\Mail\TransactionalMailResult;
use App\Ark\Operations\Documents\EstimateDocumentEmailDelivery;
use App\Ark\Operations\Documents\InvoiceDocumentEmailDelivery;
use App\Ark\Operations\Settings\ShopSettings;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

beforeEach(function () {
    config(['mail.default' => 'array']);
    Mail::fake();
});

function boundaryMailable(): \Illuminate\Mail\Mailable
{
    return new class extends \Illuminate\Mail\Mailable
    {
        public function content(): \Illuminate\Mail\Mailables\Content
        {
            return new \Illuminate\Mail\Mailables\Content(htmlString: '<p>boundary</p>');
        }

        public function envelope(): \Illuminate\Mail\Mailables\Envelope
        {
            return new \Illuminate\Mail\Mailables\Envelope(subject: 'Boundary');
        }
    };
}

it('keeps estimate and invoice delivery on the OutboundTransactionalMail boundary', function () {
    $estimate = new ReflectionClass(EstimateDocumentEmailDelivery::class);
    $invoice = new ReflectionClass(InvoiceDocumentEmailDelivery::class);
    $payment = new ReflectionClass(\App\Ark\Operations\Messaging\SendPaymentLinkEmailDelivery::class);
    $deposit = new ReflectionClass(\App\Ark\Operations\Messaging\SendDepositRequestEmailDelivery::class);
    $review = new ReflectionClass(\App\Ark\Operations\Messaging\SendReviewRequestEmailDelivery::class);

    expect($estimate->hasProperty('outboundMail'))->toBeTrue()
        ->and($invoice->hasProperty('outboundMail'))->toBeTrue()
        ->and($payment->hasProperty('outboundMail'))->toBeTrue()
        ->and($deposit->hasProperty('outboundMail'))->toBeTrue()
        ->and($review->hasProperty('outboundMail'))->toBeTrue()
        ->and($estimate->getProperty('outboundMail')->getType()?->getName())
        ->toBe(OutboundTransactionalMail::class)
        ->and($invoice->getProperty('outboundMail')->getType()?->getName())
        ->toBe(OutboundTransactionalMail::class)
        ->and($payment->getProperty('outboundMail')->getType()?->getName())
        ->toBe(OutboundTransactionalMail::class)
        ->and($deposit->getProperty('outboundMail')->getType()?->getName())
        ->toBe(OutboundTransactionalMail::class)
        ->and($review->getProperty('outboundMail')->getType()?->getName())
        ->toBe(OutboundTransactionalMail::class);
});

it('uses ArkMailClient when ARK Mail is configured', function () {
    $this->app['env'] = 'production';

    ShopSettings::current()->persistTrusted([
        'cloud_status' => 'connected',
        'cloud_base_url' => 'https://cloud.example.test',
        'cloud_credential' => 'cloud-install-secret',
        'ark_mail_status' => 'connected',
        'ark_mail_credential' => 'cloud-install-secret',
        'ark_mail_from_email' => 'shop@mail.example.test',
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

    expect($outbound->providerMode())->toBe('ark_mail')
        ->and($outbound->isReady())->toBeTrue();

    $result = $outbound->sendMailable(
        TransactionalMailOperation::EstimateSend,
        'customer@example.test',
        boundaryMailable(),
        'idem-'.Str::uuid(),
    );

    expect($result->ok())->toBeTrue();
    Mail::assertNothingSent();
    Http::assertSentCount(1);
});

it('returns an honest not-configured result when ARK Mail is disconnected', function () {
    $this->app['env'] = 'production';

    ShopSettings::current()->persistTrusted([
        'cloud_status' => null,
        'cloud_credential' => null,
        'cloud_base_url' => null,
        'ark_mail_status' => null,
        'ark_mail_credential' => null,
    ]);

    $outbound = app(OutboundTransactionalMail::class);

    expect($outbound->providerMode())->toBe('none')
        ->and($outbound->isReady())->toBeFalse()
        ->and($outbound->statusLabel())->toBe('Not configured');

    $result = $outbound->sendMailable(
        TransactionalMailOperation::DocumentSend,
        'customer@example.test',
        boundaryMailable(),
        'idem-'.Str::uuid(),
    );

    expect($result->status)->toBe(TransactionalMailResult::STATUS_NOT_CONFIGURED)
        ->and($result->ok())->toBeFalse()
        ->and($result->operatorMessage())->toContain("isn't configured");
    Mail::assertNothingSent();
});

it('does not treat MAIL_MAILER=postmark alone as a stock production provider on fresh installs', function () {
    $this->app['env'] = 'production';
    config(['mail.default' => 'postmark']);
    config(['services.postmark.token' => 'should-not-enable-mail']);

    ShopSettings::current()->persistTrusted([
        'cloud_status' => null,
        'cloud_credential' => null,
        'ark_mail_credential' => null,
        'ark_mail_status' => null,
    ]);

    expect(app(OutboundTransactionalMail::class)->providerMode())->toBe('none')
        ->and(app(OutboundTransactionalMail::class)->isReady())->toBeFalse();
});

it('does not pretend log/array succeeds in production', function () {
    $this->app['env'] = 'production';
    config(['mail.default' => 'log']);

    ShopSettings::current()->persistTrusted([
        'cloud_status' => null,
        'cloud_credential' => null,
        'ark_mail_credential' => null,
    ]);

    $result = app(OutboundTransactionalMail::class)->sendMailable(
        TransactionalMailOperation::InvoiceSend,
        'customer@example.test',
        boundaryMailable(),
        'idem-'.Str::uuid(),
    );

    expect($result->status)->toBe(TransactionalMailResult::STATUS_NOT_CONFIGURED);
    Mail::assertNothingSent();
});

it('makes one delivery attempt and does not fall back when ARK Mail fails', function () {
    $this->app['env'] = 'production';

    ShopSettings::current()->persistTrusted([
        'cloud_status' => 'connected',
        'cloud_base_url' => 'https://cloud.example.test',
        'cloud_credential' => 'cloud-install-secret',
        'ark_mail_credential' => 'cloud-install-secret',
        'ark_mail_status' => 'connected',
        'ark_mail_from_email' => 'shop@mail.example.test',
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
        boundaryMailable(),
        'idem-'.Str::uuid(),
    );

    expect($result->ok())->toBeFalse()
        ->and($result->reasonCode)->toBe('global_sending_disabled');
    Mail::assertNothingSent();
    Http::assertSentCount(1);
});

it('does not transmit shop-owned Postmark credentials to Cloud', function () {
    Http::fake([
        'cloud.example.test/*' => Http::response([
            'ok' => true,
            'correlation_id' => 'c-1',
            'message_id' => 'm-1',
            'provider_message_id' => 'p-1',
        ], 200),
    ]);

    ShopSettings::current()->persistTrusted([
        'cloud_status' => 'connected',
        'cloud_base_url' => 'https://cloud.example.test',
        'cloud_credential' => 'cloud-install-secret',
        'ark_mail_status' => 'connected',
        'ark_mail_credential' => 'cloud-install-secret',
        'ark_mail_from_email' => 'shop@mail.example.test',
    ]);

    $client = app(ArkMailClient::class);
    $envelope = TransactionalMailEnvelope::fromMailable(
        TransactionalMailOperation::EstimateSend,
        'customer@example.test',
        boundaryMailable(),
        'idem-'.Str::uuid(),
        'repair_order',
        '1',
    );

    expect($client->send($envelope)->ok())->toBeTrue();

    Http::assertSent(function ($request) {
        $body = $request->body();
        $headers = json_encode($request->headers());

        return ! str_contains($body, 'postmark_token')
            && ! str_contains($body, 'POSTMARK')
            && ! str_contains((string) $headers, 'postmark_token');
    });
});

it('exposes no stock BYO Postmark configuration surface', function () {
    $blade = file_get_contents(resource_path('views/operations/settings/partials/ark-platform-settings.blade.php'));
    $messaging = file_get_contents(resource_path('views/operations/settings/partials/customer-messaging-settings.blade.php'));
    $controller = file_get_contents(app_path('Ark/Operations/Settings/ShopIntegrationSettingsController.php'));
    $outbound = file_get_contents(app_path('Ark/Mail/OutboundTransactionalMail.php'));

    expect($blade)->not->toContain('name="email_provider"')
        ->and($blade)->not->toContain('name="postmark_token"')
        ->and($blade)->not->toContain('value="postmark"')
        ->and($blade)->toContain('Connect ARK Platform')
        ->and($messaging)->not->toContain('name="postmark_token"')
        ->and($controller)->not->toContain("'postmark'")
        ->and($controller)->not->toContain('postmark_token')
        ->and($outbound)->not->toContain('byo_postmark')
        ->and($outbound)->not->toContain('legacy_postmark')
        ->and($outbound)->not->toContain('PROVIDER_POSTMARK')
        ->and($outbound)->not->toContain('sendViaByoPostmark');
});

it('remains structurally extensible via the OutboundTransactionalMail binding', function () {
    $this->app->bind(OutboundTransactionalMail::class, function ($app) {
        return new class($app->make(ArkMailClient::class)) extends OutboundTransactionalMail
        {
            public function providerMode(): string
            {
                return 'none';
            }
        };
    });

    expect(app(OutboundTransactionalMail::class)->providerMode())->toBe('none')
        ->and(app(OutboundTransactionalMail::class)->isReady())->toBeFalse();
});
