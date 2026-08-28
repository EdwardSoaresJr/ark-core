<?php

namespace App\Ark\Operations\Leads\Public;

use App\Ark\Operations\PhoneVerification\PhoneVerificationAuthority;
use App\Ark\Operations\PhoneVerification\PhoneVerificationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class PublicLeadPhoneVerifyCheckController
{
    public function __invoke(
        Request $request,
        PhoneVerificationAuthority $authority,
        LeadPhoneVerification $verification,
    ): JsonResponse {
        $bookIdentity = $request->boolean('book_identity');

        if (! $verification->required() && ! $bookIdentity) {
            return response()->json(['verified' => true]);
        }

        if (! $authority->ready()) {
            return response()->json([
                'message' => 'Phone verification is not available right now.',
                'verified' => false,
            ], 422);
        }

        $validated = $request->validate([
            'phone' => ['required', 'string', 'max:32', new ValidUsPhone],
            'code' => ['required', 'string', 'min:4', 'max:10'],
        ]);

        try {
            $authority->verify($request->session(), $validated['phone'], $validated['code']);
        } catch (PhoneVerificationException $exception) {
            return response()->json([
                'message' => $exception->getMessage(),
                'verified' => false,
            ], 422);
        }

        return response()->json([
            'message' => 'Phone verified.',
            'verified' => true,
        ]);
    }
}
