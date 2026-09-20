<?php

namespace App\Ark\Operations\Messaging;

interface OutboundSmsTransport
{
    public function isConfigured(): bool;

    /**
     * @param  list<string>  $mediaUrls
     */
    public function send(string $toPhone, string $body, array $mediaUrls = []): OutboundSmsResult;
}
