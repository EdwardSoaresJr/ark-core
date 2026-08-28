<?php

namespace App\Ark\Operations\PhoneVerification;

use App\Ark\Operations\PhoneNumber;
use App\Ark\Operations\Settings\ShopIntegrationCredentials;
use App\Ark\Operations\Settings\ShopSettings;
use Illuminate\Contracts\Session\Session;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Phone Verification Authority — proves phone possession only.
 *
 * Does not create customers, log users in, or schedule. Callers decide what
 * happens after a verified session exists. Must never know why verification was requested.
 */
final class PhoneVerificationAuthority
{
    public const SESSION_KEY = 'verified_phone_session';

    public function __construct(
        private readonly PhoneVerificationNotification $notification,
        private readonly ShopIntegrationCredentials $credentials,
    ) {}

    /**
     * True when ARK can deliver an OTP SMS (shop Twilio + inbound SMS number).
     */
    public function ready(): bool
    {
        if (! $this->credentials->twilioConfigured()) {
            return false;
        }

        return PhoneNumber::toE164(ShopSettings::current()->telephony_inbound_number) !== null;
    }

    /**
     * Issue a new OTP to the phone. Rate-limited. Never returns the plaintext code.
     *
     * @throws PhoneVerificationException
     */
    public function issue(string $phone, ?string $createdIp = null, ?string $userAgent = null): void
    {
        if (! $this->ready()) {
            throw new PhoneVerificationException('Phone verification is not available right now.');
        }

        $phoneE164 = PhoneNumber::toE164($phone);
        $normalized = PhoneNumber::normalize($phone);

        if ($phoneE164 === null || $normalized === null) {
            throw new PhoneVerificationException('Enter a valid mobile phone number.');
        }

        $this->assertCanSend($phoneE164, $createdIp);

        $plainCode = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        $ttlMinutes = max(1, (int) config('phone_verification.code_ttl_minutes', 5));
        $maxAttempts = max(1, (int) config('phone_verification.max_attempts', 5));

        DB::transaction(function () use ($phoneE164, $plainCode, $ttlMinutes, $maxAttempts, $createdIp, $userAgent): void {
            PhoneVerification::query()
                ->where('phone_e164', $phoneE164)
                ->whereNull('verified_at')
                ->whereNull('consumed_at')
                ->where('expires_at', '>', now())
                ->update(['expires_at' => now()]);

            PhoneVerification::query()->create([
                'phone_e164' => $phoneE164,
                'code_hash' => PhoneVerification::hashCode($plainCode),
                'expires_at' => now()->addMinutes($ttlMinutes),
                'attempts_remaining' => $maxAttempts,
                'send_count' => 1,
                'created_ip' => $createdIp !== null ? substr($createdIp, 0, 45) : null,
                'user_agent' => $userAgent !== null ? substr($userAgent, 0, 512) : null,
            ]);
        });

        try {
            $this->notification->sendSms($phoneE164, $plainCode);
        } catch (\Throwable) {
            throw new PhoneVerificationException('We could not send a verification code right now. Try again shortly.');
        }
    }

    /**
     * Check OTP. On success, writes a short-lived verified session. Does not log anyone in.
     *
     * @throws PhoneVerificationException
     */
    public function verify(Session $session, string $phone, string $code): bool
    {
        $phoneE164 = PhoneNumber::toE164($phone);
        $normalized = PhoneNumber::normalize($phone);

        if ($phoneE164 === null || $normalized === null) {
            throw new PhoneVerificationException('Enter a valid mobile phone number.');
        }

        $record = PhoneVerification::query()
            ->where('phone_e164', $phoneE164)
            ->whereNull('verified_at')
            ->whereNull('consumed_at')
            ->where('expires_at', '>', now())
            ->where('attempts_remaining', '>', 0)
            ->orderByDesc('id')
            ->first();

        if ($record === null) {
            throw new PhoneVerificationException('Request a new code, then try again.');
        }

        $plainCode = trim($code);

        if (! preg_match('/^\d{4,10}$/', $plainCode) || ! hash_equals($record->code_hash, PhoneVerification::hashCode($plainCode))) {
            $remaining = max(0, (int) $record->attempts_remaining - 1);
            $record->forceFill([
                'attempts_remaining' => $remaining,
                'expires_at' => $remaining === 0 ? now() : $record->expires_at,
            ])->save();

            if ($remaining === 0) {
                throw new PhoneVerificationException('Too many incorrect codes. Request a new code.');
            }

            throw new PhoneVerificationException('That code did not work. Try again or request a new code.');
        }

        $record->forceFill([
            'verified_at' => now(),
            'attempts_remaining' => 0,
        ])->save();

        // Session stores shop-normalized phone (10-digit US) for booking / lead consumers.
        $session->put(self::SESSION_KEY, [
            'phone' => $normalized,
            'verified_at' => now()->timestamp,
            'verification_id' => $record->id,
        ]);

        return true;
    }

