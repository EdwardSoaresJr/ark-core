<?php

namespace App\Ark\Operations\Reports;

use App\Ark\Operations\Settings\ShopDisplayTimezone;
use App\Ark\Operations\RepairOrders\RepairOrder;
use App\Ark\Operations\RepairOrders\RepairOrderStatus;
use App\Ark\Operations\Telephony\TelephonyCallFlowSettings;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

final class OperationalReportDateScope
{
    public static function displayTimezone(): string
    {
        return ShopDisplayTimezone::resolve();
    }

    public static function storageTimezone(): string
    {
        return (string) config('app.timezone');
    }

    public static function shopNow(): Carbon
    {
        return now(self::displayTimezone());
    }

    public static function shopDateString(Carbon $instant): string
    {
        return $instant->copy()->timezone(self::displayTimezone())->toDateString();
    }

    public static function shopRangeLabel(Carbon $from, Carbon $to): string
    {
        $shopTz = self::displayTimezone();
        $fromLabel = $from->copy()->timezone($shopTz)->format('M j, Y');
        $toLabel = $to->copy()->timezone($shopTz)->format('M j, Y');

        return $fromLabel === $toLabel ? $fromLabel : "{$fromLabel}–{$toLabel}";
    }

    /**
     * @return array{0: Carbon, 1: Carbon}
     */
    public static function resolveRange(?string $fromDate, ?string $toDate): array
    {
        $shopTz = self::displayTimezone();
        $storageTz = self::storageTimezone();

        if (filled($fromDate) && filled($toDate)) {
            $from = Carbon::parse($fromDate, $shopTz)->startOfDay()->timezone($storageTz);
            $to = Carbon::parse($toDate, $shopTz)->endOfDay()->timezone($storageTz);
        } else {
            $today = self::shopNow();
            $from = $today->copy()->startOfMonth()->startOfDay()->timezone($storageTz);
            $to = $today->copy()->endOfDay()->timezone($storageTz);
        }

        $floor = self::trustworthyDataStartsAt();
        if ($from->lessThan($floor)) {
            $from = $floor->copy();
        }

        if ($from->greaterThan($to)) {
            [$from, $to] = [
                Carbon::parse($to->copy()->timezone($shopTz)->toDateString(), $shopTz)->startOfDay()->timezone($storageTz),
                Carbon::parse($from->copy()->timezone($shopTz)->toDateString(), $shopTz)->endOfDay()->timezone($storageTz),
            ];
        }

        return [$from, $to];
    }

    public static function trustworthyDataStartsAt(): Carbon
    {
        return Carbon::parse((string) config('ark-reports.trustworthy_data_starts_at'))->startOfDay();
    }

    /**
     * @param  Builder<RepairOrder>  $query
     * @return Builder<RepairOrder>
     */
    public static function applyTrustworthyDataFloor(Builder $query, string $table = 'repair_orders'): Builder
    {
        return $query->where(DB::raw(self::openedAtSql($table)), '>=', self::trustworthyDataStartsAt());
    }

    /**
     * @return list<RepairOrderStatus>
     */
    public static function completedStatuses(): array
    {
        return [
            RepairOrderStatus::Closed,
            RepairOrderStatus::ReadyPickup,
            RepairOrderStatus::Invoiced,
            RepairOrderStatus::Completed,
        ];
    }

    /**
     * @return list<string>
     */
    public static function completedStatusValues(): array
    {
        return array_map(
            static fn (RepairOrderStatus $status): string => $status->value,
            self::completedStatuses(),
        );
    }

    public static function openedAtSql(string $table = 'repair_orders'): string
    {
        if (Schema::hasColumn('repair_orders', 'opened_at')) {
            return "COALESCE({$table}.opened_at, {$table}.created_at)";
        }

        return "{$table}.created_at";
    }

    public static function closedAtSql(string $table = 'repair_orders'): string
    {
        $completed = implode("', '", self::completedStatusValues());

        if (Schema::hasColumn('repair_orders', 'closed_at')) {
            return "COALESCE({$table}.closed_at, CASE WHEN {$table}.status IN ('{$completed}') THEN {$table}.updated_at END)";
        }

        return "CASE WHEN {$table}.status IN ('{$completed}') THEN {$table}.updated_at END";
    }

    /**
     * @param  Builder<RepairOrder>  $query
     * @return Builder<RepairOrder>
     */
    public static function openedBetween(Builder $query, Carbon $from, Carbon $to): Builder
    {
        return self::applyTrustworthyDataFloor($query)
            ->whereBetween(DB::raw(self::openedAtSql()), [$from, $to]);
    }

    /**
     * @param  Builder<RepairOrder>  $query
     * @return Builder<RepairOrder>
     */
    public static function closedBetween(Builder $query, Carbon $from, Carbon $to): Builder
    {
        return self::applyTrustworthyDataFloor($query)
            ->whereIn('status', self::completedStatusValues())
            ->whereBetween(DB::raw(self::closedAtSql()), [$from, $to]);
    }

