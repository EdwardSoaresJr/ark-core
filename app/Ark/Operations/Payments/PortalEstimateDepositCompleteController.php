<?php

namespace App\Ark\Operations\Payments;

use App\Ark\Operations\Portal\ResolveEstimateAccessTokenAction;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PortalEstimateDepositCompleteController
{
    public function __invoke(
        Request $request,
        string $token,
        PaymentGatewayAttempt $attempt,
        ResolveEstimateAccessTokenAction $resolve,
        CompletePortalKeyedPaymentAction $complete,
        PaymentGatewayAttemptPresenter $presenter,
        CardPresentCaptureProjection $capture,
    ): JsonResponse {
        abort_unless($capture->portalPayEnabled(), 503, 'Online deposits are not enabled.');

        $accessToken = $resolve->execute($token, touchViewed: false);

        abort_unless($accessToken !== null, 404);
        abort_unless($attempt->estimate_access_token_id === $accessToken->id, 404);
        abort_unless($attempt->capture_surface === PaymentCaptureSurface::PortalEstimateDeposit, 404);

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

        $presented = $presenter->forAttempt($attempt);

        return response()->json([
            'attempt' => $presented,
            'message' => sprintf(
                'Thank you - we received your %s deposit.',
                $presented['amount'],
            ),
        ]);
    }
}
