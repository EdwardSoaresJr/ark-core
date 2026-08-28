<?php

namespace App\Ark\Operations\Payments;

use App\Ark\Operations\Portal\ResolveEstimateAccessTokenAction;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PortalEstimateDepositInitiateController
{
    public function __invoke(
        Request $request,
        string $token,
        ResolveEstimateAccessTokenAction $resolve,
        InitiatePortalEstimateDepositAction $initiate,
        PaymentGatewayAttemptPresenter $presenter,
        SquareConfiguration $square,
    ): JsonResponse {
        abort_unless($square->portalPayEnabled(), 503, 'Online deposits are not enabled.');

        $accessToken = $resolve->execute($token, touchViewed: false);

        abort_unless($accessToken !== null, 404);

        $repairOrder = $accessToken->repairOrder()->firstOrFail();

        $data = $request->validate([
            'approval_id' => ['required', 'integer', 'min:1'],
        ]);

        try {
            $approval = $initiate->resolveApproval($repairOrder, (int) $data['approval_id']);
            $attempt = $initiate->execute($repairOrder, $accessToken, $approval);
        } catch (\RuntimeException $exception) {
            return response()->json([
                'message' => $exception->getMessage(),
            ], 422);
        }

        return response()->json([
            'attempt' => $presenter->forAttempt($attempt),
        ]);
    }
}
