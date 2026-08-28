<?php

namespace App\Ark\Growth\Events;

use App\Ark\Growth\Models\GrowthOpportunity;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

final class GrowthOpportunityPublished
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(
        public readonly GrowthOpportunity $opportunity,
    ) {}
}
