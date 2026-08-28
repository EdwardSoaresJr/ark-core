<?php

namespace App\Ark\Operations\Leads;

/**
 * Observation-first spam signals for public lead ingress.
 *
 * Capture patterns before heavy enforcement. No CAPTCHA.
 */
final class LeadIngressHygiene
{
    public const MIN_SUBMIT_SECONDS = 3;

    /**
     * @return list<string>
     */
    public function signals(LeadIngressContext $ingress): array
    {
        $signals = [];
        $duration = $ingress->submitDurationSeconds();

        if ($duration !== null && $duration < self::MIN_SUBMIT_SECONDS) {
            $signals[] = 'too_fast';
        }

        return $signals;
    }

    public function autoSpamState(array $signals): ?LeadState
    {
        if (in_array('too_fast', $signals, true)) {
            return LeadState::Spam;
        }

        return null;
    }
}
