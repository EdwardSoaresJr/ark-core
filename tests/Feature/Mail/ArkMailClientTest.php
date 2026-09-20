<?php

use App\Ark\Install\InstallationIdentity;
use App\Ark\Mail\OutboundTransactionalMail;
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

it('creates a durable installation uuid once', function () {
    $a = InstallationIdentity::uuid();
    $b = InstallationIdentity::uuid();

    expect($a)->toBe($b)
        ->and(Str::isUuid($a))->toBeTrue();
});

it('returns not configured in production without ark mail', function () {
    config(['mail.default' => 'array']);
    $this->app['env'] = 'production';

    ShopSettings::current()->persistTrusted([
        'ark_mail_credential' => null,
        'cloud_credential' => null,
        'cloud_status' => null,
        'ark_mail_status' => null,
        'ark_mail_tenant_public_id' => null,
    ]);

    $outbound = app(OutboundTransactionalMail::class);

    expect($outbound->providerMode())->toBe('none');

    $result = $outbound->sendMailable(
        TransactionalMailOperation::DocumentSend,
        'customer@example.test',
        new class extends \Illuminate\Mail\Mailable
        {
            public function content(): \Illuminate\Mail\Mailables\Content
            {
                return new \Illuminate\Mail\Mailables\Content(htmlString: '<p>x</p>');
            }

            public function envelope(): \Illuminate\Mail\Mailables\Envelope
            {
                return new \Illuminate\Mail\Mailables\Envelope(subject: 'Test');
            }
        },
        'idem-'.Str::uuid(),
    );

    expect($result->status)->toBe(TransactionalMailResult::STATUS_NOT_CONFIGURED)
        ->and($result->ok())->toBeFalse()
        ->and($result->operatorMessage())->toContain("isn't configured");
});

it('allows local array mailer outside production', function () {
    config(['mail.default' => 'array']);
    ShopSettings::current()->persistTrusted([
        'ark_mail_credential' => null,
        'cloud_credential' => null,
        'cloud_status' => null,
        'ark_mail_status' => null,
    ]);

    $outbound = app(OutboundTransactionalMail::class);
    expect($outbound->providerMode())->toBe('local_log');

    $mailable = new class extends \Illuminate\Mail\Mailable
    {
        public function content(): \Illuminate\Mail\Mailables\Content
        {
            return new \Illuminate\Mail\Mailables\Content(htmlString: '<p>hi</p>');
        }

        public function envelope(): \Illuminate\Mail\Mailables\Envelope
        {
            return new \Illuminate\Mail\Mailables\Envelope(subject: 'Local');
        }
    };

    $result = $outbound->sendMailable(
        TransactionalMailOperation::EstimateSend,
        'customer@example.test',
        $mailable,
        'idem-'.Str::uuid(),
    );

    expect($result->ok())->toBeTrue();
    Mail::assertOutgoingCount(1);
});

it('ignores MAIL_MAILER=postmark as official production path', function () {
    $this->app['env'] = 'production';
    config(['mail.default' => 'postmark']);
    config(['services.postmark.token' => 'should-not-enable-mail']);

    ShopSettings::current()->persistTrusted([
        'ark_mail_credential' => null,
        'cloud_credential' => null,
        'cloud_status' => null,
        'ark_mail_status' => null,
    ]);

    expect(app(OutboundTransactionalMail::class)->providerMode())->toBe('none')
        ->and(app(OutboundTransactionalMail::class)->isReady())->toBeFalse();
});

it('does not log ark mail credentials on activation failure', function () {
    Http::fake([
        '*/api/v1/pairing/start' => Http::response(['ok' => false, 'message' => 'nope'], 422),
    ]);

    config(['services.ark_mail.base_url' => 'http://ark-mail.test']);
    ShopSettings::current()->persistTrusted([
        'email' => 'shop@example.test',
        'shop_name' => 'Test Shop',
    ]);

    expect(fn () => app(\App\Ark\Mail\ArkMailActivationClient::class)->activate())
        ->toThrow(RuntimeException::class);
});
