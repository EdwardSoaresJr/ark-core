<?php

namespace App\Ark\Operations\Inspections;

use App\Ark\Operations\RepairOrders\RepairOrder;

final class ResolveRequiredInspectionTemplate
{
    public static function for(RepairOrder $repairOrder): ?InspectionTemplate
    {
        DefaultInspectionTemplateCatalog::seedIfMissing();

        $repairOrder->loadMissing('requiredInspectionTemplate');

        $assigned = $repairOrder->requiredInspectionTemplate;
        if ($assigned instanceof InspectionTemplate && $assigned->enabled && ! $assigned->isArchived()) {
            return $assigned;
        }

        return DefaultInspectionTemplateCatalog::standardTemplate();
    }
}
