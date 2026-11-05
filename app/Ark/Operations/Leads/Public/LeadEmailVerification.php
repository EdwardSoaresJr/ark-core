<?php

namespace App\Ark\Operations\Leads\Public;

use App\Ark\Operations\EmailVerification\EmailVerification;
use App\Ark\Operations\EmailVerification\EmailVerificationAuthority;
use App\Ark\Operations\EmailVerification\EmailVerificationException;
use Illuminate\Contracts\Session\Session;

final class LeadEmailVerification
{
    public function __construct(
        private readonly EmailVerificationAuthority $authority,
    ) {}

    public function ready(): bool
    {
        return $this->authority->ready();
    }

    public function verifiedEmail(Session $session): ?string
    {
        return $this->authority->verifiedEmail($session);
    }

    public function consumeBookProof(Session $session, ?string $email = null): bool
    {
        return $this->authority->consumeVerifiedSession($session, $email);
    }

    public function proofKey(): string
    {
        return EmailVerificationAuthority::SESSION_KEY;
    }

    /**
     * A proof copied into a form can be posted twice; only an unused verification counts.
     */
    public function proofUnused(Session $session): bool
    {
        $id = (int) ($session->get(EmailVerificationAuthority::SESSION_KEY)['verification_id'] ?? 0);

        return $id > 0 && EmailVerification::query()->whereKey($id)->whereNull('consumed_at')->exists();
    }

    /**
     * Sends a code. Null on success, otherwise the reason to show the customer.
     */
    public function send(string $email, ?string $ip = null, ?string $userAgent = null): ?string
    {
        try {
            $this->authority->issue($email, $ip, $userAgent);
        } catch (EmailVerificationException $exception) {
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
    public function check(Session $session, string $email, string $code): ?string
    {
        try {
            return $this->authority->verify($session, $email, $code) ? null : 'That code did not match.';
        } catch (EmailVerificationException $exception) {
            return $exception->getMessage();
        }
    }

    public function markVerified(Session $session, string $email): void
    {
        $normalized = EmailVerification::normalizeEmail($email);

        if ($normalized === null) {
            return;
        }

        $session->put(EmailVerificationAuthority::SESSION_KEY, [
            'email' => $normalized,
            'verified_at' => now()->timestamp,
        ]);
    }
}
