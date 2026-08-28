<?php

namespace App\Ark\Operations\Leads\Public;

use App\Ark\Operations\PhoneVerification\PhoneVerificationAuthority;
use App\Ark\Operations\PhoneVerification\PhoneVerificationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class PublicLeadPhoneVerifySendController
{
    public function __invoke(Request $request, PhoneVerificationAuthority $authority, LeadPhoneVerification $verification): JsonResponse
    {
        if (! $verification->required() && ! $request->boolean('book_identity')) {
            return response()->json(['message' => 'Phone verification is not required.'], 422);
        }

        if (! $authority->ready()) {
            return response()->json(['message' => 'Phone verification is not available right now.'], 422);
        }

        $validated = $request->validate([
            'phone' => ['required', 'string', 'max:32', new ValidUsPhone],
        ]);

        try {
            $authority->issue(
                $validated['phone'],
                $request->ip(),
                $request->userAgent(),
            );
        } catch (PhoneVerificationException $exception) {
            return response()->json([
                'message' => $exception->getMessage(),
            ], 422);
        }

        return response()->json([
            'message' => 'Verification code sent.',
        ]);
    }
}
