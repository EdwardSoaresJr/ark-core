<?php

namespace App\Ark\Operations\Telephony;

/**
 * Stock shops keep call records and skip analysis.
 * Hosted analysis is bound over this class when Dragon is entitled.
 */
class CallSessionAnalyzer
{
    public const MIN_DURATION_SECONDS = 8;

    public static function enabled(): bool
    {
        return app(self::class)->available();
    }

    public function available(): bool
    {
        return false;
    }

    public function queueIfEligible(CallSession $callSession): void {}

    public function analyze(CallSession $callSession): void
    {
        $callSession->forceFill([
            'analysis_status' => CallSessionAnalysisStatus::Skipped,
            'analysis_error' => 'Model provider is not configured.',
        ])->saveQuietly();
    }
}
