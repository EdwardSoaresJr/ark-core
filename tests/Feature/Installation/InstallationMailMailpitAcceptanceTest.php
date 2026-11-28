<?php

/**
 * Isolated real-SMTP acceptance against Mailpit (docker ark-mailpit-290).
 * Does not send to production customers.
 */

use App\Ark\Installation\Mail\CustomerOutboundMail;
use App\Ark\Installation\Mail\InstallationMailConfiguration;
use App\Ark\Installation\Mail\InstallationMailSender;
use App\Ark\Mail\TransactionalMailResult;
use App\Ark\Operations\Settings\ShopSettings;

function mailpitBase(): string
{
    return (string) (getenv('ARK_MAILPIT_API') ?: 'http://127.0.0.1:18025');
}

function mailpitSmtpHost(): string
{
    return (string) (getenv('ARK_MAILPIT_SMTP_HOST') ?: '127.0.0.1');
}

function mailpitSmtpPort(): int
{
    // Must be an InstallationMailHostGuard-allowed port (25/465/587/2525/2465/2587).
    return (int) (getenv('ARK_MAILPIT_SMTP_PORT') ?: 2587);
}

/**
 * Bypass Laravel Http::preventStrayRequests() — this acceptance talks to local Mailpit only.
 *
 * @return array<string, mixed>
 */
function mailpitRequest(string $method, string $path): array
{
    $url = rtrim(mailpitBase(), '/').$path;
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_CUSTOMREQUEST => strtoupper($method),
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 5,
        CURLOPT_HTTPHEADER => ['Accept: application/json'],
    ]);
    $body = curl_exec($ch);
    $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);

    if ($body === false || $status < 200 || $status >= 300) {
        throw new RuntimeException("Mailpit {$method} {$path} failed ({$status}): {$error}");
    }

    $json = json_decode($body, true);

    return is_array($json) ? $json : [];
}

function mailpitAvailable(): bool
{
    try {
        mailpitRequest('GET', '/api/v1/info');

        return true;
    } catch (Throwable) {
        return false;
    }
}

function mailpitDeleteAll(): void
{
    mailpitRequest('DELETE', '/api/v1/messages');
}

/**
 * @return list<array<string, mixed>>
 */
function mailpitMessages(): array
{
    $json = mailpitRequest('GET', '/api/v1/messages');

    return is_array($json['messages'] ?? null) ? $json['messages'] : [];
}

/**
 * @return array<string, mixed>
 */
function mailpitMessage(string $id): array
{
    return mailpitRequest('GET', '/api/v1/message/'.rawurlencode($id));
}

function configureMailpitShopMail(): void
{
    ShopSettings::current()->persistTrusted([
        'shop_name' => 'Mailpit Acceptance Shop',
        'installation_mail_mode' => InstallationMailConfiguration::MODE_SHOP,
        'installation_mail_enabled' => true,
        'installation_mail_mailer' => 'smtp',
        'installation_mail_host' => mailpitSmtpHost(),
        'installation_mail_port' => mailpitSmtpPort(),
        'installation_mail_encryption' => null,
        'installation_mail_username' => null,
        'installation_mail_password' => null,
        'installation_mail_from_address' => 'shop@mailpit.acceptance.test',
        'installation_mail_from_name' => 'Mailpit Acceptance Shop',
        'installation_mail_reply_to' => 'reply@mailpit.acceptance.test',
        'cloud_status' => 'disconnected',
        'platform_status' => null,
        'ark_mail_from_email' => null,
    ]);
}

beforeEach(function () {
    if (! mailpitAvailable()) {
        test()->markTestSkipped('Mailpit is not reachable at '.mailpitBase());
    }

    mailpitDeleteAll();
    configureMailpitShopMail();
});

