<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Attachment;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class ExceptionOccurredMail extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * @param  array<string, mixed>  $context
     */
    public function __construct(public array $context) {}

    public function envelope(): Envelope
    {
        $environment = strtoupper((string) ($this->context['environment'] ?? config('app.env')));
        $status = $this->context['status_code'] ?? '500';
        $exceptionClass = (string) ($this->context['exception_class'] ?? 'Exception');
        $reportId = (string) ($this->context['report_id'] ?? 'unknown');

        return new Envelope(
            subject: sprintf(
                '[%s] ARK error %s · %s: %s',
                $environment,
                $reportId,
                $status,
                class_basename($exceptionClass),
            ),
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'mail.exception-occurred',
        );
    }

    /**
     * @return array<int, Attachment>
     */
    public function attachments(): array
    {
        $reportId = (string) ($this->context['report_id'] ?? 'unknown');
        $markdown = (string) ($this->context['report_markdown'] ?? '');

        if ($markdown === '') {
            return [];
        }

        return [
            Attachment::fromData(fn (): string => $markdown, 'ark-error-'.$reportId.'.md')
                ->withMime('text/markdown'),
        ];
    }
}
