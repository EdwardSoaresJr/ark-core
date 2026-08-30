<?php

namespace App\Ark\Mail\Providers;

use App\Ark\Mail\TransactionalMailEnvelope;
use App\Ark\Mail\TransactionalMailProvider;
use App\Ark\Mail\TransactionalMailResult;
use Illuminate\Support\Facades\Log;

final class NotConfiguredMailProvider implements TransactionalMailProvider
{
    public function name(): string
    {
        return 'none';
    }

    public function isConfigured(): bool
    {
        return false;
    }

    public function send(TransactionalMailEnvelope $envelope): TransactionalMailResult
    {
        Log::info('ark_mail.not_configured', [
            'operation' => $envelope->operation->value,
            'correlation_id' => $envelope->correlationId,
        ]);

        return TransactionalMailResult::notConfigured();
    }
}
