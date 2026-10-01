<?php

namespace App\Ark\Operations\Attention;

enum AdvisorNudgeResponseKind: string
{
    case Dismissed = 'dismissed';
    case Acted = 'acted';

    public function label(): string
    {
        return match ($this) {
            self::Dismissed => 'Dismissed',
            self::Acted => 'Acted',
        };
    }
}
