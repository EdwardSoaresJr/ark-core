<?php

namespace App\Ark\Mail\Providers;

use App\Ark\Mail\TransactionalMailEnvelope;
use App\Ark\Mail\TransactionalMailProvider;
use App\Ark\Mail\TransactionalMailResult;
use Illuminate\Support\Facades\Mail;

/**
 * Existing BYO path — Laravel mailer (Postmark/SMTP/log) configured by the shop.
 */
final class LaravelMailProvider implements TransactionalMailProvider
{
    public function __construct(
        private readonly \Closure $mailableFactory,
    ) {}

    public function name(): string
    {
        return 'laravel';
    }

    public function isConfigured(): bool
    {
        return true;
    }

    public function send(TransactionalMailEnvelope $envelope): TransactionalMailResult
    {
        $mailable = ($this->mailableFactory)($envelope);
        Mail::to($envelope->recipientEmail)->send($mailable);

        return TransactionalMailResult::sent($envelope->correlationId);
    }
}
