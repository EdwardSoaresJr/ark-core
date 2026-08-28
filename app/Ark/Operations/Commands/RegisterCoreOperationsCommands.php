<?php

namespace App\Ark\Operations\Commands;

use App\Ark\Operations\RepairOrders\RepairOrder;
use App\Ark\Runtime\Authorization\ArkCapability;
use Illuminate\Support\Facades\Route;

/**
 * Core commands — modules may register more via the singleton registry.
 */
final class RegisterCoreOperationsCommands
{
    public function __invoke(OperationsCommandRegistry $registry): void
    {
        $repairOrder = $this->currentRepairOrder();

        $this->registerNavigate($registry);
        $this->registerCreate($registry);
        $this->registerSearch($registry);
        $this->registerOperations($registry, $repairOrder);
    }

    private function registerNavigate(OperationsCommandRegistry $registry): void
    {
        $registry->register(new OperationsCommand(
            id: 'nav.workboard',
            title: "Today's Workboard",
            group: 'Navigate',
            keywords: ['workboard', 'board', 'today', 'home', 'attention'],
            permission: ArkCapability::OperationsAccess->value,
            url: route('operations.index'),
        ));

        $registry->register(new OperationsCommand(
            id: 'nav.repair-orders',
            title: 'Repair Orders',
            group: 'Navigate',
            keywords: ['ro', 'repair', 'orders', 'queue'],
            permission: ArkCapability::RepairOrdersView->value,
            url: route('operations.repair-orders.index'),
        ));

        $registry->register(new OperationsCommand(
            id: 'nav.customers',
            title: 'Customers',
            group: 'Navigate',
            keywords: ['customer', 'people', 'clients'],
            permission: ArkCapability::CustomersManage->value,
            url: route('operations.customers.search'),
        ));

        $registry->register(new OperationsCommand(
            id: 'nav.vehicles',
            title: 'Vehicles',
            group: 'Navigate',
            keywords: ['vehicle', 'car', 'vin', 'plate'],
            permission: ArkCapability::VehiclesManage->value,
            url: route('operations.vehicles.search'),
        ));

        $registry->register(new OperationsCommand(
            id: 'nav.communications',
            title: 'Communications',
            group: 'Navigate',
            keywords: ['comms', 'inbox', 'messages', 'sms', 'calls'],
            permission: ArkCapability::OperationsAccess->value,
            url: route('operations.communications.inbox'),
        ));

        $registry->register(new OperationsCommand(
            id: 'nav.reports',
            title: 'Reports',
            group: 'Navigate',
            keywords: ['reports', 'end of day', 'operational'],
            permission: ArkCapability::OperationsAccess->value,
            url: route('operations.reports.index'),
        ));

        if (Route::has('website.performance')) {
            $registry->register(new OperationsCommand(
                id: 'nav.website',
                title: 'Website',
                group: 'Navigate',
                keywords: ['website', 'public', 'growth', 'page media'],
                permission: ArkCapability::SettingsManage->value,
                url: route('website.performance'),
            ));
        }

        $registry->register(new OperationsCommand(
            id: 'nav.settings',
            title: 'Settings',
            group: 'Navigate',
            keywords: ['settings', 'shop', 'config'],
            permission: ArkCapability::SettingsManage->value,
            url: route('operations.settings.shop.edit'),
        ));
    }

    private function registerCreate(OperationsCommandRegistry $registry): void
    {
        $registry->register(new OperationsCommand(
            id: 'create.repair-order',
            title: 'New Repair Order',
            group: 'Create',
            keywords: ['create', 'new', 'ro', 'repair', 'intake', 'check in'],
            permission: ArkCapability::RepairOrdersManage->value,
            url: route('operations.intake.create'),
        ));

        $registry->register(new OperationsCommand(
            id: 'create.customer',
            title: 'New Customer',
            group: 'Create',
            keywords: ['create', 'new', 'customer', 'intake'],
            permission: ArkCapability::CustomersManage->value,
            url: route('operations.intake.create'),
        ));

        $registry->register(new OperationsCommand(
            id: 'create.vehicle',
            title: 'New Vehicle',
            group: 'Create',
            keywords: ['create', 'new', 'vehicle', 'intake'],
            permission: ArkCapability::VehiclesManage->value,
            url: route('operations.intake.create'),
        ));

        $registry->register(new OperationsCommand(
            id: 'create.appointment',
            title: 'New Appointment',
            group: 'Create',
            keywords: ['create', 'new', 'appointment', 'schedule'],
            permission: ArkCapability::OperationsAccess->value,
            url: route('operations.appointments.create'),
        ));

        $registry->register(new OperationsCommand(
            id: 'create.message',
            title: 'New Message',
            group: 'Create',
            keywords: ['create', 'new', 'message', 'text', 'sms', 'compose'],
            permission: ArkCapability::OperationsAccess->value,
            url: route('operations.communications.inbox'),
        ));
    }

