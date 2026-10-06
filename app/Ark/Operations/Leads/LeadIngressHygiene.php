<?php

namespace App\Ark\Operations\Leads;

/**
 * Public contact-form checks.
 *
 * A submission with no server-issued render stamp is spam. A stamp younger
 * than a few seconds is sent back so the person can try again.
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

        if ($ingress->formRenderedAt === null) {
            $signals[] = 'missing_form';
        }

        $duration = $ingress->submitDurationSeconds();

        if ($duration !== null && $duration < self::MIN_SUBMIT_SECONDS) {
            $signals[] = 'too_fast';
        }

        return $signals;
    }

    public function autoSpamState(array $signals): ?LeadState
    {
        if (in_array('missing_form', $signals, true) || in_array('too_fast', $signals, true)) {
            return LeadState::Spam;
        }

        return null;
    }
}
