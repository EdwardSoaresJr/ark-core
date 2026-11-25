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

    /** Incomplete inspection may block finish / pickup / paid close. */
    public function blocksLifecycleOnIncomplete(): bool
    {
        return $this === self::Required;
    }
}
