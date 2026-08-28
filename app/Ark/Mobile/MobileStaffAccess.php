<?php

namespace App\Ark\Mobile;

use App\Ark\Operations\Conversations\Conversation;
use App\Ark\Operations\Conversations\ConversationLink;
use App\Ark\Operations\Financial\LedgerEntryType;
use App\Ark\Operations\Financial\RepairOrderLedgerEntry;
use App\Ark\Operations\RepairOrders\RepairOrder;
use App\Ark\Operations\Vehicles\Vehicle;
use App\Ark\Operations\ShopExcellence\OwnerWorkspaceAccess;
use App\Ark\Runtime\Authorization\ArkCapability;
use App\Ark\Runtime\Authorization\ArkRole;
use App\Models\User;

final class MobileStaffAccess
{
    public function canUseMobile(User $user): bool
    {
        if (! $user->isActive()) {
            return false;
        }

        // Floor staff roles — technicians get assigned-work shell only; advisors/admins get full companion nav.
        if ($user->hasAnyRole([
            ArkRole::Admin->value,
            ArkRole::Advisor->value,
            ArkRole::Technician->value,
        ])) {
            return true;
        }

        return $user->can(ArkCapability::ProductionAccess->value)
            || $user->can(ArkCapability::OperationsAccess->value);
    }

    public function canViewCustomer(User $user): bool
    {
        return $this->canManageCustomers($user)
            || $this->canPerformIntake($user)
            || $this->canAccessShopCommunications($user);
    }

    public function canViewVehicle(User $user, Vehicle $vehicle): bool
    {
        if ($this->canViewCustomer($user)) {
            return true;
        }

        if (! $user->can(ArkCapability::RepairOrdersView->value)) {
            return false;
        }

        return RepairOrder::query()
            ->where('vehicle_id', $vehicle->id)
            ->where('assigned_technician_id', $user->id)
            ->exists();
    }

    public function canPerformIntake(User $user): bool
    {
        return $user->can(ArkCapability::RepairOrdersManage->value);
    }

    public function canManageCustomers(User $user): bool
    {
        return $user->can(ArkCapability::CustomersManage->value);
    }

    public function canManageVehicles(User $user): bool
    {
        return $user->can(ArkCapability::VehiclesManage->value);
    }

    public function canViewRepairOrder(User $user, RepairOrder $repairOrder): bool
    {
        if (! $user->can(ArkCapability::RepairOrdersView->value)) {
            return false;
        }

        if ($user->hasAnyRole([ArkRole::Admin->value, ArkRole::Advisor->value])
            && $user->can(ArkCapability::OperationsAccess->value)) {
            return true;
        }

        if ($user->hasRole(ArkRole::Technician->value)) {
            return (int) $repairOrder->assigned_technician_id === (int) $user->id;
        }

        return $user->can(ArkCapability::RepairOrdersManage->value);
    }

    public function canRecordFinding(User $user, RepairOrder $repairOrder): bool
    {
        return \App\Ark\Operations\Inspections\InspectionCaptureLinks::canRecord($user, $repairOrder);
    }

    public function canUpdateConcernProductionStatus(User $user, RepairOrder $repairOrder): bool
    {
        if (! $user->can(ArkCapability::ProductionAccess->value)
            && ! $user->can(ArkCapability::RepairOrdersManage->value)) {
            return false;
        }

        return $this->canViewRepairOrder($user, $repairOrder);
    }

    public function canSetConcernDisposition(User $user, RepairOrder $repairOrder): bool
    {
        // The estimate decision (approve / decline / defer) is an advisor/owner
        // action that mutates the estimate. Technicians own production, not
        // approvals (technician-scope doctrine).
        if (! $user->can(ArkCapability::RepairOrdersManage->value)) {
            return false;
        }

        return $this->canViewRepairOrder($user, $repairOrder);
    }

    public function canChangeRepairOrderLifecycle(User $user, RepairOrder $repairOrder): bool
    {
        // RO lifecycle (status moves, close-out) is advisor/owner authority.
        // Technicians own production status on concerns, not the repair order
        // lifecycle (technician-scope doctrine).
        if (! $user->can(ArkCapability::RepairOrdersManage->value)) {
            return false;
        }

        return $this->canViewRepairOrder($user, $repairOrder);
    }

