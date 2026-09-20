<?php

use App\Ark\Runtime\Authorization\ArkCapability;
use App\Ark\Runtime\Authorization\ArkRole;

return [
    'roles' => [
        ArkRole::Admin->value => [
            ArkCapability::ProductionAccess->value,
            ArkCapability::OperationsAccess->value,
            ArkCapability::CustomersManage->value,
            ArkCapability::VehiclesManage->value,
            ArkCapability::RepairOrdersView->value,
            ArkCapability::RepairOrdersManage->value,
            ArkCapability::RepairOrdersCloseout->value,
            ArkCapability::RepairOrdersDestructive->value,
            ArkCapability::EstimatesView->value,
            ArkCapability::EstimatesManage->value,
            ArkCapability::EstimateDocumentsManage->value,
            ArkCapability::PricingOverride->value,
            ArkCapability::ProcurementCancel->value,
            ArkCapability::FinancialView->value,
            ArkCapability::FinancialManage->value,
            ArkCapability::SettingsManage->value,
            ArkCapability::StaffManage->value,
            ArkCapability::CommunicationsInternalView->value,
            ArkCapability::CommunicationsInternalManage->value,
            ArkCapability::AttentionView->value,
            ArkCapability::AttentionManage->value,
        ],

        ArkRole::Advisor->value => [
            ArkCapability::ProductionAccess->value,
            ArkCapability::OperationsAccess->value,
            ArkCapability::CustomersManage->value,
            ArkCapability::VehiclesManage->value,
            ArkCapability::RepairOrdersView->value,
            ArkCapability::RepairOrdersManage->value,
            ArkCapability::RepairOrdersCloseout->value,
            ArkCapability::RepairOrdersDestructive->value,
            ArkCapability::EstimatesView->value,
            ArkCapability::EstimatesManage->value,
            ArkCapability::EstimateDocumentsManage->value,
            ArkCapability::PricingOverride->value,
            ArkCapability::ProcurementCancel->value,
            ArkCapability::FinancialView->value,
            ArkCapability::CommunicationsInternalView->value,
            ArkCapability::CommunicationsInternalManage->value,
            ArkCapability::AttentionView->value,
            ArkCapability::AttentionManage->value,
        ],

        ArkRole::Technician->value => [
            ArkCapability::ProductionAccess->value,
            ArkCapability::RepairOrdersView->value,
            ArkCapability::RepairOrdersLifecycle->value,
            ArkCapability::CommunicationsInternalView->value,
        ],

        ArkRole::Customer->value => [
            ArkCapability::PortalAccess->value,
        ],
    ],
];
