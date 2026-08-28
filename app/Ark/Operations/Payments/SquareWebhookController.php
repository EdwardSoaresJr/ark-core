<?php

namespace App\Ark\Operations\Payments;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use JsonException;

class SquareWebhookController
{
    public function __invoke(
        Request $request,
        SquareWebhookVerifier $verifier,
        ProcessSquareWebhookAction $process,
    ): Response {
        $rawBody = $request->getContent();
        $signature = $request->header('x-square-hmacsha256-signature');
        $notificationUrl = $request->fullUrl();

        if (! $verifier->isValid($notificationUrl, $rawBody, $signature)) {
            return response('Invalid signature.', 401);
        }

        try {
            $payload = json_decode($rawBody, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            return response('Invalid payload.', 400);
        }

        if (! is_array($payload)) {
            return response('Invalid payload.', 400);
        }

        $process->execute($payload);

        return response('ok', 200);
    }
}