    public function canRecordPayment(User $user, RepairOrder $repairOrder): bool
    {
        if (! $user->can(ArkCapability::RepairOrdersManage->value)) {
            return false;
        }

        if (! $this->canViewRepairOrder($user, $repairOrder)) {
            return false;
        }

        $balance = $repairOrder->balanceDue();

        return $balance->hasIssuedInvoice && $balance->balanceDueCents > 0;
    }

    public function canRecordDeposit(User $user, RepairOrder $repairOrder): bool
    {
        if (! $user->can(ArkCapability::RepairOrdersManage->value)) {
            return false;
        }

        if (! $this->canViewRepairOrder($user, $repairOrder)) {
            return false;
        }

        $balance = $repairOrder->balanceDue();

        return ! $repairOrder->isTerminal() && ! $balance->hasIssuedInvoice;
    }

    public function canRecordRefund(User $user, RepairOrder $repairOrder): bool
    {
        if (! $user->can(ArkCapability::RepairOrdersManage->value)) {
            return false;
        }

        if (! $this->canViewRepairOrder($user, $repairOrder)) {
            return false;
        }

        $balance = $repairOrder->balanceDue();

        return ! $repairOrder->isTerminal()
            && $balance->hasIssuedInvoice
            && $balance->paymentsAppliedCents > 0;
    }

    public function canManageLedgerEntries(User $user, RepairOrder $repairOrder): bool
    {
        if (! $user->can(ArkCapability::RepairOrdersCloseout->value)) {
            return false;
        }

        if (! $this->canViewRepairOrder($user, $repairOrder)) {
            return false;
        }

        return ! $repairOrder->isTerminal();
    }

    public function canVoidLedgerEntry(User $user, RepairOrder $repairOrder, RepairOrderLedgerEntry $entry): bool
    {
        if (! $this->canManageLedgerEntries($user, $repairOrder)) {
            return false;
        }

        if ($entry->repair_order_id !== $repairOrder->id || $entry->isVoided()) {
            return false;
        }

        return in_array($entry->entry_type, [
            LedgerEntryType::Deposit,
            LedgerEntryType::Payment,
            LedgerEntryType::Refund,
            LedgerEntryType::StoreCreditIssuance,
        ], true);
    }

    public function canManageAppointments(User $user): bool
    {
        return $this->canPerformIntake($user)
            && \App\Ark\Operations\OperationsFeatures::appointmentsEnabled();
    }

    public function canViewOwnerBookend(User $user): bool
    {
        return OwnerWorkspaceAccess::allows($user);
    }

    public function canViewOwnerOperationalReport(User $user): bool
    {
        return $this->canViewOwnerBookend($user);
    }

    public function canAccessShopCommunications(User $user): bool
    {
        return $user->can(ArkCapability::OperationsAccess->value);
    }

    public function canViewShopAttention(User $user): bool
    {
        return $this->canAccessShopCommunications($user);
    }

    public function canReplyToCustomer(User $user): bool
    {
        return $user->can(ArkCapability::OperationsAccess->value);
    }

    public function canRecordInternalNote(User $user): bool
    {
        return $user->can(ArkCapability::CommunicationsInternalView->value)
            || $user->can(ArkCapability::OperationsAccess->value);
    }

    public function canDecodeVin(User $user): bool
    {
        return $this->canPerformIntake($user);
    }

    public function canViewConversation(User $user, Conversation $conversation): bool
    {
        if ($this->canAccessShopCommunications($user)) {
            return true;
        }

        if (! $user->hasRole(ArkRole::Technician->value)) {
            return false;
        }

        $assignedRepairOrderIds = RepairOrder::query()
            ->where('assigned_technician_id', $user->id)
            ->pluck('id');

        if ($assignedRepairOrderIds->isEmpty()) {
            return false;
        }

        return ConversationLink::query()
            ->where('conversation_id', $conversation->id)
            ->where('linkable_type', (new RepairOrder)->getMorphClass())
            ->whereIn('linkable_id', $assignedRepairOrderIds)
            ->exists();
    }
}
