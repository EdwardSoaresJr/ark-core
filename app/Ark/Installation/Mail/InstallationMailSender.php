<?php

namespace App\Ark\Installation\Mail;

use App\Ark\Mail\TransactionalMailResult;
use Symfony\Component\Mailer\Mailer;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;
use Throwable;

/**
 * Sends customer mail through installation SMTP/sendmail built at send time
 * so queue workers pick up Settings changes without a process restart.
 */
final class InstallationMailSender
{
    public function __construct(
        private readonly InstallationMailConfiguration $configuration,
        private readonly InstallationMailMessageRenderer $renderer,
        private readonly InstallationMailTransportFactory $transports,
        private readonly InstallationMailHostGuard $hostGuard = new InstallationMailHostGuard,
    ) {}

    /**
     * @param  array<string, mixed>  $variables
     * @param  list<array{filename: string, mime?: string, path?: string, content?: string, content_base64?: string}>  $attachments
     * @param  array<string, mixed>|null  $configOverride  When set (e.g. admin test), skip activeResolved().
     */
    public function send(
        string $operation,
        string $recipientEmail,
        array $variables = [],
        array $attachments = [],
        ?string $correlationId = null,
        ?array $configOverride = null,
    ): TransactionalMailResult {
        if ($this->configuration->hostedIsolated()) {
            return TransactionalMailResult::rejected(
                'hosted_isolation',
                'Hosted installations use managed email delivery only.',
                $correlationId,
            );
        }

        $config = $configOverride ?? $this->configuration->activeResolved();
        if ($config === null) {
            return TransactionalMailResult::notConfigured();
        }

        $recipientEmail = strtolower(trim($recipientEmail));
        if ($recipientEmail === '' || ! filter_var($recipientEmail, FILTER_VALIDATE_EMAIL)) {
            return TransactionalMailResult::rejected('invalid_recipient', 'A valid recipient email is required.', $correlationId);
        }

        try {
            if (($config['mailer'] ?? '') === 'smtp') {
                $this->hostGuard->assertSafe((string) $config['host'], (int) $config['port']);
            }

            $rendered = $this->renderer->render($operation, $variables);
            $email = (new Email)
                ->from(new Address($config['from_address'], (string) ($config['from_name'] ?? '')))
                ->to($recipientEmail)
                ->subject($rendered['subject'])
                ->html($rendered['html'])
                ->text($rendered['text']);

            if (filled($config['reply_to'] ?? null) && filter_var($config['reply_to'], FILTER_VALIDATE_EMAIL)) {
                $email->replyTo($config['reply_to']);
            }

            foreach ($attachments as $attachment) {
                $this->attach($email, $attachment);
            }

            $mailer = new Mailer($this->transports->make($config));
            $mailer->send($email);

            return TransactionalMailResult::transportAccepted($correlationId, [
                'source' => $config['source'] ?? 'unknown',
                'mailer' => $config['mailer'],
                'operation' => $operation,
            ]);
        } catch (Throwable $e) {
            return TransactionalMailResult::failed(
                $this->operatorSafeMessage($e),
                $correlationId,
            );
        }
    }

    /**
     * @param  array{filename: string, mime?: string, path?: string, content?: string, content_base64?: string}  $attachment
     */
    private function attach(Email $email, array $attachment): void
    {
        $filename = (string) ($attachment['filename'] ?? 'attachment');
        $mime = (string) ($attachment['mime'] ?? 'application/octet-stream');

        if (isset($attachment['path']) && is_string($attachment['path']) && is_file($attachment['path'])) {
            $email->attachFromPath($attachment['path'], $filename, $mime);

            return;
        }

        $content = null;
        if (isset($attachment['content']) && is_string($attachment['content'])) {
            $content = $attachment['content'];
        } elseif (isset($attachment['content_base64']) && is_string($attachment['content_base64'])) {
            $decoded = base64_decode($attachment['content_base64'], true);
            $content = $decoded === false ? null : $decoded;
        }

        if ($content !== null && $content !== '') {
            $email->attach($content, $filename, $mime);
        }
    }

    private function operatorSafeMessage(Throwable $e): string
    {
        $message = trim($e->getMessage());
        if ($message === '') {
            return 'The mail server rejected the connection.';
        }

        // Strip credentials that transport exceptions may echo in DSNs or auth lines.
        $message = preg_replace('/:[^:@\\s\\/]+@/', ':***@', $message) ?? $message;
        $message = preg_replace('/(pass(word)?|pwd|secret|credential)\\s*[:=]\\s*\\S+/i', '$1=***', $message) ?? $message;
        $message = preg_replace('#(smtp[s]?://)([^:\\s]+):([^@\\s]+)@#i', '$1$2:***@', $message) ?? $message;

        return 'Mail transport error: '.$message;
    }
}
