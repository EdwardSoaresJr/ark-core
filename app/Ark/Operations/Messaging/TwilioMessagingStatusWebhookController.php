<?php

namespace App\Ark\Operations\Messaging;

use App\Ark\Operations\Telephony\TwilioWebhookVerifier;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class TwilioMessagingStatusWebhookController
{
    public function __invoke(
        Request $request,
        TwilioWebhookVerifier $verifier,
        ProcessTwilioMessageDeliveryStatusAction $deliveryStatus,
    ): Response {
        if (! $verifier->isValid($request)) {
            return response('Invalid signature.', 401);
        }

        $deliveryStatus->execute($request->all());

        return response('', 204);
    }
}
