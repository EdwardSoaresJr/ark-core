<?php

namespace App\Ark\Installation\Mail;

use App\Ark\Mail\TransactionalMailResult;
use Symfony\Component\Mailer\Mailer;
use Symfony\Component\Mailer\Transport;
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
        private readonly InstallationMailHostGuard $hostGuard = new InstallationMailHostGuard,
    ) {}

    /**
     * @param  array<string, mixed>  $variables
     * @param  list<array{filename: string, mime?: string, path?: string, content?: string, content_base64?: string}>  $attachments
     */
    public function send(
        string $operation,
        string $recipientEmail,
        array $variables = [],
        array $attachments = [],
        ?string $correlationId = null,
    ): TransactionalMailResult {
        if ($this->configuration->hostedIsolated()) {
            return TransactionalMailResult::rejected(
                'hosted_isolation',
                'Hosted installations use managed email delivery only.',
                $correlationId,
            );
        }

        $config = $this->configuration->activeResolved();
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

            $mailer = new Mailer(Transport::fromDsn($this->dsn($config)));
            $mailer->send($email);

            return TransactionalMailResult::transportAccepted($correlationId, [
                'source' => $config['source'],
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
     * @param  array{
     *     mailer: string,
     *     host: ?string,
     *     port: ?int,
     *     encryption: ?string,
     *     username: ?string,
     *     password: ?string,
     * }  $config
     */
    private function dsn(array $config): string
    {
        if ($config['mailer'] === 'sendmail') {
            $path = (string) config('mail.mailers.sendmail.path', '/usr/sbin/sendmail -bs -i');

            return 'sendmail://default?command='.urlencode($path);
        }

        $user = $config['username'] ?? null;
        $pass = $config['password'] ?? null;
        $auth = '';
        if (filled($user)) {
            $auth = rawurlencode((string) $user);
            if (filled($pass)) {
                $auth .= ':'.rawurlencode((string) $pass);
            }
            $auth .= '@';
        }

        $scheme = match ($config['encryption'] ?? null) {
            'ssl' => 'smtps',
            default => 'smtp',
        };

        $query = [];
        if (($config['encryption'] ?? null) === 'tls') {
            $query[] = 'encryption=tls';
        }

        $dsn = sprintf(
            '%s://%s%s:%d',
            $scheme,
            $auth,
            $config['host'],
            (int) $config['port'],
        );

        return $query === [] ? $dsn : $dsn.'?'.implode('&', $query);
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

        // Avoid leaking credentials that Symfony may echo in transport exceptions.
        $message = preg_replace('/:[^:@\\s\\/]+@/', ':***@', $message) ?? $message;

        return 'Mail transport error: '.$message;
    }
}
