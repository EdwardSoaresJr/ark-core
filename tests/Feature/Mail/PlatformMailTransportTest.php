<?php

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Symfony\Component\Mailer\Exception\TransportException;

test('mailer names cannot select postmark or smtp', function () {
    config([
        'mail.default' => 'postmark',
        'services.postmark.token' => 'should-not-send',
        'mail.mailers.smtp.host' => '127.0.0.1',
        'mail.mailers.smtp.port' => 1,
    ]);

    config(['mail.mailers.mailgun' => ['transport' => 'mailgun']]);

    expect((string) Mail::mailer('postmark')->getSymfonyTransport())->toBe('ark-platform://platform')
        ->and((string) Mail::mailer('smtp')->getSymfonyTransport())->toBe('ark-platform://platform')
        ->and((string) Mail::mailer('ses')->getSymfonyTransport())->toBe('ark-platform://platform')
        ->and((string) Mail::mailer('mailgun')->getSymfonyTransport())->toBe('ark-platform://platform')
        ->and((string) Mail::mailer('resend')->getSymfonyTransport())->toBe('ark-platform://platform')
        ->and((string) Mail::mailer('sendmail')->getSymfonyTransport())->toBe('ark-platform://platform')
        ->and(class_exists(\Symfony\Component\Mailer\Bridge\Postmark\Transport\PostmarkTransportFactory::class))->toBeFalse();
});

test('valid smtp and postmark settings cannot send from core', function () {
    enableHostedPlatformMail();
    config([
        'mail.default' => 'smtp',
        'mail.mailers.smtp.host' => '127.0.0.1',
        'mail.mailers.smtp.port' => 2525,
        'mail.mailers.smtp.username' => 'core-user',
        'mail.mailers.smtp.password' => 'core-secret',
        'services.postmark.token' => 'should-not-send',
    ]);

    $mailable = new class extends \Illuminate\Mail\Mailable
    {
        public function envelope(): \Illuminate\Mail\Mailables\Envelope
        {
            return new \Illuminate\Mail\Mailables\Envelope(subject: 'Should not send');
        }

        public function content(): \Illuminate\Mail\Mailables\Content
        {
            return new \Illuminate\Mail\Mailables\Content(htmlString: '<p>123456</p>');
        }
    };

    expect(fn () => Mail::to('advisor@example.test')->send($mailable))
        ->toThrow(TransportException::class, 'Core does not render or deliver email.');

    Http::assertNothingSent();
});

test('platform rejection does not fall back to the local mailer', function () {
    Mail::fake();
    enableHostedPlatformMail();
    config(['mail.default' => 'smtp', 'mail.mailers.smtp.host' => '127.0.0.1', 'mail.mailers.smtp.port' => 1]);
    Http::fake(function (\Illuminate\Http\Client\Request $request) {
        if (str_contains($request->url(), '/api/v1/services/mail/messages/transactional')) {
            return Http::response([
                'ok' => false,
                'message' => 'Sending is disabled.',
            ], 422);
        }

        return Http::response(['unexpected' => $request->url()], 599);
    });

    $this->artisan('shop-excellence:owner-digest', ['--email' => 'owner@example.test'])
        ->assertFailed();

    Mail::assertNothingSent();
    Http::assertSent(fn (\Illuminate\Http\Client\Request $request): bool => str_contains($request->url(), '/api/v1/services/mail/messages/transactional'));
    Http::assertNotSent(fn (\Illuminate\Http\Client\Request $request): bool => str_contains($request->url(), 'postmark') || str_contains($request->url(), '127.0.0.1'));
});

test('owner digest uses platform and ignores the local mailer', function () {
    enableHostedPlatformMail();
    fakeHostedPlatformMail();
    config(['mail.default' => 'postmark', 'services.postmark.token' => 'should-not-send']);

    $this->artisan('shop-excellence:owner-digest', ['--email' => 'owner@example.test'])
        ->assertSuccessful();

    assertPlatformTransactionalMail('owner.digest', 'owner@example.test');
    Http::assertNotSent(fn (\Illuminate\Http\Client\Request $request): bool => str_contains($request->url(), 'postmark'));
});

test('core composer dependencies do not include a mail provider package', function () {
    $composer = json_decode((string) file_get_contents(base_path('composer.json')), true);
    $packages = array_keys(array_merge($composer['require'] ?? [], $composer['require-dev'] ?? []));

    expect($packages)->not->toContain('symfony/postmark-mailer')
        ->and($packages)->not->toContain('symfony/amazon-mailer')
        ->and($packages)->not->toContain('symfony/mailgun-mailer')
        ->and($packages)->not->toContain('resend/resend-php');
});

test('application code does not send through laravel mail', function () {
    $offenders = [];

    $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(app_path()));
    foreach ($iterator as $file) {
        if (! $file->isFile() || $file->getExtension() !== 'php') {
            continue;
        }

        $contents = file_get_contents($file->getPathname());
        if (preg_match('/Mail::(to|queue|send|raw)\b/', $contents) === 1) {
            $offenders[] = str_replace(base_path().'/', '', $file->getPathname());
        }
    }

    expect($offenders)->toBe([])
        ->and(is_dir(app_path('Mail')))->toBeFalse()
        ->and(is_dir(resource_path('views/mail')))->toBeFalse()
        ->and(file_exists(resource_path('views/vendor/mail/html/customer-message.blade.php')))->toBeFalse()
        ->and(file_exists(resource_path('views/vendor/mail/html/shop-header.blade.php')))->toBeFalse();
});
