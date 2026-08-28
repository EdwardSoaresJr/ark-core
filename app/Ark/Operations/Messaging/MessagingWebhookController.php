<?php

namespace App\Ark\Operations\Messaging;

use App\Ark\Operations\Telephony\TwilioWebhookVerifier;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class MessagingWebhookController
{
    public function __invoke(
        Request $request,
        TwilioWebhookVerifier $verifier,
        TwilioInboundMessageParser $parser,
        ProcessInboundSmsConsentAction $consent,
        ProcessMessageActionReplyAction $messageActions,
        TwilioSmsIngress $ingress,
    ): Response {
        if (! $verifier->isValid($request)) {
            return response('Invalid signature.', 401);
        }

        $payload = $parser->parse($request);

        $consentResult = $consent->execute(
            fromPhone: $payload->contactKey,
            body: $payload->body,
            providerMessageSid: $payload->providerMessageId,
        );

        if ($consentResult['handled']) {
            cache()->put(MessagingHealth::WEBHOOK_RECEIVED_CACHE_KEY, now(), now()->addDays(30));

            return filled($consentResult['confirmation'] ?? null)
                ? TwilioMessagingTwimlResponse::message((string) $consentResult['confirmation'])
                : TwilioMessagingTwimlResponse::empty();
        }

        $actionResult = $messageActions->execute($payload);

        if ($actionResult['handled']) {
            cache()->put(MessagingHealth::WEBHOOK_RECEIVED_CACHE_KEY, now(), now()->addDays(30));

            return filled($actionResult['confirmation'] ?? null)
                ? TwilioMessagingTwimlResponse::message((string) $actionResult['confirmation'])
                : TwilioMessagingTwimlResponse::empty();
        }

        $ingress->ingest($payload);

        cache()->put(MessagingHealth::WEBHOOK_RECEIVED_CACHE_KEY, now(), now()->addDays(30));

        return TwilioMessagingTwimlResponse::empty();
    }
}
