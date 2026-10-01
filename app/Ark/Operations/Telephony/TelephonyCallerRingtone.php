<?php

namespace App\Ark\Operations\Telephony;

/**
 * Caller-facing ring audio while advisors are being connected.
 *
 * Twilio Dial ringTone accepts country codes (us, uk, …) or a hosted audio URL
 * for future shop promos / hold messages before bridge.
 */
final class TelephonyCallerRingtone
{
    public const AUDIO_MODE_STANDARD = 'standard';

    public const AUDIO_MODE_PROMO = 'promo';

    public function __construct(
        private readonly TelephonyCallFlowSettings $flow,
    ) {}

    public static function forCurrentShop(): self
    {
        return new self(TelephonyCallFlowSettings::fromShopSettings());
    }

    public static function isPromoUrl(string $tone): bool
    {
        return preg_match('/^https?:\/\//i', trim($tone)) === 1;
    }

    public static function normalizeFromSettingsInput(?string $mode, ?string $promoUrl, ?string $existing = null): string
    {
        if ($mode === null) {
            $fallback = trim((string) $existing);

            return $fallback !== '' ? $fallback : 'us';
        }

        if ($mode === self::AUDIO_MODE_PROMO) {
            $url = trim((string) $promoUrl);

            return $url !== '' ? $url : 'us';
        }

        return 'us';
    }

    public function dialAttribute(): string
    {
        return ' ringTone="'.htmlspecialchars($this->flow->callerRingTone(), ENT_XML1).'"';
    }

    /** @param int $loop 0 = repeat until the next TwiML verb or redirect */
    public function playVerb(int $loop = 0): string
    {
        return '<Play loop="'.$loop.'">'
            .htmlspecialchars($this->playUrl(), ENT_XML1)
            .'</Play>';
    }

    public function playUrl(): string
    {
        $tone = $this->flow->callerRingTone();

        if (preg_match('/^https?:\/\//i', $tone) === 1) {
            return $tone;
        }

        return asset('audio/us-ringback.wav');
    }
}
