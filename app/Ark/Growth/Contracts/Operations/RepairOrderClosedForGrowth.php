<?php

namespace App\Ark\Growth\Contracts\Operations;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Cross-product contract: Operations dispatches when a repair order closes with revenue.
 * Growth listens — no direct Operations imports from Growth controllers.
 */
final class RepairOrderClosedForGrowth
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(
        public readonly RepairOrderClosedPayload $payload,
    ) {}
}
