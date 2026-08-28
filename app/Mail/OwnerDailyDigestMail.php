<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class OwnerDailyDigestMail extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * @param  array{
     *     range_label: string,
     *     headlines: list<array{label: string, value: string, hint: string, tone: 'good'|'warn'|null}>,
     *     priorities: list<array{label: string, count: int, hint: string, tone: string}>,
     *     reconciliation: array{reconciles: bool, sales_posted: string, cash_collected: string, reconciled: string, delta_label: string},
     *     lines: list<array{label: string, value: string, hint: string, tone: 'good'|'warn'|null}>,
     *     report_url: string,
     *     financial_url: string,
     *     margin_health_url: string,
     *     owner_pl_url: string,
     *     bookend_url: string
     * }  $digest
     */
    public function __construct(public array $digest) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'ARK daily shop pulse · '.$this->digest['range_label'],
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'mail.owner-daily-digest',
        );
    }
}
