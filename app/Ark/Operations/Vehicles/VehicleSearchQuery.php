<?php

namespace App\Ark\Operations\Vehicles;

use App\Ark\Operations\Customers\CustomerSearchQuery;
use App\Ark\Operations\RepairOrders\RepairOrderStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class VehicleSearchQuery
{
    /**
     * @return Collection<int, Vehicle>
     */
    public static function matching(string $query, int $limit = 8): Collection
    {
        $query = trim($query);

        if ($query === '') {
            return collect();
        }

        return Vehicle::query()
            ->tap(fn (Builder $vehicles) => self::applyConstraints($vehicles, $query))
            ->tap(fn (Builder $vehicles) => self::withOperationalContext($vehicles))
            ->orderByDesc('id')
            ->limit($limit)
            ->get();
    }

    public static function withOperationalContext(Builder $vehicles): void
    {
        $activeStatuses = RepairOrderStatus::operationalQueueValues();

        $vehicles->with([
            'customer',
            'repairOrders' => fn ($repairOrders) => $repairOrders
                ->whereIn('status', $activeStatuses)
                ->latest()
                ->limit(1),
        ])->withCount('repairOrders');
    }

    public static function applyConstraints(Builder $vehicles, string $query): void
    {
        $query = trim($query);

        if ($query === '') {
            return;
        }

        $like = '%'.$query.'%';
        $normalizedVin = strtoupper(preg_replace('/\s+/', '', $query));

        $vehicles->where(function (Builder $vehicles) use ($like, $query, $normalizedVin): void {
            $vehicles
                ->where('plate', 'like', $like)
                ->orWhere('vin', 'like', $like)
                ->orWhere('nickname', 'like', $like)
                ->orWhere('make', 'like', $like)
                ->orWhere('model', 'like', $like)
                ->orWhere('trim', 'like', $like);

            if (strlen($normalizedVin) >= 4) {
                $vehicles->orWhere('normalized_vin', 'like', '%'.$normalizedVin.'%');
            }

            if (preg_match('/^\d{4}$/', $query) === 1) {
                $vehicles->orWhere('year', (int) $query);
            }

            $vehicles->orWhereHas('customer', function (Builder $customer) use ($query): void {
                CustomerSearchQuery::applyConstraints($customer, $query);
            });
        });
    }
}
