<?php

namespace App\Ark\Operations\Today;

use App\Ark\Operations\Workboard\WorkboardTriageCard;

/**
 * One-line answer to "why is this RO in Needs Action?"
 */
final class AdvisorHomeAttentionReason
{
    public static function for(
        AdvisorHomeAttentionZoneKey $zoneKey,
        WorkboardTriageCard $card,
        ?AdvisorHomeCardSurface $surface,
    ): ?string {
        if ($zoneKey !== AdvisorHomeAttentionZoneKey::NeedsAction) {
            return null;
        }

        return AdvisorHomeActionableAttention::reasonFor($card, $surface);
    }
}
