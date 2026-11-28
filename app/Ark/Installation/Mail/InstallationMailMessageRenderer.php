<?php

namespace App\Ark\Installation\Mail;

/**
 * Renders simple HTML bodies for installation transactional mail.
 * Platform-managed templates remain in ARK Shop; this covers standalone Core only.
 */
final class InstallationMailMessageRenderer
{
    /**
     * @param  array<string, mixed>  $variables
     * @return array{subject: string, html: string, text: string}
     */
    public function render(string $operation, array $variables): array
    {
        $shop = (string) ($variables['shop_name'] ?? config('app.name', 'ARK'));

        return match ($operation) {
            'estimate.send' => $this->compose(
                subject: "Estimate from {$shop}",
                intro: "{$shop} sent you an estimate.",
                lines: $this->estimateLines($variables),
                ctaLabel: filled($variables['portal_url'] ?? null) ? 'Review estimate' : null,
                ctaUrl: $variables['portal_url'] ?? null,
            ),
            'invoice.send' => $this->compose(
                subject: "Invoice from {$shop}",
                intro: "{$shop} sent you an invoice.",
                lines: $this->invoiceLines($variables),
                ctaLabel: filled($variables['pay_url'] ?? null) ? 'Pay invoice' : null,
                ctaUrl: $variables['pay_url'] ?? null,
            ),
            'inspection.send' => $this->compose(
                subject: "Inspection from {$shop}",
                intro: "{$shop} shared an inspection walkthrough.",
                lines: [
                    'Hello '.($variables['recipient_name'] ?? 'there').',',
                    'Vehicle: '.($variables['vehicle'] ?? '—'),
                    'Repair order: '.($variables['repair_order_label'] ?? '—'),
                ],
                ctaLabel: filled($variables['walk_url'] ?? null) ? 'Open inspection' : null,
                ctaUrl: $variables['walk_url'] ?? null,
            ),
            'statement.send' => $this->compose(
                subject: "Account statement from {$shop}",
                intro: "{$shop} sent your account statement.",
                lines: [
                    'Customer: '.($variables['customer_name'] ?? '—'),
                    'Balance due: '.($variables['balance_due'] ?? '—'),
                    'As of: '.($variables['as_of'] ?? '—'),
                ],
            ),
            'document.send' => $this->compose(
                subject: (($variables['document_label'] ?? 'Document')." from {$shop}"),
                intro: "{$shop} sent you a document.",
                lines: [
                    'Document: '.($variables['document_title'] ?? ($variables['document_label'] ?? 'Document')),
                    filled($variables['repair_order_number'] ?? null) ? 'Repair order: '.$variables['repair_order_number'] : null,
                    filled($variables['vehicle'] ?? null) ? 'Vehicle: '.$variables['vehicle'] : null,
                    filled($variables['staff_note'] ?? null) ? 'Note: '.$variables['staff_note'] : null,
                ],
            ),
            'installation.mail.test' => $this->compose(
                subject: "Test email from {$shop}",
                intro: "This is a test message from {$shop}. Installation email is working.",
                lines: [
                    'If you received this, SMTP/sendmail accepted the message from ARK Core.',
                    'Transport acceptance is not the same as confirmed delivery to every mailbox.',
                ],
            ),
            'customer.transactional_message' => $this->compose(
                subject: (string) ($variables['subject'] ?? "Message from {$shop}"),
                intro: (string) ($variables['intro'] ?? "{$shop} sent you a message."),
                lines: array_values(array_filter([
                    filled($variables['response_hint'] ?? null) ? (string) $variables['response_hint'] : null,
                    filled($variables['phone_display'] ?? null) ? 'Phone: '.$variables['phone_display'] : null,
                ])),
            ),
            'booking.identity_code' => $this->compose(
                subject: "Your verification code from {$shop}",
                intro: "{$shop} sent a verification code.",
                lines: [
                    'Code: '.($variables['code'] ?? '—'),
                    'Expires in: '.($variables['expires_in'] ?? '—'),
                ],
            ),
            'account.email_verification' => $this->compose(
                subject: "Verify your email — {$shop}",
                intro: 'Hello '.($variables['name'] ?? 'there').',',
                lines: [
                    "Verify your email for {$shop}.",
                    'This link expires in '.($variables['expires_in'] ?? '—').'.',
                ],
                ctaLabel: filled($variables['verification_url'] ?? null) ? 'Verify email' : null,
                ctaUrl: $variables['verification_url'] ?? null,
            ),
            'appointment.send' => $this->compose(
                subject: (string) ($variables['subject'] ?? "Appointment update from {$shop}"),
                intro: (string) ($variables['intro'] ?? "{$shop} sent an appointment update."),
                lines: is_array($variables['lines'] ?? null)
                    ? array_values(array_filter($variables['lines'], fn ($line) => is_string($line) && trim($line) !== ''))
                    : [],
            ),
            default => $this->compose(
                subject: "Message from {$shop}",
                intro: "{$shop} sent you a message.",
                lines: array_map(
                    fn (string $key, mixed $value): string => $key.': '.(is_scalar($value) ? (string) $value : json_encode($value)),
                    array_keys($variables),
                    array_values($variables),
                ),
            ),
        };
    }

