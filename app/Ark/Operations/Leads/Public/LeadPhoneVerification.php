<?php

namespace App\Ark\Operations\Leads\Public;

use App\Ark\Operations\PhoneNumber;
use App\Ark\Operations\PhoneVerification\PhoneVerification;
use App\Ark\Operations\PhoneVerification\PhoneVerificationAuthority;
use App\Ark\Operations\PhoneVerification\PhoneVerificationException;
use Illuminate\Contracts\Session\Session;

final class LeadPhoneVerification
{
    public function __construct(
        private readonly PhoneVerificationAuthority $authority,
    ) {}

    public function required(): bool
    {
        return $this->authority->ready();
    }

    public function bookIdentityGateReady(): bool
    {
        return $this->authority->ready();
    }

    public function verifiedPhone(Session $session): ?string
    {
        return $this->authority->verifiedPhone($session);
    }

    public function consumeBookProof(Session $session, string $phone): bool
    {
        return $this->authority->consumeVerifiedSession($session, $phone);
    }

    public function proofKey(): string
    {
        return PhoneVerificationAuthority::SESSION_KEY;
    }

    /**
     * A proof copied into a form can be posted twice; only an unused verification counts.
     */
    public function proofUnused(Session $session): bool
    {
        $id = (int) ($session->get(PhoneVerificationAuthority::SESSION_KEY)['verification_id'] ?? 0);

        return $id > 0 && PhoneVerification::query()->whereKey($id)->whereNull('consumed_at')->exists();
    }

    /**
     * Sends a code. Null on success, otherwise the reason to show the customer.
     */
    public function send(string $phone, ?string $ip = null, ?string $userAgent = null): ?string
    {
        try {
            $this->authority->issue($phone, $ip, $userAgent);
        } catch (PhoneVerificationException $exception) {
            return $exception->getMessage();
        } catch (\Throwable $exception) {
            report($exception);

            return 'We could not send a code right now. Call the shop and we will book you in.';
        }

        return null;
    }

    /**
     * Checks a code into the given session. Null on success, otherwise the reason.
     */
    public function check(Session $session, string $phone, string $code): ?string
    {
        try {
            return $this->authority->verify($session, $phone, $code) ? null : 'That code did not match.';
        } catch (PhoneVerificationException $exception) {
            return $exception->getMessage();
        }
    }

    public function markVerified(Session $session, string $phone): void
    {
        $normalized = PhoneNumber::normalize($phone);

        if ($normalized === null) {
            return;
        }

        $session->put(PhoneVerificationAuthority::SESSION_KEY, [
            'phone' => $normalized,
            'verified_at' => now()->timestamp,
        ]);
    }
}
