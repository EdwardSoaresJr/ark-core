<?php

namespace App\Ark\Operations\Payments;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PortalInvoicePayCompleteController
{
    public function __invoke(
        Request $request,
        string $token,
        PaymentGatewayAttempt $attempt,
        ResolveCustomerPayTokenAction $resolve,
        CompleteSquareKeyedPaymentAction $complete,
        PaymentGatewayAttemptPresenter $presenter,
        SquareConfiguration $square,
    ): JsonResponse {
        abort_unless($square->portalPayEnabled(), 503);

        $accessToken = $resolve->execute($token);

        abort_unless($accessToken !== null, 404);
        abort_unless($attempt->customer_access_token_id === $accessToken->id, 404);

        $data = $request->validate([
            'source_id' => ['required', 'string', 'max:255'],
        ]);

        try {
            $attempt = $complete->execute($attempt, $data['source_id']);
        } catch (\RuntimeException $exception) {
            return response()->json([
                'message' => $exception->getMessage(),
                'attempt' => $presenter->forAttempt($attempt->fresh()),
            ], 422);
        }

        return response()->json([
            'attempt' => $presenter->forAttempt($attempt),
            'message' => 'Payment received. Thank you.',
        ]);
    }
}
