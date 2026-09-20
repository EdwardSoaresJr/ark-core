<?php

namespace App\Ark\Operations\RepairOrders;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * One-shot historical learn: closed / posted tickets teach companions.
 */
final class BackfillEstimateCompanionPatternsAction
{
    public function __construct(
        private readonly LearnEstimateCompanionPatternsAction $learn,
    ) {}

    /**
     * @return array{scanned: int, ingested: int, patterns_before: int, patterns_after: int}
     */
    public function execute(bool $fresh = false, ?int $limit = null): array
    {
        $patternsBefore = EstimateCompanionPattern::query()->count();

        if ($fresh) {
            EstimateCompanionPattern::query()->delete();
            SeedEstimateCompanionPatterns::install();
        }

        $scanned = 0;
        $ingested = 0;
        $max = $limit;

        $this->candidateQuery()->chunkById(100, function (Collection $orders) use (&$scanned, &$ingested, $max): bool {
            foreach ($orders as $repairOrder) {
                if ($max !== null && $scanned >= $max) {
                    return false;
                }

                $scanned++;
                $repairOrder->loadMissing(['lines', 'concerns']);

                $hasLabor = $repairOrder->lines->contains(
                    fn (RepairOrderLine $line): bool => $line->type === RepairOrderLineType::Labor
                        || $line->type === RepairOrderLineType::Package,
                );
                $hasPart = $repairOrder->lines->contains(
                    fn (RepairOrderLine $line): bool => $line->type === RepairOrderLineType::Part
                        || $line->type === RepairOrderLineType::Fee,
                );

                if (! $hasLabor || ! $hasPart) {
                    continue;
                }

                $this->learn->ingest($repairOrder);
                $ingested++;
            }

            return $max === null || $scanned < $max;
        });

        return [
            'scanned' => $scanned,
            'ingested' => $ingested,
            'patterns_before' => $patternsBefore,
            'patterns_after' => EstimateCompanionPattern::query()->count(),
        ];
    }

    public function candidateCount(?int $limit = null): int
    {
        $count = (int) $this->candidateQuery()->count();

        if ($limit !== null && $limit > 0) {
            return min($count, $limit);
        }

        return $count;
    }

    /**
     * @return Builder<RepairOrder>
     */
    private function candidateQuery(): Builder
    {
        return RepairOrder::query()
            ->where(function ($builder): void {
                $builder->where('status', RepairOrderStatus::Closed->value)
                    ->orWhereNotNull('posted_at');
            })
            ->whereHas('lines', function ($builder): void {
                $builder->whereIn('type', [
                    RepairOrderLineType::Labor->value,
                    RepairOrderLineType::Package->value,
                ]);
            })
            ->whereHas('lines', function ($builder): void {
                $builder->whereIn('type', [
                    RepairOrderLineType::Part->value,
                    RepairOrderLineType::Fee->value,
                ]);
            })
            ->orderBy('id');
    }
}
