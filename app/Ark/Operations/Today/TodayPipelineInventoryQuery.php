<?php

namespace App\Ark\Operations\Today;

use App\Ark\Operations\Financial\LedgerEntryType;
use App\Ark\Operations\RepairOrders\RepairOrderStatus;
use App\Ark\Operations\Reports\OperationalReportDateScope;
use App\Ark\Operations\Workboard\WorkboardSwimlaneCatalog;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;

/**
 * Drill-down filters for Today pipeline metrics → repair order index.
 */
final class TodayPipelineInventoryQuery
{
    public const COLLECTED_THIS_MONTH = 'collected_this_month';

    public const AWAITING_APPROVAL = 'awaiting_approval';

    public const APPROVED_NOT_STARTED = 'approved_not_started';

    public const READY_FOR_PICKUP = 'ready_for_pickup';

    public const REVENUE_IN_FLIGHT = 'revenue_in_flight';

    public static function label(string $key): ?string
    {
        return match ($key) {
            self::COLLECTED_THIS_MONTH => 'Collected this month',
            self::AWAITING_APPROVAL => 'Awaiting approval',
            self::APPROVED_NOT_STARTED => 'Approved not started',
            self::READY_FOR_PICKUP => 'Ready for pickup',
            self::REVENUE_IN_FLIGHT => 'Revenue in flight',
            default => null,
        };
    }

    public static function url(string $key): string
    {
        if ($key === self::COLLECTED_THIS_MONTH) {
            [$from, $to] = self::currentMonthRange();

            return route('operations.repair-orders.index', [
                'pipeline' => self::COLLECTED_THIS_MONTH,
                'collected_from' => OperationalReportDateScope::shopDateString($from),
                'collected_to' => OperationalReportDateScope::shopDateString($to),
            ]);
        }

        return route('operations.repair-orders.index', ['pipeline' => $key]);
    }

    public static function apply(Builder $query, string $key, ?Carbon $collectedFrom = null, ?Carbon $collectedTo = null): Builder
    {
        return match ($key) {
            self::COLLECTED_THIS_MONTH => self::applyCollectedThisMonth($query, $collectedFrom, $collectedTo),
            self::AWAITING_APPROVAL => $query->where('status', RepairOrderStatus::WaitingApproval->value),
            self::APPROVED_NOT_STARTED => $query->whereIn('status', TodayPipelineProjection::approvedNotStartedSlugs()),
            self::READY_FOR_PICKUP => $query->whereIn('status', TodayPipelineProjection::readyForPickupSlugs()),
            self::REVENUE_IN_FLIGHT => $query->where(function (Builder $scoped): void {
                $scoped->where('status', RepairOrderStatus::WaitingApproval->value)
                    ->orWhereIn('status', TodayPipelineProjection::approvedNotStartedSlugs())
                    ->orWhereIn('status', TodayPipelineProjection::readyForPickupSlugs());
            }),
            default => $query,
        };
    }

    /**
     * @return array{0: Carbon, 1: Carbon}
     */
    public static function currentMonthRange(): array
    {
        $today = OperationalReportDateScope::shopNow();
        $storageTz = OperationalReportDateScope::storageTimezone();

        return [
            $today->copy()->startOfMonth()->startOfDay()->timezone($storageTz),
            $today->copy()->endOfDay()->timezone($storageTz),
        ];
    }

    private static function applyCollectedThisMonth(
        Builder $query,
        ?Carbon $collectedFrom,
        ?Carbon $collectedTo,
    ): Builder {
        [$from, $to] = self::resolveCollectedRange($collectedFrom, $collectedTo);

        return $query->whereHas('ledgerEntries', function (Builder $ledger) use ($from, $to): void {
            $ledger
                ->active()
                ->whereIn('entry_type', [
                    LedgerEntryType::Payment->value,
                    LedgerEntryType::Deposit->value,
                ])
                ->whereBetween('recorded_at', [$from, $to]);
        });
    }

    /**
     * @return array{0: Carbon, 1: Carbon}
     */
    private static function resolveCollectedRange(?Carbon $collectedFrom, ?Carbon $collectedTo): array
    {
        if ($collectedFrom !== null && $collectedTo !== null) {
            $storageTz = OperationalReportDateScope::storageTimezone();

            return [
                $collectedFrom->copy()->startOfDay()->timezone($storageTz),
                $collectedTo->copy()->endOfDay()->timezone($storageTz),
            ];
        }

        return self::currentMonthRange();
    }
}
