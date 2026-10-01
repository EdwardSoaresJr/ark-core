<?php

use App\Ark\Operations\Messaging\OutboundSmsTransport;
use Tests\Support\FakeOutboundSmsTransport;

function bindFakeOutboundSms(string $messageId = 'SMfake0001', string $status = 'queued'): FakeOutboundSmsTransport
{
    $transport = new FakeOutboundSmsTransport($messageId, $status);
    app()->instance(OutboundSmsTransport::class, $transport);

    return $transport;
}

function bindFailingOutboundSms(string $message = 'Outbound SMS failed.'): FakeOutboundSmsTransport
{
    $transport = new FakeOutboundSmsTransport(fail: true, failureMessage: $message);
    app()->instance(OutboundSmsTransport::class, $transport);

    return $transport;
}
