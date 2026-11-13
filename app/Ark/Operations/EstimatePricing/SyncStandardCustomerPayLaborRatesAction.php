<?php

namespace App\Ark\Operations\EstimatePricing;

use Illuminate\Support\Carbon;

/**
 * Retired (#194 D2). Standard inheritance and automatic cascade are no longer used.
 * Kept as a no-op so legacy callers do not reintroduce competing rate authorities.
 */
class SyncStandardCustomerPayLaborRatesAction
{
    /**
     * @return array{
     *     updated_class_keys: list<string>,
     *     preserved_class_keys: list<string>,
     *     previous_standard_cents: int,
     *     next_standard_cents: int,
     * }
     */
    public function execute(
        int $previousStandardCents,
        int $nextStandardCents,
        ?Carbon $effectiveFrom = null,
        ?string $changeReason = null,
    ): array {
        return [
            'updated_class_keys' => [],
            'preserved_class_keys' => [],
            'previous_standard_cents' => $previousStandardCents,
            'next_standard_cents' => $nextStandardCents,
        ];
    }
}
