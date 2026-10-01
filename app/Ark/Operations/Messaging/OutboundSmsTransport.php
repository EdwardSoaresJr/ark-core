<?php

namespace App\Ark\Operations\Messaging;

interface OutboundSmsTransport
{
    public function isConfigured(): bool;

    /**
     * @param  list<string>  $mediaUrls
     * @param  list<array{type: string, id: string}>  $context
     */
    public function send(string $toPhone, string $body, array $mediaUrls = [], array $context = []): OutboundSmsResult;
}
