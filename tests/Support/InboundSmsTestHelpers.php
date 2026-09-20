<?php

use App\Ark\Operations\Communications\OperationalCommunicationChannel;
use App\Ark\Operations\Conversations\ConversationContactSurface;
use App\Ark\Operations\Conversations\InboundConversationPayload;
use App\Ark\Operations\Messaging\InboundSmsConversationIngress;
use App\Ark\Operations\Messaging\ProcessInboundSmsConsentAction;
use App\Ark\Operations\Messaging\RecordCustomerSmsDeliveryStatusAction;
use App\Ark\Operations\Customers\Customer;

function processInboundSmsConsent(string $fromPhone, string $body, ?string $providerMessageId = null): array
{
    return app(ProcessInboundSmsConsentAction::class)->execute($fromPhone, $body, $providerMessageId);
}

function ingestInboundSms(
    string $fromPhone,
    string $body,
    string $providerMessageId = 'SMtest0001',
    ?string $toNumber = '7195559999',
): array {
    return app(InboundSmsConversationIngress::class)->ingest(new InboundConversationPayload(
        contactSurface: ConversationContactSurface::Phone,
        contactKey: $fromPhone,
        providerMessageId: $providerMessageId,
        channel: OperationalCommunicationChannel::Sms,
        body: $body,
        metadata: $toNumber !== null ? ['to_number' => $toNumber] : [],
    ));
}

function recordSmsDeliveryStatus(
    Customer $customer,
    string $messageSid,
    string $status,
    ?string $errorCode = null,
): void {
    app(RecordCustomerSmsDeliveryStatusAction::class)->execute(
        $customer,
        $messageSid,
        $status,
        $errorCode,
    );
}
