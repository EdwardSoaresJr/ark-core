<?php

namespace App\Ark\Operations\Intake;

use App\Ark\Operations\Vehicles\Vehicle;
use Illuminate\Support\Str;

final class VehicleIntakeLookup
{
    public static function resolve(string $query): ?Vehicle
    {
        $query = trim($query);

        if ($query === '') {
            return null;
        }

        $normalizedVin = strtoupper(preg_replace('/[^A-Z0-9]/', '', $query) ?? '');

        if (strlen($normalizedVin) >= 11) {
            return Vehicle::query()
                ->with('customer')
                ->where(function ($vehicles) use ($normalizedVin): void {
                    $vehicles
                        ->where('normalized_vin', $normalizedVin)
                        ->orWhere('vin', $normalizedVin);
                })
                ->orderByDesc('id')
                ->first();
        }

        $normalizedPlate = strtoupper(preg_replace('/[\s\-]/', '', $query) ?? '');

        if (strlen($normalizedPlate) < 2) {
            return null;
        }

        return Vehicle::query()
            ->with('customer')
            ->where(function ($vehicles) use ($query, $normalizedPlate): void {
                $vehicles
                    ->where('plate', $query)
                    ->orWhere('plate', 'like', '%'.$query.'%')
                    ->orWhereRaw(
                        "REPLACE(REPLACE(UPPER(plate), '-', ''), ' ', '') = ?",
                        [$normalizedPlate],
                    );
            })
            ->orderByDesc('id')
            ->first();
    }

    public static function notFoundMessage(string $query): string
    {
        $query = trim($query);
        $looksLikeVin = strlen(strtoupper(preg_replace('/[^A-Z0-9]/', '', $query) ?? '')) >= 11;

        if ($looksLikeVin) {
            return 'No vehicle on file for that VIN. Add the customer and vehicle below.';
        }

        return 'No vehicle on file for that plate. Search by customer or add a new record.';
    }
}
