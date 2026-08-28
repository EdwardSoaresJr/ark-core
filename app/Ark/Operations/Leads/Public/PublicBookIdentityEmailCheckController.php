<?php

namespace App\Ark\Operations\Leads\Public;

use App\Ark\Operations\Customers\Recognition\CustomerRecognitionProjection;
use App\Ark\Operations\EmailVerification\EmailVerificationAuthority;
use App\Ark\Operations\EmailVerification\EmailVerificationException;
use App\Ark\Operations\Portal\PortalObservationSession;
use App\Ark\Operations\Portal\ResolveCustomerByContact;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Completes Book modal email OTP — recognition if known, guest if not.
 */
final class PublicBookIdentityEmailCheckController
{
    public function __construct(
        private readonly EmailVerificationAuthority $authority,
        private readonly ResolveCustomerByContact $resolveCustomer,
    ) {}

    public function __invoke(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'email' => ['required', 'string', 'email:rfc', 'max:255'],
            'code' => ['required', 'string', 'size:6', 'regex:/^\d{6}$/'],
            'concern' => ['nullable', 'string', 'max:255'],
        ]);

        $email = strtolower(trim($validated['email']));

        try {
            $this->authority->verify($request->session(), $email, $validated['code']);
        } catch (EmailVerificationException $exception) {
            return response()->json([
                'ok' => false,
                'message' => $exception->getMessage(),
            ], 422);
        }

        $bookQuery = array_filter([
            'concern' => filled($validated['concern'] ?? null) ? trim((string) $validated['concern']) : null,
        ]);

        $resolved = $this->resolveCustomer->resolve($email);

        if ($resolved !== null) {
            $request->session()->forget(CustomerRecognitionProjection::SESSION_GUEST_KEY);
            Auth::guard('portal')->login($resolved['customer']);
            $request->session()->regenerate();
            PortalObservationSession::start($request);

            return response()->json([
                'ok' => true,
                'redirect' => route('public.book', $bookQuery),
            ]);
        }

        $request->session()->put(CustomerRecognitionProjection::SESSION_GUEST_KEY, true);

        return response()->json([
            'ok' => true,
            'redirect' => route('public.book', $bookQuery),
        ]);
    }
}
