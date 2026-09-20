<?php

namespace App\Ark\Operations\Telephony;

use App\Ark\Operations\Conversations\CustomerCallContextResolver;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ClaimCallSessionController
{
    public function __invoke(
        Request $request,
        CallSession $callSession,
        CallSessionQueue $queue,
        CustomerCallContextResolver $callContextResolver,
        IncomingCallContextBroadcaster $broadcaster,
    ): JsonResponse {
        $callSession->forceFill([
            'owned_by_user_id' => $request->user()->id,
            'owned_at' => now(),
        ])->save();

        $queue->markCallerHandled($callSession);

        $callSession->load(['customer', 'owner']);

        $context = $callSession->customer_id !== null
            ? $callContextResolver->resolveForCustomer($callSession->customer)
            : $callContextResolver->resolve($callSession->normalized_from);

        $broadcaster->broadcastUpdate($callSession, $context);

        return response()->json([
            'claimed' => true,
            'call_session_id' => $callSession->id,
            'owned_by_user_id' => $callSession->owned_by_user_id,
            'owned_by_name' => $callSession->owner?->name,
        ]);
    }
}
