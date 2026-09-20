<?php

namespace App\Ark\Operations\Today\Surface;

enum TodayLens: string
{
    case Owner = 'owner';
    case Advisor = 'advisor';
    case Technician = 'technician';
}
