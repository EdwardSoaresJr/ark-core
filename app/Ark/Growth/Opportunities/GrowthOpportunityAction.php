<?php

namespace App\Ark\Growth\Opportunities;

enum GrowthOpportunityAction: string
{
    case Create = 'create';
    case Improve = 'improve';
    case Promote = 'promote';
    case Link = 'link';

    public function label(): string
    {
        return match ($this) {
            self::Create => 'Create page',
            self::Improve => 'Improve page',
            self::Promote => 'Promote page',
            self::Link => 'Add internal links',
        };
    }
}
