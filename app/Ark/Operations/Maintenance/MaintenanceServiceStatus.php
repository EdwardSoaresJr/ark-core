<?php

namespace App\Ark\Operations\Maintenance;

enum MaintenanceServiceStatus: string
{
    case Active = 'active';
    case Confirmed = 'confirmed';
    case Cancelled = 'cancelled';
}
