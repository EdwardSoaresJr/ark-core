<?php

namespace App\Ark\Import;

use Illuminate\Support\Facades\Storage;

final class LegacyImportCheckpoint
{
    /**
     * @param  array<string, int>  $offsets
     */
    public function __construct(
        public array $offsets = [
            'customers' => 0,
            'vehicles' => 0,
            'repair_orders' => 0,
        ],
    ) {}

    public static function load(): self
    {
        $path = config('legacy-arksms-import.checkpoint_path');

        if (! Storage::disk('local')->exists($path)) {
            return new self;
        }

        $data = json_decode(Storage::disk('local')->get($path), true);

        if (! is_array($data)) {
            return new self;
        }

        return new self([
            'customers' => (int) ($data['customers'] ?? 0),
            'vehicles' => (int) ($data['vehicles'] ?? 0),
            'repair_orders' => (int) ($data['repair_orders'] ?? 0),
        ]);
    }

    public function save(): void
    {
        Storage::disk('local')->put(
            config('legacy-arksms-import.checkpoint_path'),
            json_encode($this->offsets, JSON_PRETTY_PRINT),
        );
    }

    public function bump(string $entity, int $legacyId): void
    {
        $this->offsets[$entity] = max($this->offsets[$entity] ?? 0, $legacyId);
    }
}
