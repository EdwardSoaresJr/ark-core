<?php

namespace App\Ark\Operations\Portal;

use App\Ark\Operations\RepairOrders\RepairOrderConcern;
use Illuminate\Support\Collection;

final readonly class PortalEstimateAuthorizationFormProjection
{
    /**
     * @param  list<array{id: int, summary: string, subtotalCents: int, subtotal: string}>  $authorizationConcerns
     * @param  array<int, string>  $initialDispositions
     */
    public function __construct(
        public array $authorizationConcerns,
        public array $initialDispositions,
    ) {}

    /**
     * @param  array<string, mixed>  $snapshot
     * @param  Collection<int, RepairOrderConcern>  $pendingConcerns
     */
    public static function fromSnapshotAndPendingConcerns(array $snapshot, Collection $pendingConcerns): self
    {
        $snapshotConcernsById = collect($snapshot['concerns'] ?? [])
            ->filter(fn ($concern): bool => is_array($concern))
            ->keyBy(fn (array $concern): int => (int) ($concern['id'] ?? 0));

        $authorizationConcerns = $pendingConcerns
            ->map(function (RepairOrderConcern $concern) use ($snapshotConcernsById): array {
                $snapshotConcern = $snapshotConcernsById->get($concern->id, []);

                return [
                    'id' => $concern->id,
                    'summary' => $concern->summary,
                    'subtotalCents' => (int) ($snapshotConcern['subtotal_cents'] ?? 0),
                    'subtotal' => $snapshotConcern['subtotal'] ?? '—',
                ];
            })
            ->values()
            ->all();

        $initialDispositions = collect($authorizationConcerns)
            ->mapWithKeys(fn (array $concern): array => [
                $concern['id'] => old('concern_dispositions.'.$concern['id'], 'approved'),
            ])
            ->all();

        return new self($authorizationConcerns, $initialDispositions);
    }
}
