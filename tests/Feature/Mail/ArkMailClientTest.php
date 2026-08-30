<?php

use App\Ark\Install\InstallationIdentity;
use App\Ark\Mail\OutboundTransactionalMail;
use App\Ark\Mail\TransactionalMailOperation;
use App\Ark\Mail\TransactionalMailResult;
use App\Ark\Operations\Settings\ShopSettings;
use App\Mail\DocumentCustomerMail;
use App\Models\User;
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

it('returns not configured when no provider is available', function () {
    config(['mail.default' => 'unavailable-mailer']);

    ShopSettings::current()->persistTrusted([
        'postmark_token' => null,
        'ark_mail_credential' => null,
        'ark_mail_status' => null,
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

it('preserves BYO laravel mail path when postmark is configured', function () {
    ShopSettings::current()->persistTrusted([
        'postmark_token' => 'byo-postmark-token',
        'ark_mail_credential' => null,
        'ark_mail_status' => null,
    ]);

    $outbound = app(OutboundTransactionalMail::class);
    expect($outbound->providerMode())->toBe('byo_postmark');

    $mailable = new class extends \Illuminate\Mail\Mailable
    {
        public function content(): \Illuminate\Mail\Mailables\Content
        {
            return new \Illuminate\Mail\Mailables\Content(htmlString: '<p>hi</p>');
        }

        public function envelope(): \Illuminate\Mail\Mailables\Envelope
        {
            return new \Illuminate\Mail\Mailables\Envelope(subject: 'BYO');
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

it('does not log ark mail credentials on activation failure', function () {
    Http::fake([
        '*/api/v1/activate' => Http::response(['ok' => false, 'message' => 'nope'], 422),
    ]);

    config(['services.ark_mail.base_url' => 'http://ark-mail.test', 'services.ark_mail.allow_activation' => true]);
    ShopSettings::current()->persistTrusted([
        'email' => 'shop@example.test',
        'shop_name' => 'Test Shop',
    ]);

    expect(fn () => app(\App\Ark\Mail\ArkMailActivationClient::class)->activate())
        ->toThrow(RuntimeException::class);
});
