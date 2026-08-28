<?php

namespace App\Ark\Operations\Customers;

use App\Ark\Operations\PhoneNumber;
use App\Ark\Operations\RepairOrders\RepairOrderStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class CustomerSearchQuery
{
    /**
     * @return Collection<int, Customer>
     */
    public static function matching(string $query, int $limit = 12): Collection
    {
        $query = trim($query);

        if ($query === '') {
            return collect();
        }

        return Customer::query()
            ->tap(fn (Builder $customers) => self::applyConstraints($customers, $query))
            ->tap(fn (Builder $customers) => self::withOperationalContext($customers))
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->limit($limit)
            ->get();
    }

    public static function withOperationalContext(Builder $customers): void
    {
        $activeStatuses = RepairOrderStatus::operationalQueueValues();

        $customers->with([
            'vehicles' => fn ($vehicles) => $vehicles->latest()->limit(4),
            'repairOrders' => fn ($repairOrders) => $repairOrders
                ->with('vehicle')
                ->whereIn('status', $activeStatuses)
                ->latest()
                ->limit(3),
        ]);
    }

    public static function applyConstraints(Builder $customers, string $query): void
    {
        $query = trim($query);
        $like = '%'.$query.'%';
        $phoneDigits = PhoneNumber::digits($query);
        $nameTerms = self::nameSearchTerms($query);

        $customers->where(function (Builder $customers) use ($like, $phoneDigits, $nameTerms): void {
            $customers->where(function (Builder $nameScope) use ($like, $nameTerms): void {
                if (count($nameTerms) >= 2) {
                    foreach ($nameTerms as $term) {
                        $termLike = '%'.$term.'%';

                        $nameScope->where(function (Builder $termScope) use ($termLike): void {
                            $termScope
                                ->where('first_name', 'like', $termLike)
                                ->orWhere('last_name', 'like', $termLike);
                        });
                    }

                    $nameScope->orWhereRaw("CONCAT(first_name, ' ', last_name) LIKE ?", [$like])
                        ->orWhereRaw("CONCAT(last_name, ' ', first_name) LIKE ?", [$like]);
                } else {
                    $nameScope
                        ->where('first_name', 'like', $like)
                        ->orWhere('last_name', 'like', $like);
                }
            });

            $customers
                ->orWhere('email', 'like', $like)
                ->orWhereHas('vehicles', function (Builder $vehicles) use ($like): void {
                    $vehicles
                        ->where('plate', 'like', $like)
                        ->orWhere('vin', 'like', $like);
                });

            if (strlen($phoneDigits) >= 3) {
                $customers->orWhere('phone', 'like', '%'.$phoneDigits.'%');
            }
        });
    }

    /**
     * @return list<string>
     */
    private static function nameSearchTerms(string $query): array
    {
        return collect(preg_split('/\s+/u', trim($query)) ?: [])
            ->map(fn (string $term): string => trim($term))
            ->filter(fn (string $term): bool => $term !== '' && mb_strlen($term) >= 2)
            ->values()
            ->all();
    }
}
