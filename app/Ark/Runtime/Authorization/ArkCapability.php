<?php

namespace App\Ark\Runtime\Authorization;

enum ArkCapability: string
{
    case ProductionAccess = 'production.access';
    case OperationsAccess = 'operations.access';
    case CustomersManage = 'customers.manage';
    case VehiclesManage = 'vehicles.manage';
    case RepairOrdersView = 'repair_orders.view';
    case RepairOrdersManage = 'repair_orders.manage';
    case RepairOrdersLifecycle = 'repair_orders.lifecycle';
    case RepairOrdersCloseout = 'repair_orders.closeout';
    case RepairOrdersDestructive = 'repair_orders.destructive';
    case EstimatesView = 'estimates.view';
    case EstimatesManage = 'estimates.manage';
    case EstimateDocumentsManage = 'estimate_documents.manage';
    case PricingOverride = 'pricing.override';
    case ProcurementCancel = 'procurement.cancel';
    case FinancialView = 'financial.view';
    case FinancialManage = 'financial.manage';
    case SettingsManage = 'settings.manage';
    case StaffManage = 'staff.manage';
    case PortalAccess = 'portal.access';
    case CommunicationsInternalView = 'communications.internal.view';
    case CommunicationsInternalManage = 'communications.internal.manage';
    case AttentionView = 'attention.view';
    case AttentionManage = 'attention.manage';
}