    /**
     * @param  Builder<RepairOrder>  $query
     * @return Builder<RepairOrder>
     */
    public static function archiveClosedBetween(Builder $query, Carbon $from, Carbon $to): Builder
    {
        return self::applyTrustworthyDataFloor($query)
            ->where('status', RepairOrderStatus::Closed)
            ->whereBetween(DB::raw(self::closedAtSql()), [$from, $to]);
    }

    public static function applyOpenedBetweenOnJoinedRepairOrders(Builder $query, Carbon $from, Carbon $to): Builder
    {
        return self::applyTrustworthyDataFloor($query, 'repair_orders')
            ->whereBetween(DB::raw(self::openedAtSql('repair_orders')), [$from, $to]);
    }

    public static function applyClosedBetweenOnJoinedRepairOrders(Builder $query, Carbon $from, Carbon $to): Builder
    {
        return self::applyTrustworthyDataFloor($query, 'repair_orders')
            ->whereIn('repair_orders.status', self::completedStatusValues())
            ->whereBetween(DB::raw(self::closedAtSql('repair_orders')), [$from, $to]);
    }

    public static function weekdayCount(Carbon $from, Carbon $to): int
    {
        return self::shopOpenDayCount($from, $to);
    }

    public static function shopOpenDayCount(Carbon $from, Carbon $to): int
    {
        return TelephonyCallFlowSettings::fromShopSettings()->openDayCount($from, $to, self::displayTimezone());
    }

    /**
     * Tekmetric-style posted sales: ROs with a posted date in range.
     * Deposits, partial payments, and paid-but-unposted ROs stay out until posted.
     *
     * @param  Builder<RepairOrder>  $query
     * @return Builder<RepairOrder>
     */
    public static function salesPostedBetween(Builder $query, Carbon $from, Carbon $to): Builder
    {
        return self::applyTrustworthyDataFloor($query)
            ->whereNotNull('posted_at')
            ->whereBetween('posted_at', [$from, $to])
            ->where(function (Builder $scope): void {
                $scope
                    ->whereNull('close_variant_key')
                    ->orWhere('close_variant_key', '!=', 'lost');
            })
            ->where(function (Builder $scope) use ($from): void {
                self::applySalesCloseAttributionRules($scope, $from);
            });
    }

    /**
     * @param  Builder<RepairOrder>  $query
     * @return Builder<RepairOrder>
     */
    public static function salesClosedBetween(Builder $query, Carbon $from, Carbon $to): Builder
    {
        return self::salesPostedBetween($query, $from, $to);
    }

    /**
     * Same rules as {@see salesPostedBetween()} for queries that already join repair_orders.
     */
    public static function applySalesPostedBetweenOnJoinedRepairOrders(Builder $query, Carbon $from, Carbon $to): Builder
    {
        return self::applyTrustworthyDataFloor($query, 'repair_orders')
            ->whereNotNull('repair_orders.posted_at')
            ->whereBetween('repair_orders.posted_at', [$from, $to])
            ->where(function (Builder $scope): void {
                $scope
                    ->whereNull('repair_orders.close_variant_key')
                    ->orWhere('repair_orders.close_variant_key', '!=', 'lost');
            })
            ->where(function (Builder $scope) use ($from): void {
                self::applySalesCloseAttributionRules($scope, $from, 'repair_orders');
            });
    }

    /**
     * @deprecated Use {@see applySalesPostedBetweenOnJoinedRepairOrders()}
     */
    public static function applySalesClosedBetweenOnJoinedRepairOrders(Builder $query, Carbon $from, Carbon $to): Builder
    {
        return self::applySalesPostedBetweenOnJoinedRepairOrders($query, $from, $to);
    }

    /**
     * Attribute sales for live shop work. Exclude only imported legacy carryover that
     * opened before trustworthy reporting data — not every legacy customer record.
     *
     * @param  Builder<\Illuminate\Database\Eloquent\Model>  $scope
     */
    private static function applySalesCloseAttributionRules(Builder $scope, Carbon $from, string $table = 'repair_orders'): void
    {
        $trustworthyFloor = self::trustworthyDataStartsAt();

        $scope->where(function (Builder $inner) use ($trustworthyFloor, $table): void {
            $inner
                ->where(DB::raw(self::openedAtSql($table)), '>=', $trustworthyFloor)
                ->orWhereNotExists(function ($query) use ($table): void {
                    $query->selectRaw('1')
                        ->from('customers')
                        ->whereColumn('customers.id', "{$table}.customer_id")
                        ->whereNotNull('customers.legacy_arksms_customer_id');
                });
        });
    }
}
