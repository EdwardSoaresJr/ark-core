<?php

namespace App\Ark\Operations\Workboard;

use App\Ark\Operations\RepairOrders\Status\RepairOrderStatusColor;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final class JobBoardLaneCatalogUpdater
{
    public function __construct(
        private readonly JobBoardLaneCatalog $catalog,
    ) {}

    /**
     * @param  array{
     *     lanes?: array<string, array{name?: string, color?: string, sort_order?: mixed, active?: mixed}>,
     *     create?: array{name?: string, color?: string|null}
     * }  $payload
     */
    public function apply(array $payload): void
    {
        if (filled(trim((string) ($payload['create']['name'] ?? '')))) {
            $this->createLane($payload['create']);
        }

        foreach ($payload['lanes'] ?? [] as $key => $lanePayload) {
            $this->updateLane((string) $key, $lanePayload);
        }

        $this->assertAtLeastOneActiveLane();
        $this->catalog->forgetCache();
    }

    /**
     * @param  array{name?: string, color?: string|null}  $payload
     */
    public function createLane(array $payload): JobBoardLane
    {
        $name = trim((string) ($payload['name'] ?? ''));

        if ($name === '') {
            throw ValidationException::withMessages([
                'create.name' => 'Lane name is required.',
            ]);
        }

        $key = Str::slug($name, '_');

        if ($key === '' || strlen($key) > 32) {
            throw ValidationException::withMessages([
                'create.name' => 'Lane name must produce a short key.',
            ]);
        }

        if (JobBoardLane::query()->where('key', $key)->exists()) {
            throw ValidationException::withMessages([
                'create.name' => 'A lane with that name already exists.',
            ]);
        }

        $maxSort = (int) JobBoardLane::query()->max('sort_order');

        $lane = JobBoardLane::query()->create([
            'key' => $key,
            'name' => $name,
            'color' => RepairOrderStatusColor::normalize($payload['color'] ?? RepairOrderStatusColor::SECONDARY),
            'sort_order' => $maxSort + 1,
            'active' => true,
            'is_system' => false,
        ]);

        $this->catalog->forgetCache();

        return $lane;
    }

    /**
     * @param  array{name?: string, color?: string, sort_order?: mixed, active?: mixed}  $payload
     */
    private function updateLane(string $key, array $payload): void
    {
        $lane = JobBoardLane::query()->where('key', $key)->first();

        if ($lane === null) {
            throw ValidationException::withMessages([
                'lanes' => "Unknown job board lane [{$key}].",
            ]);
        }

        if (array_key_exists('name', $payload)) {
            $name = trim((string) $payload['name']);

            if ($name === '') {
                throw ValidationException::withMessages([
                    "lanes.{$key}.name" => 'Lane name is required.',
                ]);
            }

            $lane->name = $name;
        }

        if (array_key_exists('color', $payload) && filled($payload['color'])) {
            $color = strtolower(trim((string) $payload['color']));

            if (! in_array($color, RepairOrderStatusColor::keys(), true)) {
                throw ValidationException::withMessages([
                    "lanes.{$key}.color" => 'Choose a valid lane color.',
                ]);
            }

            $lane->color = $color;
        }

        if (array_key_exists('sort_order', $payload) && $payload['sort_order'] !== null && $payload['sort_order'] !== '') {
            $lane->sort_order = max(0, (int) $payload['sort_order']);
        }

        if (array_key_exists('active', $payload)) {
            $lane->active = filter_var($payload['active'], FILTER_VALIDATE_BOOLEAN);
        }

        $lane->save();
    }

    private function assertAtLeastOneActiveLane(): void
    {
        if (JobBoardLane::query()->where('active', true)->exists()) {
            return;
        }

        throw ValidationException::withMessages([
            'lanes' => 'Keep at least one Job Board lane enabled.',
        ]);
    }
}
