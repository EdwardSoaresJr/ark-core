<?php

namespace App\Ark\Operations\RepairOrders;

use App\Ark\Operations\RepairOrders\RepairOrderVisitMode;
use App\Ark\Operations\Financial\BalanceDueCalculator;
use App\Ark\Operations\Financial\EstimateTotalsCalculator;
use App\Ark\Operations\PhoneNumber;

final class ServiceLaneIdentityPresenter
{
    /**
     * @return array{
     *     customer: array{name: string, profileHref: ?string, billingClass: string, phone: ?string, phoneHref: ?string, referral: ?string},
     *     vehicle: array{title: string, profileHref: ?string, scanMileage: ?string, plate: ?string},
     *     ownership: array{statusLabel: string, statusTone: string, repairOrderId: int, visitModeLabel: ?string, advisor: ?string, technician: string},
     *     financial: ?array{estimate: string, approved: string, due: string},
     * }
     */
    public static function forRepairOrder(RepairOrder $repairOrder, ?EstimateTotals $totals = null): array
    {
        $identity = OperationalIdentityPresenter::forRepairOrder($repairOrder, includeStaffPosture: false);
        $repairOrder->loadMissing(['customer', 'vehicle']);

        $phoneLine = collect($identity['customer']['lines'])->firstWhere('label', 'Phone');
        $referralLine = collect($identity['customer']['lines'])->firstWhere('label', 'Referral');
        $plateLine = collect($identity['vehicle']['lines'])->firstWhere('label', 'Plate');
        $visitMode = RepairOrderVisitMode::fromRepairOrder($repairOrder);

        $technicianLine = collect($identity['visit']['lines'])->firstWhere('label', 'Technician');

        return [
            'customer' => [
                'name' => $identity['customer']['title'],
                'profileHref' => route('operations.customers.show', $repairOrder->customer),
                'billingClass' => $identity['customer']['type'] ?? 'Retail',
                'phone' => $phoneLine['value'] ?? PhoneNumber::display($repairOrder->customer->phone),
                'phoneHref' => $phoneLine['href'] ?? PhoneNumber::telUri($repairOrder->customer->phone),
                'referral' => $referralLine['value'] ?? null,
            ],
            'vehicle' => [
                'title' => $identity['vehicle']['title'],
                'profileHref' => route('operations.customers.show', $repairOrder->customer).'#vehicle-'.$repairOrder->vehicle_id,
                'scanMileage' => self::scanMileageLabel($repairOrder),
                'plate' => $plateLine['value'] ?? null,
            ],
            'ownership' => [
                'statusLabel' => $repairOrder->statusDisplayLabel(),
                'statusTone' => $repairOrder->status->indexTone(),
                'repairOrderId' => $repairOrder->repair_order_id,
                'visitModeLabel' => $visitMode?->label(),
                'advisor' => collect($identity['visit']['lines'])->firstWhere('label', 'Advisor')['value'] ?? null,
                'technician' => $technicianLine['value'] ?? 'Unassigned',
            ],
            'financial' => $totals !== null ? self::financialOrientation($repairOrder, $totals) : null,
        ];
    }

    /**
     * @return array{estimate: string, approved: string, due: string}
     */
    private static function financialOrientation(RepairOrder $repairOrder, EstimateTotals $totals): array
    {
        $calculator = app(EstimateTotalsCalculator::class);
        $balance = app(BalanceDueCalculator::class)->forRepairOrder($repairOrder);
        $approvedCents = $calculator->approvedTotalsForRead($repairOrder)->totalCents();

        return [
            'estimate' => $totals->format($totals->totalCents()),
            'approved' => $totals->format($approvedCents),
            'due' => $balance->hasIssuedInvoice
                ? $totals->format($balance->balanceDueCents)
                : '—',
        ];
    }

    private static function scanMileageLabel(RepairOrder $repairOrder): ?string
    {
        $mileageIn = $repairOrder->resolvedMileageIn();

        if ($mileageIn === null) {
            return null;
        }

        return is_numeric($mileageIn)
            ? number_format((int) $mileageIn).' mi'
            : (string) $mileageIn;
    }
}
