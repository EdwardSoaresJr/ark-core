<?php

namespace App\Ark\Operations\Vehicles;

use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

final class VehicleIdentityInput
{
    public static function mergeCoercedVinFromRequest(Request $request): void
    {
        if (! $request->has('vin')) {
            return;
        }

        $request->merge([
            'vin' => (new \App\Ark\Vehicles\VinNormalizer)->coerceInput($request->input('vin')),
        ]);
    }

    public static function hasMinimumIdentity(array $data): bool
    {
        if (trim((string) ($data['vin'] ?? '')) !== '') {
            return true;
        }

        if (trim((string) ($data['plate'] ?? '')) !== '') {
            return true;
        }

        $year = $data['year'] ?? null;
        $make = trim((string) ($data['make'] ?? ''));
        $model = trim((string) ($data['model'] ?? ''));

        return filled($year) && $make !== '' && $model !== '';
    }

    public static function validate(array $data): void
    {
        if (self::hasMinimumIdentity($data)) {
            return;
        }

        throw ValidationException::withMessages([
            'vin' => 'Enter a VIN, plate, or year, make, and model before saving.',
        ]);
    }
}
