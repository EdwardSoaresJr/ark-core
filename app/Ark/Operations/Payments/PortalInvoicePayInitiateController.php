<?php

namespace App\Ark\Operations\Payments;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PortalInvoicePayInitiateController
{
    public function __invoke(
        Request $request,
        string $token,
        ResolveCustomerPayTokenAction $resolve,
        InitiateSquarePaymentAction $initiatePayment,
        InitiatePortalDepositRequestAction $initiateDeposit,
        PaymentGatewayAttemptPresenter $presenter,
        CardPresentCaptureProjection $capture,
    ): JsonResponse {
        abort_unless($capture->portalPayEnabled(), 503);

        $accessToken = $resolve->execute($token);

        abort_unless($accessToken !== null, 404);

        $repairOrder = $accessToken->repairOrder()->firstOrFail();

        $attempt = $accessToken->isDepositRequest()
            ? $initiateDeposit->execute($repairOrder, $accessToken)
            : $initiatePayment->execute(
                $repairOrder,
                PaymentCaptureSurface::Portal,
                actor: null,
                accessToken: $accessToken,
            );

        return response()->json([
            'attempt' => $presenter->forAttempt($attempt),
        ]);
    }
}
