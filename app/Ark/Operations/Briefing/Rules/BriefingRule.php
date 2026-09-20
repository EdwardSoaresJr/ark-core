<?php

namespace App\Ark\Operations\Briefing\Rules;

use App\Ark\Operations\Briefing\BriefingContext;
use App\Ark\Operations\Briefing\BriefingItem;

interface BriefingRule
{
    public function key(): string;

    /**
     * @return list<BriefingItem>
     */
    public function items(BriefingContext $context): array;
}
