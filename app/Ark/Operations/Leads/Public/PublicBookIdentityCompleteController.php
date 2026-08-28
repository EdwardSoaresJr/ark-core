<?php

namespace App\Ark\Operations\Leads\Public;

use App\Ark\Operations\Customers\Recognition\CustomerRecognitionProjection;
use App\Ark\Operations\PhoneNumber;
use App\Ark\Operations\Portal\PortalObservationSession;
use App\Ark\Operations\Portal\ResolveCustomerByContact;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * After Twilio Verify succeeds on /book, route into recognition or guest wizard.
 * Does not create customers — unknown phones continue as verified guests.
 */
final class PublicBookIdentityCompleteController
{
    public function __construct(
        private readonly LeadPhoneVerification $verification,
        private readonly ResolveCustomerByContact $resolveCustomer,
    ) {}

    public function __invoke(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'phone' => ['required', 'string', 'max:32', new ValidUsPhone],
            'concern' => ['nullable', 'string', 'max:255'],
        ]);

        $bookQuery = array_filter([
            'concern' => filled($validated['concern'] ?? null) ? trim((string) $validated['concern']) : null,
        ]);

        if (! $this->verification->bookIdentityGateReady()) {
            return redirect()
                ->route('public.book', $bookQuery)
                ->withErrors(['phone' => 'Online booking is temporarily unavailable. Please call or text us to get scheduled.']);
        }

        $proofPhone = $this->verification->verifiedPhone($request->session());
        $normalized = PhoneNumber::normalize($validated['phone']);

        if ($proofPhone === null || $normalized === null || $proofPhone !== $normalized) {
            return redirect()
                ->route('public.book', $bookQuery)
                ->withErrors(['phone' => 'Verify your mobile number with the text code first.']);
        }

        $resolved = $this->resolveCustomer->resolve($validated['phone']);

        if ($resolved !== null) {
            $request->session()->forget(CustomerRecognitionProjection::SESSION_GUEST_KEY);
            Auth::guard('portal')->login($resolved['customer']);
            $request->session()->regenerate();
            PortalObservationSession::start($request);

            return redirect()->route('public.book', $bookQuery);
        }

        $request->session()->put(CustomerRecognitionProjection::SESSION_GUEST_KEY, true);

        return redirect()->route('public.book', $bookQuery);
    }
}
