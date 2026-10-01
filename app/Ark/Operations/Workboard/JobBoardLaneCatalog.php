<?php

namespace App\Ark\Operations\Workboard;

use App\Ark\Operations\RepairOrders\RepairOrder;
use App\Ark\Operations\RepairOrders\RepairOrderStatus;
use App\Ark\Operations\RepairOrders\Status\RepairOrderStatusCatalog;
use App\Ark\Operations\RepairOrders\Status\RepairOrderStatusColor;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;

final class JobBoardLaneCatalog
{
    private const CACHE_KEY = 'job_board_lanes.v1';

    /** @var list<array{key: string, name: string, color: string, sort_order: int, active: bool, is_system: bool}>|null */
    private ?array $lanes = null;

    public function forgetCache(): void
    {
        Cache::forget(self::CACHE_KEY);
        $this->lanes = null;
    }

    public function isBooted(): bool
    {
        return Schema::hasTable('job_board_lanes') && JobBoardLane::query()->exists();
    }

    /**
     * @return list<array{key: string, name: string, color: string, sort_order: int, active: bool, is_system: bool}>
     */
    public function all(): array
    {
        $this->bootIfNeeded();

        return $this->lanes ?? JobBoardLaneCatalogDefaults::lanes();
    }

    /**
     * Active Job Board columns in display order.
     *
     * @return list<array{key: string, label: string, tone: string, color: string}>
     */
    public function homeBoardColumns(): array
    {
        return collect($this->all())
            ->filter(fn (array $lane): bool => $lane['active'])
            ->sortBy('sort_order')
            ->map(fn (array $lane): array => [
                'key' => $lane['key'],
                'label' => $lane['name'],
                'tone' => JobBoardLaneCatalogDefaults::defaultTone($lane['color']),
                'color' => RepairOrderStatusColor::normalize($lane['color']),
            ])
            ->values()
            ->all();
    }

    /**
     * @return list<string>
     */
    public function activeKeys(): array
    {
        return collect($this->homeBoardColumns())->pluck('key')->all();
    }

    /**
     * @return list<string>
     */
    public function knownKeys(): array
    {
        return collect($this->all())->pluck('key')->all();
    }

    public function lane(string $key): ?array
    {
        foreach ($this->all() as $lane) {
            if ($lane['key'] === $key) {
                return $lane;
            }
        }

        return null;
    }

    public function labelForKey(string $key): ?string
    {
        return $this->lane($key)['name'] ?? null;
    }

    public function colorForKey(string $key): string
    {
        return RepairOrderStatusColor::normalize($this->lane($key)['color'] ?? RepairOrderStatusColor::SECONDARY);
    }

    public function acceptsStatusLaneKey(?string $laneKey): bool
    {
        if ($laneKey === null || $laneKey === '') {
            return false;
        }

        return in_array($laneKey, $this->knownKeys(), true);
    }

    public function columnKeyForRepairOrder(RepairOrder $repairOrder): ?string
    {
        $slug = $repairOrder->workboardLaneStatus()->value;
        $catalog = app(RepairOrderStatusCatalog::class);

        if ($catalog->isBooted()) {
            $definition = $catalog->definitionForSlug($slug);

            if ($definition !== null) {
                if ($definition->is_terminal || ! $definition->active || ! $definition->show_on_advisor_board) {
                    return null;
                }

                $laneKey = $definition->advisor_lane_key;

                if (is_string($laneKey) && $laneKey !== '') {
                    $lane = $this->lane($laneKey);

                    if ($lane !== null && $lane['active']) {
                        return $laneKey;
                    }

                    $mapped = JobBoardLaneCatalogDefaults::homeLaneKey($laneKey, $slug);
                    $mappedLane = $mapped !== null ? $this->lane($mapped) : null;

                    if ($mappedLane !== null && $mappedLane['active']) {
                        return $mapped;
                    }
                }
            }
        }

        return $this->legacyColumnKey($repairOrder);
    }

    public function defaultStatusSlugForLane(string $laneKey): ?string
    {
        $catalog = app(RepairOrderStatusCatalog::class);

        if (! $catalog->isBooted()) {
            return $this->legacyDefaultStatusSlug($laneKey);
        }

        foreach ($catalog->advisorBoardSlugs() as $slug) {
            $definition = $catalog->definitionForSlug($slug);

            if ($definition?->advisor_lane_key === $laneKey) {
                return $slug;
            }
        }

        return $this->legacyDefaultStatusSlug($laneKey);
    }

    private function legacyColumnKey(RepairOrder $repairOrder): ?string
    {
        $laneKey = WorkboardSwimlaneCatalog::laneKeyForRepairOrder($repairOrder);

        $mapped = JobBoardLaneCatalogDefaults::homeLaneKey($laneKey, $repairOrder->workboardLaneStatus()->value);

        if ($mapped === null) {
            return null;
        }

        $lane = $this->lane($mapped);

        if ($lane !== null && ! $lane['active']) {
            return null;
        }

        return $mapped;
    }

    private function legacyDefaultStatusSlug(string $laneKey): ?string
    {
        return match ($laneKey) {
            JobBoardLaneCatalogDefaults::ESTIMATES => RepairOrderStatus::Estimate->value,
            JobBoardLaneCatalogDefaults::WAITING_APPROVAL => RepairOrderStatus::WaitingApproval->value,
            JobBoardLaneCatalogDefaults::PARTS => RepairOrderStatus::WaitingParts->value,
            JobBoardLaneCatalogDefaults::WORK_IN_PROGRESS => RepairOrderStatus::InProgress->value,
            JobBoardLaneCatalogDefaults::COMPLETED => RepairOrderStatus::ReadyPickup->value,
            default => null,
        };
    }

    private function bootIfNeeded(): void
    {
        if ($this->lanes !== null) {
            return;
        }

        if (! Schema::hasTable('job_board_lanes')) {
            $this->lanes = JobBoardLaneCatalogDefaults::lanes();

            return;
        }

        $this->lanes = Cache::remember(self::CACHE_KEY, 60, function (): array {
            $rows = JobBoardLane::query()
                ->orderBy('sort_order')
                ->orderBy('id')
                ->get();

            if ($rows->isEmpty()) {
                return JobBoardLaneCatalogDefaults::lanes();
            }

            return $rows
                ->map(fn (JobBoardLane $lane): array => [
                    'key' => $lane->key,
                    'name' => $lane->name,
                    'color' => RepairOrderStatusColor::normalize($lane->color),
                    'sort_order' => (int) $lane->sort_order,
                    'active' => (bool) $lane->active,
                    'is_system' => (bool) $lane->is_system,
                ])
                ->values()
                ->all();
        });
    }
}
