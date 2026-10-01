<?php

namespace App\Ark\Operations\Communications;

enum CommunicationsVisitSource: string
{
    case Linked = 'linked';
    case Inferred = 'inferred';
    case Multiple = 'multiple';
    case None = 'none';

    public function label(): string
    {
        return match ($this) {
            self::Linked => 'Linked visit',
            self::Inferred => 'Inferred current visit',
            self::Multiple => 'Multiple open visits',
            self::None => 'No current visit',
        };
    }
}
