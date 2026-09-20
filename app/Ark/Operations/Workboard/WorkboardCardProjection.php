<?php

namespace App\Ark\Operations\Workboard;

use App\Ark\Operations\Financial\BalanceDueCalculator;
use App\Ark\Operations\RepairOrders\PartsPressure;
use App\Ark\Operations\RepairOrders\EstimateTotals;
use App\Ark\Operations\RepairOrders\RepairOrder;
use App\Ark\Operations\RepairOrders\RepairOrderStatus;
use App\Ark\Operations\RepairOrders\RepairOrderWorkflowStatus;
use App\Ark\Operations\Vehicles\VehicleIdentityPressure;
use Illuminate\Support\Collection;

final readonly class WorkboardCardProjection
{
    public function __construct(
        public PartsPressure $partsPressure,
        public ?string $partsPressureSummary,
        public ?string $partsBlockerSummary,
        public VehicleIdentityPressure $vehicleIdentityPressure,
        public ?string $vehicleIdentityPressureHint,
        public ?string $estimateTotalLabel,
        public ?bool $isPaid,
        public string $communicationPostureLabel,
    ) {}

    /**
     * @param  Collection<int, RepairOrder>  $repairOrders
     * @param  Collection<int, EstimateTotals>  $repairOrderTotals
     * @return Collection<int, self>
     */
    public static function mapForRepairOrders(
        Collection $repairOrders,
        Collection $repairOrderTotals,
        BalanceDueCalculator $balanceDueCalculator,
    ): Collection {
        $balances = $balanceDueCalculator->mapForRepairOrders(
            $repairOrders->filter(fn (RepairOrder $repairOrder): bool => self::needsPaymentContext($repairOrder->status)),
        );

        return $repairOrders->mapWithKeys(function (RepairOrder $repairOrder) use ($repairOrderTotals, $balances): array {
            return [
                $repairOrder->id => self::forRepairOrder(
                    $repairOrder,
                    $repairOrderTotals[$repairOrder->id] ?? null,
                    balances: $balances,
                ),
            ];
        });
    }

    /**
     * @param  array<int, \App\Ark\Operations\Financial\BalanceDueResult>|null  $balances
     */
    public static function forRepairOrder(
        RepairOrder $repairOrder,
        ?EstimateTotals $totals,
        ?BalanceDueCalculator $balanceDueCalculator = null,
        ?array $balances = null,
    ): self {
        $partsPressure = $repairOrder->partsPressure();
        $partsPressureSummary = $repairOrder->partsPressureSummary($partsPressure);
        $partsBlockerSummary = $repairOrder->partsBlockerSummary();
        $estimateTotalLabel = $totals && $totals->totalCents() > 0
            ? $totals->format($totals->totalCents())
            : null;

        $isPaid = null;

        if (self::needsPaymentContext($repairOrder->status)) {
            if ($balances !== null) {
                $isPaid = ($balances[$repairOrder->id] ?? null)?->isPaid();
            } elseif ($balanceDueCalculator !== null) {
                $isPaid = $balanceDueCalculator->forRepairOrder($repairOrder)->isPaid();
            }
        }

        return new self(
            partsPressure: $partsPressure,
            partsPressureSummary: $partsPressureSummary,
            partsBlockerSummary: $partsBlockerSummary,
            vehicleIdentityPressure: $repairOrder->vehicleIdentityPressure(),
            vehicleIdentityPressureHint: $repairOrder->vehicleIdentityPressureHint(),
            estimateTotalLabel: $estimateTotalLabel,
            isPaid: $isPaid,
            communicationPostureLabel: $repairOrder->communicationPostureLabel(),
        );
    }

    public function partsPressureLabel(): string
    {
        return $this->partsPressure->label();
    }

    public function footnoteContextFor(RepairOrder $repairOrder, RepairOrderStatus $queueStatus): ?string
    {
        if ($this->partsPressureSummary !== null) {
            return $this->partsPressureSummary;
        }

        if ($this->partsBlockerSummary !== null) {
            return $this->partsBlockerSummary;
        }

        if (in_array($queueStatus, [
            RepairOrderStatus::Invoiced,
            RepairOrderStatus::ReadyPickup,
        ], true)) {
            // Ledger authority only ($this->isPaid from BalanceDueCalculator). Never paymentStatus().
            return ($this->isPaid ?? false)
                ? 'Paid · ready to close'
                : 'Balance due · collect before close';
        }

        if ($queueStatus === RepairOrderStatus::Completed) {
            return 'Work complete · invoice next';
        }

        if (in_array($queueStatus, [
            RepairOrderStatus::WaitingApproval,
        ], true)) {
            return $this->communicationPostureLabel;
        }

        if (in_array($queueStatus, [
            RepairOrderStatus::Approved,
            RepairOrderStatus::ReadyForWork,
            RepairOrderStatus::InProgress,
            RepairOrderStatus::QualityCheck,
        ], true)) {
            return $repairOrder->repairActionOwnerSummary() ?? 'Needs owner';
        }

        return null;
    }

    public function nextAction(RepairOrder $repairOrder, RepairOrderStatus $queueStatus): string
    {
        return match ($queueStatus) {
            RepairOrderStatus::Draft => 'Diagnose',
            RepairOrderStatus::Estimate => 'Finish estimate',
            RepairOrderStatus::WaitingApproval => 'Get approval',
            RepairOrderStatus::Approved,
            RepairOrderStatus::ReadyForWork => $repairOrder->hasRepairActionOwner() ? 'Start work' : 'Assign owners',
            RepairOrderStatus::WaitingParts => 'Clear parts',
            RepairOrderStatus::InProgress => 'Work active',
            RepairOrderStatus::QualityCheck => 'QC pass',
            RepairOrderStatus::Completed => 'Issue invoice',
            RepairOrderStatus::Invoiced,
            RepairOrderStatus::ReadyPickup => ($this->isPaid ?? false) ? 'Close paid' : 'Collect balance',
            default => 'Review',
        };
    }

    private static function needsPaymentContext(RepairOrderWorkflowStatus|RepairOrderStatus|string $status): bool
    {
        return RepairOrderWorkflowStatus::from($status)->isOneOf([
            RepairOrderStatus::Invoiced,
            RepairOrderStatus::ReadyPickup,
        ]);
    }
}
