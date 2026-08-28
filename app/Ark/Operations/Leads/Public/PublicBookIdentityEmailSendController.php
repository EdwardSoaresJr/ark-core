<?php

namespace App\Ark\Operations\Leads\Public;

use App\Ark\Operations\EmailVerification\EmailVerificationAuthority;
use App\Ark\Operations\EmailVerification\EmailVerificationException;
use App\Ark\Operations\Portal\PortalAccessEasterEgg;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Book modal email OTP — any email (lead verification), not portal-only.
 */
final class PublicBookIdentityEmailSendController
{
    public function __construct(
        private readonly EmailVerificationAuthority $authority,
    ) {}

    public function __invoke(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'email' => ['required', 'string', 'email:rfc', 'max:255'],
        ]);

        $email = strtolower(trim($validated['email']));

        $easterEgg = PortalAccessEasterEgg::messageFor($email);
        if ($easterEgg !== null) {
            return response()->json(['ok' => true, 'notice' => $easterEgg]);
        }

        try {
            $this->authority->issue(
                $email,
                $request->ip(),
                $request->userAgent(),
            );
        } catch (EmailVerificationException $exception) {
            return response()->json([
                'ok' => false,
                'message' => $exception->getMessage(),
            ], 422);
        }

        return response()->json(['ok' => true]);
    }
}
