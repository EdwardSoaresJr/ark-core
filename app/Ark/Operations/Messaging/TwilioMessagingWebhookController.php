<?php

namespace App\Ark\Operations\Messaging;

use App\Ark\Operations\Communications\OperationalCommunicationChannel;
use App\Ark\Operations\Conversations\ConversationContactSurface;
use App\Ark\Operations\Conversations\InboundConversationPayload;
use App\Ark\Operations\PhoneNumber;
use App\Ark\Operations\Telephony\TwilioWebhookVerifier;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

final class TwilioMessagingWebhookController
{
    public function incoming(
        Request $request,
        TwilioWebhookVerifier $verifier,
        InboundSmsConversationIngress $ingress,
    ): Response {
        if (! $verifier->isValid($request)) {
            return response('Invalid signature.', 401);
        }

        $from = PhoneNumber::normalize((string) $request->input('From', '')) ?? '';
        $sid = trim((string) $request->input('MessageSid', ''));

        $ingress->ingest(new InboundConversationPayload(
            contactSurface: ConversationContactSurface::Phone,
            contactKey: $from,
            providerMessageId: $sid,
            channel: OperationalCommunicationChannel::Sms,
            body: (string) $request->input('Body', ''),
            metadata: [
                'provider_message_id' => $sid,
                'twilio_message_sid' => $sid,
                'to' => (string) $request->input('To', ''),
            ],
        ));

        return response('', 200);
    }

    public function status(Request $request, TwilioWebhookVerifier $verifier): Response
    {
        if (! $verifier->isValid($request)) {
            return response('Invalid signature.', 401);
        }

        return response('', 204);
    }
}
