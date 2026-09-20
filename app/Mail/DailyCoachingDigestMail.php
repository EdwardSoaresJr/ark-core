<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class DailyCoachingDigestMail extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * @param  array{
     *     range_label: string,
     *     strongest_call: array<string, mixed>|null,
     *     coaching_opportunity: array<string, mixed>|null,
     *     call_intelligence_url: string,
     *     review_count: int
     * }  $digest
     */
    public function __construct(public array $digest) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'ARK Daily Coaching Digest · '.$this->digest['range_label'],
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'mail.daily-coaching-digest',
        );
    }
}
