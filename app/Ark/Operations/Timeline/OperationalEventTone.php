<?php

namespace App\Ark\Operations\Timeline;

enum OperationalEventTone: string
{
    case Neutral = 'neutral';
    case Customer = 'customer';
    case Shop = 'shop';
    case Internal = 'internal';
    case System = 'system';
    case Warning = 'warning';
    case Success = 'success';
}
