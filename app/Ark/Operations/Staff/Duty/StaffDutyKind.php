<?php

namespace App\Ark\Operations\Staff\Duty;

enum StaffDutyKind: string
{
    case SignIn = 'sign_in';
    case Manual = 'manual';
    case Scheduled = 'scheduled';
    case AfterHoursCoverage = 'after_hours_coverage';

    /**
     * After-hours coverage is authorized past closing time; every other kind ends when the shop closes.
     */
    public function endsAtShopClose(): bool
    {
        return $this !== self::AfterHoursCoverage;
    }
}