    private function registerSearch(OperationsCommandRegistry $registry): void
    {
        $registry->register(new OperationsCommand(
            id: 'search.customer',
            title: 'Search Customers',
            group: 'Search',
            keywords: ['search', 'customer', 'phone', 'name'],
            permission: ArkCapability::CustomersManage->value,
            url: route('operations.customers.search'),
        ));

        $registry->register(new OperationsCommand(
            id: 'search.vehicle',
            title: 'Search Vehicles',
            group: 'Search',
            keywords: ['search', 'vehicle', 'vin', 'plate', 'license'],
            permission: ArkCapability::VehiclesManage->value,
            url: route('operations.vehicles.search'),
        ));

        $registry->register(new OperationsCommand(
            id: 'search.vin',
            title: 'Search by VIN',
            group: 'Search',
            keywords: ['search', 'vin', 'vehicle'],
            permission: ArkCapability::VehiclesManage->value,
            url: route('operations.vehicles.search'),
        ));

        $registry->register(new OperationsCommand(
            id: 'search.plate',
            title: 'Search by License Plate',
            group: 'Search',
            keywords: ['search', 'plate', 'license', 'tag'],
            permission: ArkCapability::VehiclesManage->value,
            url: route('operations.vehicles.search'),
        ));

        $registry->register(new OperationsCommand(
            id: 'search.phone',
            title: 'Search by Phone',
            group: 'Search',
            keywords: ['search', 'phone', 'customer', 'number'],
            permission: ArkCapability::CustomersManage->value,
            url: route('operations.customers.search'),
        ));

        $registry->register(new OperationsCommand(
            id: 'search.repair-order',
            title: 'Search Repair Orders',
            group: 'Search',
            keywords: ['search', 'ro', 'repair', 'order', 'number'],
            permission: ArkCapability::RepairOrdersView->value,
            url: route('operations.repair-orders.index'),
        ));

        $registry->register(new OperationsCommand(
            id: 'search.estimate',
            title: 'Search Estimates',
            group: 'Search',
            keywords: ['search', 'estimate'],
            permission: ArkCapability::RepairOrdersView->value,
            url: route('operations.repair-orders.index'),
        ));

        $registry->register(new OperationsCommand(
            id: 'search.invoice',
            title: 'Search Invoices',
            group: 'Search',
            keywords: ['search', 'invoice'],
            permission: ArkCapability::RepairOrdersView->value,
            url: route('operations.repair-orders.index'),
        ));
    }

    private function registerOperations(OperationsCommandRegistry $registry, ?RepairOrder $repairOrder): void
    {
        $noRo = 'Open a repair order first.';

        $registry->register(new OperationsCommand(
            id: 'ops.print-key-tag',
            title: 'Print Key Tag',
            group: 'Operations',
            keywords: ['print', 'key', 'tag'],
            permission: ArkCapability::RepairOrdersView->value,
            url: $repairOrder !== null
                ? route('operations.repair-orders.print-key-tag', $repairOrder)
                : null,
            disabledReason: $repairOrder === null ? $noRo : null,
        ));

        $registry->register(new OperationsCommand(
            id: 'ops.print-oil-sticker',
            title: 'Print Oil Sticker',
            group: 'Operations',
            keywords: ['print', 'oil', 'sticker', 'change'],
            permission: ArkCapability::RepairOrdersView->value,
            url: $repairOrder !== null
                ? route('operations.repair-orders.print-oil-change-sticker', $repairOrder)
                : null,
            disabledReason: $repairOrder === null ? $noRo : null,
        ));

        if (Route::has('operations.repair-orders.sheets.tech.pdf')) {
            $registry->register(new OperationsCommand(
                id: 'ops.print-service-tag',
                title: 'Print Service Tag',
                group: 'Operations',
                keywords: ['print', 'service', 'tag', 'tech', 'sheet'],
                permission: ArkCapability::RepairOrdersView->value,
                url: $repairOrder !== null
                    ? route('operations.repair-orders.sheets.tech.pdf', $repairOrder)
                    : null,
                disabledReason: $repairOrder === null ? $noRo : null,
            ));
        }

        $registry->register(new OperationsCommand(
            id: 'ops.take-payment',
            title: 'Take Payment',
            group: 'Operations',
            keywords: ['payment', 'pay', 'square', 'collect', 'balance'],
            permission: ArkCapability::RepairOrdersCloseout->value,
            url: $repairOrder !== null
                ? route('operations.repair-orders.show', $repairOrder).'#estimate-builder-rail'
                : null,
            disabledReason: $repairOrder === null ? $noRo : null,
        ));

        $registry->register(new OperationsCommand(
            id: 'ops.record-deposit',
            title: 'Record Deposit',
            group: 'Operations',
            keywords: ['deposit', 'payment', 'record'],
            permission: ArkCapability::RepairOrdersCloseout->value,
            url: $repairOrder !== null
                ? route('operations.repair-orders.show', $repairOrder).'#estimate-builder-rail'
                : null,
            disabledReason: $repairOrder === null ? $noRo : null,
        ));

        $registry->register(new OperationsCommand(
            id: 'ops.open-active-ro',
            title: 'Open Active RO',
            group: 'Operations',
            keywords: ['active', 'ro', 'open', 'workboard', 'repair'],
            permission: ArkCapability::RepairOrdersView->value,
            url: $repairOrder !== null
                ? route('operations.repair-orders.show', $repairOrder)
                : route('operations.index'),
        ));
    }

    private function currentRepairOrder(): ?RepairOrder
    {
        $repairOrder = request()->route('repairOrder');

        return $repairOrder instanceof RepairOrder ? $repairOrder : null;
    }
}