    /**
     * @param  array<string, mixed>  $variables
     * @return list<string|null>
     */
    private function estimateLines(array $variables): array
    {
        return [
            'Vehicle: '.($variables['vehicle'] ?? '—'),
            'Repair order: '.($variables['repair_order_number'] ?? '—'),
            'Estimate total: '.($variables['estimate_total'] ?? '—'),
            filled($variables['staff_note'] ?? null) ? 'Note: '.$variables['staff_note'] : null,
        ];
    }

    /**
     * @param  array<string, mixed>  $variables
     * @return list<string|null>
     */
    private function invoiceLines(array $variables): array
    {
        return [
            'Vehicle: '.($variables['vehicle'] ?? '—'),
            'Repair order: '.($variables['repair_order_number'] ?? '—'),
            'Balance due: '.($variables['balance_due'] ?? '—'),
            filled($variables['staff_note'] ?? null) ? 'Note: '.$variables['staff_note'] : null,
        ];
    }

    /**
     * @param  list<string|null>  $lines
     * @return array{subject: string, html: string, text: string}
     */
    private function compose(
        string $subject,
        string $intro,
        array $lines,
        ?string $ctaLabel = null,
        mixed $ctaUrl = null,
    ): array {
        $clean = array_values(array_filter($lines, fn ($line) => is_string($line) && trim($line) !== ''));
        $url = is_string($ctaUrl) && $ctaUrl !== '' ? $ctaUrl : null;

        $textParts = [$intro, '', ...$clean];
        if ($url !== null && $ctaLabel !== null) {
            $textParts[] = '';
            $textParts[] = $ctaLabel.': '.$url;
        }
        $text = implode("\n", $textParts);

        $htmlLines = implode('', array_map(
            fn (string $line): string => '<p style="margin:0 0 8px;">'.e($line).'</p>',
            $clean,
        ));
        $button = '';
        if ($url !== null && $ctaLabel !== null) {
            $button = '<p style="margin:24px 0;"><a href="'.e($url).'" style="background:#206bc4;color:#fff;padding:10px 16px;text-decoration:none;border-radius:4px;">'
                .e($ctaLabel).'</a></p>';
        }

        $html = '<div style="font-family:system-ui,sans-serif;font-size:15px;line-height:1.5;color:#1e293b;">'
            .'<p style="margin:0 0 16px;">'.e($intro).'</p>'
            .$htmlLines
            .$button
            .'</div>';

        return [
            'subject' => $subject,
            'html' => $html,
            'text' => $text,
        ];
    }
}
