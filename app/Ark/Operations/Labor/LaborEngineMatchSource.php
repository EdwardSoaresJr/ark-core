<?php

namespace App\Ark\Operations\Labor;

enum LaborEngineMatchSource: string
{
    case VehicleRecord = 'vehicle_record';
    case AdvisorSelected = 'advisor_selected';
    case AssumedDefault = 'assumed_default';
}
