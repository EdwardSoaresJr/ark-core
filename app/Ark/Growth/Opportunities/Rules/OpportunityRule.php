<?php

namespace App\Ark\Growth\Opportunities\Rules;

interface OpportunityRule
{
    /**
     * @return list<\App\Ark\Growth\Opportunities\OpportunityCandidate>
     */
    public function candidates(): array;
}
