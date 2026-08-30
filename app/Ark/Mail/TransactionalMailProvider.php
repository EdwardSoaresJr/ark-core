<?php

namespace App\Ark\Mail;

interface TransactionalMailProvider
{
    public function name(): string;

    public function isConfigured(): bool;

    public function send(TransactionalMailEnvelope $envelope): TransactionalMailResult;
}
