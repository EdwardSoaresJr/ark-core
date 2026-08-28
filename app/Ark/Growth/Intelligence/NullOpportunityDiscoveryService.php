<?php

namespace App\Ark\Growth\Intelligence;

use App\Ark\Growth\Intelligence\Contracts\OpportunityDiscoveryService;

final class NullOpportunityDiscoveryService implements OpportunityDiscoveryService
{
    public function discover(): array
    {
        return [];
    }
}
