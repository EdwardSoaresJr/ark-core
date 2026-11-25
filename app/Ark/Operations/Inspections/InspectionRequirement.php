<?php

namespace App\Ark\Operations\Inspections;

/**
 * Shop / RO inspection lifecycle obligation.
 * Distinct from template assignment and coverage posture.
 */
enum InspectionRequirement: string
{
    case Optional = 'optional';
    case Recommended = 'recommended';
    case Required = 'required';

    public function label(): string
    {
        return match ($this) {
            self::Optional => 'Optional',
            self::Recommended => 'Recommended',
            self::Required => 'Required',
        };
    }

    /** Owner-facing explanation for Shop Settings. */
    public function description(): string
    {
        return match ($this) {
            self::Optional => 'Technicians may inspect when useful. Incomplete or skipped inspections never block completing, invoicing, or closing a job.',
            self::Recommended => 'ARK reminds the shop when an inspection is unfinished. Incomplete inspections still never block completing or closing a job.',
            self::Required => 'An inspection must be finished (or an authorized manager must waive it with a reason) before Completed, Ready for Pickup, or paid close.',
        };
    }

    /** Incomplete inspection may block finish / pickup / paid close. */
    public function blocksLifecycleOnIncomplete(): bool
    {
        return $this === self::Required;
    }
}
