<?php

namespace App\Ark\Operations\EmailVerification;

use Illuminate\Contracts\Session\Session;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Email Verification Authority — proves email possession only.
 *
 * Does not create customers or log users in. Callers decide what happens after
 * a verified session exists. Portal sign-in stays on PortalAccessChallenge.
 */
final class EmailVerificationAuthority
{
    public const SESSION_KEY = 'verified_email_session';

    public function __construct(
        private readonly EmailVerificationNotification $notification,
    ) {}

    public function ready(): bool
    {
        return filled(config('mail.from.address'));
    }

    /**
     * @throws EmailVerificationException
     */
    public function issue(string $email, ?string $createdIp = null, ?string $userAgent = null): void
    {
        if (! $this->ready()) {
            throw new EmailVerificationException('Email verification is not available right now.');
        }

        $normalized = EmailVerification::normalizeEmail($email);

        if ($normalized === null) {
            throw new EmailVerificationException('Enter a valid email address.');
        }

        $this->assertCanSend($normalized, $createdIp);

        $plainCode = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        $ttlMinutes = max(1, (int) config('email_verification.code_ttl_minutes', 5));
        $maxAttempts = max(1, (int) config('email_verification.max_attempts', 5));

        DB::transaction(function () use ($normalized, $plainCode, $ttlMinutes, $maxAttempts, $createdIp, $userAgent): void {
            EmailVerification::query()
                ->where('email', $normalized)
                ->whereNull('verified_at')
                ->whereNull('consumed_at')
                ->where('expires_at', '>', now())
                ->update(['expires_at' => now()]);

            EmailVerification::query()->create([
                'email' => $normalized,
                'code_hash' => EmailVerification::hashCode($plainCode),
                'expires_at' => now()->addMinutes($ttlMinutes),
                'attempts_remaining' => $maxAttempts,
                'send_count' => 1,
                'created_ip' => $createdIp !== null ? substr($createdIp, 0, 45) : null,
                'user_agent' => $userAgent !== null ? substr($userAgent, 0, 512) : null,
            ]);
        });

        $this->notification->send($normalized, $plainCode);
    }

    /**
     * @throws EmailVerificationException
     */
    public function verify(Session $session, string $email, string $code): bool
    {
        $normalized = EmailVerification::normalizeEmail($email);

        if ($normalized === null) {
            throw new EmailVerificationException('Enter a valid email address.');
        }

        $record = EmailVerification::query()
            ->where('email', $normalized)
            ->whereNull('verified_at')
            ->whereNull('consumed_at')
            ->where('expires_at', '>', now())
            ->where('attempts_remaining', '>', 0)
            ->orderByDesc('id')
            ->first();

        if ($record === null) {
            throw new EmailVerificationException('Request a new code, then try again.');
        }

        $plainCode = trim($code);

        if (! preg_match('/^\d{4,10}$/', $plainCode) || ! hash_equals($record->code_hash, EmailVerification::hashCode($plainCode))) {
            $remaining = max(0, (int) $record->attempts_remaining - 1);
            $record->forceFill([
                'attempts_remaining' => $remaining,
                'expires_at' => $remaining === 0 ? now() : $record->expires_at,
            ])->save();

            if ($remaining === 0) {
                throw new EmailVerificationException('Too many incorrect codes. Request a new code.');
            }

            throw new EmailVerificationException('That code did not work. Try again or request a new code.');
        }

        $record->forceFill([
            'verified_at' => now(),
            'attempts_remaining' => 0,
        ])->save();

        $session->put(self::SESSION_KEY, [
            'email' => $normalized,
            'verified_at' => now()->timestamp,
            'verification_id' => $record->id,
        ]);

        return true;
    }

    public function verifiedEmail(Session $session): ?string
    {
        $proof = $session->get(self::SESSION_KEY);

        if (! is_array($proof)) {
            return null;
        }

        $email = isset($proof['email']) ? EmailVerification::normalizeEmail((string) $proof['email']) : null;

        if ($email === null || ($proof['email'] ?? null) !== $email) {
            return null;
        }

        $verifiedAt = (int) ($proof['verified_at'] ?? 0);
        $ttlMinutes = max(1, (int) config('email_verification.session_ttl_minutes', 30));

        if ($verifiedAt <= 0 || Carbon::createFromTimestamp($verifiedAt)->lte(now()->subMinutes($ttlMinutes))) {
            return null;
        }

        return $email;
    }

    public function consumeVerifiedSession(Session $session, ?string $email = null): bool
    {
        $proofEmail = $this->verifiedEmail($session);

        if ($proofEmail === null) {
            return false;
        }

        if ($email !== null) {
            $normalized = EmailVerification::normalizeEmail($email);

            if ($normalized === null || $normalized !== $proofEmail) {
                return false;
            }
        }

        $verificationId = (int) (is_array($session->get(self::SESSION_KEY))
            ? ($session->get(self::SESSION_KEY)['verification_id'] ?? 0)
            : 0);

        if ($verificationId > 0) {
            EmailVerification::query()
                ->where('id', $verificationId)
                ->whereNull('consumed_at')
                ->update(['consumed_at' => now()]);
        }

        $session->forget(self::SESSION_KEY);

        return true;
    }

    /**
     * @throws EmailVerificationException
     */
    private function assertCanSend(string $email, ?string $createdIp): void
    {
        $cooldown = max(1, (int) config('email_verification.send_cooldown_seconds', 30));
        $maxEmailHour = max(1, (int) config('email_verification.max_sends_per_email_per_hour', 5));
        $maxIpHour = max(1, (int) config('email_verification.max_requests_per_ip_per_hour', 10));

        $latest = EmailVerification::query()
            ->where('email', $email)
            ->orderByDesc('id')
            ->first();

        if ($latest !== null && $latest->created_at instanceof Carbon && $latest->created_at->gt(now()->subSeconds($cooldown))) {
            throw new EmailVerificationException('Wait a moment before requesting another code.');
        }

        $emailSends = EmailVerification::query()
            ->where('email', $email)
            ->where('created_at', '>=', now()->subHour())
            ->count();

        if ($emailSends >= $maxEmailHour) {
            throw new EmailVerificationException('Too many codes sent to this email. Try again later.');
        }

        if (filled($createdIp)) {
            $ipSends = EmailVerification::query()
                ->where('created_ip', $createdIp)
                ->where('created_at', '>=', now()->subHour())
                ->count();

            if ($ipSends >= $maxIpHour) {
                throw new EmailVerificationException('Too many verification requests. Try again later.');
            }
        }
    }
}