    /**
     * Normalized phone from a still-valid verified session, or null.
     */
    public function verifiedPhone(Session $session): ?string
    {
        $proof = $session->get(self::SESSION_KEY);

        if (! is_array($proof)) {
            return null;
        }

        $phone = isset($proof['phone']) ? PhoneNumber::normalize((string) $proof['phone']) : null;

        if ($phone === null || ($proof['phone'] ?? null) !== $phone) {
            return null;
        }

        $verifiedAt = (int) ($proof['verified_at'] ?? 0);
        $ttlMinutes = max(1, (int) config('phone_verification.session_ttl_minutes', 30));

        if ($verifiedAt <= 0 || Carbon::createFromTimestamp($verifiedAt)->lte(now()->subMinutes($ttlMinutes))) {
            return null;
        }

        return $phone;
    }

    /**
     * One-time consume of the verified session for a matching phone.
     */
    public function consumeVerifiedSession(Session $session, string $phone): bool
    {
        $normalized = PhoneNumber::normalize($phone);
        $proofPhone = $this->verifiedPhone($session);

        if ($normalized === null || $proofPhone === null || $proofPhone !== $normalized) {
            return false;
        }

        $verificationId = (int) (is_array($session->get(self::SESSION_KEY))
            ? ($session->get(self::SESSION_KEY)['verification_id'] ?? 0)
            : 0);

        if ($verificationId > 0) {
            PhoneVerification::query()
                ->where('id', $verificationId)
                ->whereNull('consumed_at')
                ->update(['consumed_at' => now()]);
        }

        $session->forget(self::SESSION_KEY);

        return true;
    }

    /**
     * @throws PhoneVerificationException
     */
    private function assertCanSend(string $phoneE164, ?string $createdIp): void
    {
        $cooldown = max(1, (int) config('phone_verification.send_cooldown_seconds', 30));
        $maxPhoneHour = max(1, (int) config('phone_verification.max_sends_per_phone_per_hour', 5));
        $maxIpHour = max(1, (int) config('phone_verification.max_requests_per_ip_per_hour', 10));

        $latest = PhoneVerification::query()
            ->where('phone_e164', $phoneE164)
            ->orderByDesc('id')
            ->first();

        if ($latest !== null && $latest->created_at instanceof Carbon && $latest->created_at->gt(now()->subSeconds($cooldown))) {
            throw new PhoneVerificationException('Wait a moment before requesting another code.');
        }

        $phoneSends = PhoneVerification::query()
            ->where('phone_e164', $phoneE164)
            ->where('created_at', '>=', now()->subHour())
            ->count();

        if ($phoneSends >= $maxPhoneHour) {
            throw new PhoneVerificationException('Too many codes sent to this number. Try again later.');
        }

        if (filled($createdIp)) {
            $ipSends = PhoneVerification::query()
                ->where('created_ip', $createdIp)
                ->where('created_at', '>=', now()->subHour())
                ->count();

            if ($ipSends >= $maxIpHour) {
                throw new PhoneVerificationException('Too many verification requests. Try again later.');
            }
        }
    }
}