it('accepts estimate invoice inspection document and statement over real SMTP with content links and PDFs', function () {
    $sender = app(InstallationMailSender::class);
    $pdf = '%PDF-1.4 mailpit-acceptance';

    $cases = [
        [
            'operation' => 'estimate.send',
            'to' => 'estimate@mailpit.acceptance.test',
            'variables' => [
                'shop_name' => 'Mailpit Acceptance Shop',
                'vehicle' => '2018 Ford F-150',
                'repair_order_number' => '1001',
                'estimate_total' => '$250.00',
                'portal_url' => 'https://shop.test/portal/estimates/token-estimate',
            ],
            'filename' => 'estimate-ro-1001.pdf',
            'needle' => 'token-estimate',
        ],
        [
            'operation' => 'invoice.send',
            'to' => 'invoice@mailpit.acceptance.test',
            'variables' => [
                'shop_name' => 'Mailpit Acceptance Shop',
                'vehicle' => '2019 Honda Civic',
                'repair_order_number' => '1002',
                'balance_due' => '$40.00',
                'pay_url' => 'https://shop.test/portal/pay/token-invoice',
            ],
            'filename' => 'invoice-ro-1002.pdf',
            'needle' => 'token-invoice',
        ],
        [
            'operation' => 'inspection.send',
            'to' => 'inspection@mailpit.acceptance.test',
            'variables' => [
                'shop_name' => 'Mailpit Acceptance Shop',
                'recipient_name' => 'Terry Tech',
                'vehicle' => '2016 Honda CR-V',
                'repair_order_label' => 'RO #1003',
                'walk_url' => 'https://shop.test/inspections/walk/token-inspection',
            ],
            'filename' => 'inspection-notes.pdf',
            'needle' => 'token-inspection',
        ],
        [
            'operation' => 'document.send',
            'to' => 'document@mailpit.acceptance.test',
            'variables' => [
                'shop_name' => 'Mailpit Acceptance Shop',
                'document_label' => 'Authorization',
                'document_title' => 'Signed authorization',
                'repair_order_number' => '1004',
                'vehicle' => '2020 Toyota Tacoma',
            ],
            'filename' => 'authorization.pdf',
            'needle' => 'Signed authorization',
        ],
        [
            'operation' => 'statement.send',
            'to' => 'statement@mailpit.acceptance.test',
            'variables' => [
                'shop_name' => 'Mailpit Acceptance Shop',
                'customer_name' => 'Fleet Co',
                'balance_due' => '$150.00',
                'as_of' => '2026-11-27',
            ],
            'filename' => 'statement-customer-1.pdf',
            'needle' => '$150.00',
        ],
    ];

    foreach ($cases as $case) {
        $result = $sender->send(
            operation: $case['operation'],
            recipientEmail: $case['to'],
            variables: $case['variables'],
            attachments: [[
                'filename' => $case['filename'],
                'mime' => 'application/pdf',
                'content' => $pdf,
            ]],
        );

        expect($result->status)->toBe(TransactionalMailResult::STATUS_TRANSPORT_ACCEPTED)
            ->and($result->ok())->toBeTrue()
            ->and($result->confirmedDelivered())->toBeFalse();
    }

    // Allow Mailpit to index.
    $deadline = microtime(true) + 5;
    do {
        $messages = mailpitMessages();
        if (count($messages) >= 5) {
            break;
        }
        usleep(150_000);
    } while (microtime(true) < $deadline);

    expect($messages)->toHaveCount(5);

    $byTo = [];
    foreach ($messages as $summary) {
        $id = (string) ($summary['ID'] ?? '');
        $detail = mailpitMessage($id);
        $to = strtolower((string) data_get($detail, 'To.0.Address', ''));
        $byTo[$to] = $detail;
    }

    foreach ($cases as $case) {
        $detail = $byTo[$case['to']] ?? null;
        expect($detail)->not->toBeNull("missing Mailpit message for {$case['to']}");
        $html = (string) ($detail['HTML'] ?? '');
        $text = (string) ($detail['Text'] ?? '');
        $subject = (string) ($detail['Subject'] ?? '');
        $attachments = $detail['Attachments'] ?? [];

        expect($html.$text)->toContain($case['needle'])
            ->and($subject)->not->toBe('')
            ->and($attachments)->not->toBeEmpty()
            ->and((string) data_get($attachments, '0.FileName'))->toBe($case['filename']);
    }

    $proof = [
        'captured_at' => now()->toIso8601String(),
        'mailpit_api' => mailpitBase(),
        'smtp' => mailpitSmtpHost().':'.mailpitSmtpPort(),
        'count' => count($messages),
        'messages' => array_map(function (array $summary) use ($byTo): array {
            $to = strtolower((string) data_get($summary, 'To.0.Address', ''));
            $detail = $byTo[$to] ?? mailpitMessage((string) ($summary['ID'] ?? ''));
            $atts = is_array($detail['Attachments'] ?? null) ? $detail['Attachments'] : [];

            return [
                'to' => data_get($detail, 'To.0.Address'),
                'subject' => $detail['Subject'] ?? null,
                'attachment_count' => count($atts),
                'attachment_names' => array_values(array_map(fn ($a) => $a['FileName'] ?? null, $atts)),
            ];
        }, $messages),
    ];

    $dir = base_path('review-artifacts/native-email-290/mailpit');
    if (! is_dir($dir)) {
        mkdir($dir, 0777, true);
    }
    file_put_contents($dir.'/ACCEPTANCE.json', json_encode($proof, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)."\n");
});

it('routes CustomerOutboundMail through installation SMTP to Mailpit when Platform is disconnected', function () {
    $result = app(CustomerOutboundMail::class)->send(
        operation: 'installation.mail.test',
        recipientEmail: 'test-send@mailpit.acceptance.test',
        variables: ['shop_name' => 'Mailpit Acceptance Shop'],
    );

    expect($result->ok())->toBeTrue()
        ->and($result->status)->toBe(TransactionalMailResult::STATUS_TRANSPORT_ACCEPTED);

    $deadline = microtime(true) + 5;
    do {
        $messages = mailpitMessages();
        if ($messages !== []) {
            break;
        }
        usleep(100_000);
    } while (microtime(true) < $deadline);

    expect($messages)->not->toBeEmpty();
    $detail = mailpitMessage((string) $messages[0]['ID']);
    expect((string) ($detail['Subject'] ?? ''))->toContain('Test email')
        ->and((string) data_get($detail, 'To.0.Address'))->toBe('test-send@mailpit.acceptance.test');
});
