<?php

namespace App\Ark\Operations\Telephony;

use App\Ark\Operations\PhoneNumber;
use App\Ark\Operations\Settings\ShopSettings;

final class TelephonyForwardNumber
{
    public static function resolve(?ShopSettings $settings = null): ?string
    {
        $endpointDestination = TelephonyEndpoint::query()
            ->where('enabled', true)
            ->orderBy('position')
            ->orderBy('id')
            ->get()
            ->map(fn (TelephonyEndpoint $endpoint): string => $endpoint->dialDestination())
            ->first(fn (string $destination): bool => $destination !== '');

        return $endpointDestination !== null ? $endpointDestination : null;
    }

    public static function displaySource(?ShopSettings $settings = null): ?string
    {
        $endpoint = TelephonyEndpoint::query()
            ->where('enabled', true)
            ->orderBy('position')
            ->orderBy('id')
            ->first();

        if ($endpoint !== null && $endpoint->dialDestination() !== '') {
            return $endpoint->dialDestination();
        }

        return null;
    }

    public static function sourceLabel(?ShopSettings $settings = null): string
    {
        if (TelephonyEndpoint::query()->where('enabled', true)->exists()) {
            return 'Ring group';
        }

        return 'Not configured';
    }
}
