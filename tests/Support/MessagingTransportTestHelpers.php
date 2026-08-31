<?php

use App\Ark\Operations\Messaging\OutboundSmsTransport;
use Tests\Support\FakeOutboundSmsTransport;

function bindFakeOutboundSms(string $messageId = 'SMfake0001', string $status = 'queued'): FakeOutboundSmsTransport
{
    $transport = new FakeOutboundSmsTransport($messageId, $status);
    app()->instance(OutboundSmsTransport::class, $transport);

    return $transport;
}
