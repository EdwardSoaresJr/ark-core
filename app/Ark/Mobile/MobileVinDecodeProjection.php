<?php

namespace App\Ark\Mobile;

use App\Ark\Vehicles\CanonicalVehicleIdentity;

final class MobileVinDecodeProjection
{
    /**
     * @return array{vehicle: array<string, mixed>}
     */
    public function present(CanonicalVehicleIdentity $identity): array
    {
        return [
            'vehicle' => [
                ...$identity->toFormArray(),
                'label' => $this->label($identity),
                'usable' => $identity->isUsable(),
                'source_label' => $this->sourceLabel($identity->source),
            ],
        ];
    }

    private function label(CanonicalVehicleIdentity $identity): string
    {
        $parts = array_filter([
            $identity->year,
            $identity->make,
            $identity->model,
            $identity->trim,
        ], fn ($value) => filled($value));

        if ($parts === []) {
            return 'Vehicle';
        }

        return trim(implode(' ', $parts));
    }

    private function sourceLabel(?string $source): ?string
    {
        return match ($source) {
            'nhtsa' => 'NHTSA',
            'partstech' => 'PartsTech',
            'partstech+nhtsa' => 'PartsTech + NHTSA',
            default => $source !== null && $source !== '' ? strtoupper($source) : null,
        };
    }
}
