<?php

use App\Ark\Installation\Mail\InstallationMailConfiguration;
use App\Ark\Installation\Mail\InstallationMailMessageRenderer;
use App\Ark\Installation\Mail\InstallationMailSender;
use App\Ark\Installation\Mail\InstallationMailTransportFactory;
use App\Ark\Mail\TransactionalMailResult;
use App\Ark\Operations\Settings\ShopSettings;
use Symfony\Component\Mailer\Envelope;
use Symfony\Component\Mailer\SentMessage;
use Symfony\Component\Mailer\Transport\TransportInterface;
use Symfony\Component\Mime\Email;
use Symfony\Component\Mime\RawMessage;

it('records transport acceptance with body links and attachments via a controlled SMTP transport', function () {
    $captured = [];

    $fakeTransport = new class($captured) implements TransportInterface
    {
        /** @param array<int, array<string, mixed>> $captured */
        public function __construct(private array &$captured) {}

        public function send(RawMessage $message, ?Envelope $envelope = null): ?SentMessage
        {
            $email = $message instanceof Email ? $message : null;
            expect($email)->not->toBeNull();

            $this->captured[] = [
                'subject' => $email->getSubject(),
                'html' => (string) $email->getHtmlBody(),
                'text' => (string) $email->getTextBody(),
                'attachments' => count($email->getAttachments()),
                'to' => $email->getTo()[0]->getAddress(),
            ];

            return new SentMessage($message, $envelope ?? Envelope::create($message));
        }

        public function __toString(): string
        {
            return 'controlled-smtp://test';
        }
    };

    $factory = Mockery::mock(InstallationMailTransportFactory::class);
    $factory->shouldReceive('make')->once()->andReturn($fakeTransport);

    $sender = new InstallationMailSender(
        app(InstallationMailConfiguration::class),
        app(InstallationMailMessageRenderer::class),
        $factory,
    );

    ShopSettings::current()->persistTrusted([
        'installation_mail_mode' => InstallationMailConfiguration::MODE_SHOP,
        'installation_mail_enabled' => true,
        'installation_mail_mailer' => 'smtp',
        'installation_mail_host' => '127.0.0.1',
        'installation_mail_port' => 587,
        'installation_mail_from_address' => 'shop@example.test',
    ]);

    $result = $sender->send(
        operation: 'estimate.send',
        recipientEmail: 'customer@example.test',
        variables: [
            'shop_name' => 'Demo Shop',
            'vehicle' => '2018 Ford F-150',
            'repair_order_number' => '1001',
            'estimate_total' => '$250.00',
            'portal_url' => 'https://shop.test/portal/estimates/token',
        ],
        attachments: [[
            'filename' => 'estimate-ro-1001.pdf',
            'mime' => 'application/pdf',
            'content' => '%PDF-1.4 estimate',
        ]],
    );

    expect($result->status)->toBe(TransactionalMailResult::STATUS_TRANSPORT_ACCEPTED)
        ->and($result->ok())->toBeTrue()
        ->and($result->confirmedDelivered())->toBeFalse()
        ->and($captured)->toHaveCount(1)
        ->and($captured[0]['to'])->toBe('customer@example.test')
        ->and($captured[0]['subject'])->toContain('Estimate')
        ->and($captured[0]['html'])->toContain('https://shop.test/portal/estimates/token')
        ->and($captured[0]['html'])->toContain('2018 Ford F-150')
        ->and($captured[0]['attachments'])->toBe(1);
});

it('renders invoice inspection document and statement content with attachments', function () {
    $renderer = app(InstallationMailMessageRenderer::class);

    $invoice = $renderer->render('invoice.send', [
        'shop_name' => 'Demo',
        'vehicle' => 'Civic',
        'repair_order_number' => '9',
        'balance_due' => '$40.00',
        'pay_url' => 'https://shop.test/pay/abc',
    ]);
    $inspection = $renderer->render('inspection.send', [
        'shop_name' => 'Demo',
        'recipient_name' => 'Tech',
        'vehicle' => 'Civic',
        'repair_order_label' => 'RO #9',
        'walk_url' => 'https://shop.test/walk/1',
    ]);
    $document = $renderer->render('document.send', [
        'shop_name' => 'Demo',
        'document_label' => 'Authorization',
        'document_title' => 'Signed auth',
    ]);
    $statement = $renderer->render('statement.send', [
        'shop_name' => 'Demo',
        'customer_name' => 'Fleet Co',
        'balance_due' => '$150.00',
        'as_of' => '2026-11-27',
    ]);

    expect($invoice['html'])->toContain('https://shop.test/pay/abc')
        ->and($inspection['html'])->toContain('https://shop.test/walk/1')
        ->and($document['subject'])->toContain('Authorization')
        ->and($statement['html'])->toContain('$150.00');
});

it('never reports failure as success and redacts credentials', function () {
    $factory = Mockery::mock(InstallationMailTransportFactory::class);
    $factory->shouldReceive('make')->once()->andThrow(new RuntimeException('smtp://user:secret-pass@127.0.0.1 failed'));

    $sender = new InstallationMailSender(
        app(InstallationMailConfiguration::class),
        app(InstallationMailMessageRenderer::class),
        $factory,
    );

    ShopSettings::current()->persistTrusted([
        'installation_mail_mode' => InstallationMailConfiguration::MODE_SHOP,
        'installation_mail_enabled' => true,
        'installation_mail_mailer' => 'smtp',
        'installation_mail_host' => '127.0.0.1',
        'installation_mail_port' => 587,
        'installation_mail_from_address' => 'shop@example.test',
    ]);

    $result = $sender->send('installation.mail.test', 'owner@example.test', ['shop_name' => 'Demo']);

    expect($result->ok())->toBeFalse()
        ->and($result->status)->toBe(TransactionalMailResult::STATUS_FAILED)
        ->and($result->operatorMessage())->not->toContain('secret-pass');
});
