<?php

namespace App\Ark\Operations\Vehicles;

enum VehicleIdentityPressure: string
{
    case NoVin = 'no_vin';
    case VinPresent = 'vin_present';
    case VinDecoded = 'vin_decoded';

    public function label(): string
    {
        return match ($this) {
            self::NoVin => 'VIN missing',
            self::VinPresent => 'VIN on file',
            self::VinDecoded => 'VIN decoded',
        };
    }

    public function showsChip(): bool
    {
        return $this === self::NoVin;
    }

    public function chipTone(): string
    {
        return match ($this) {
            self::NoVin => 'no-vin',
            default => 'neutral',
        };
    }

    public function visibilityHint(): ?string
    {
        return match ($this) {
            self::NoVin => 'Add the vehicle VIN on this record before sending the estimate to the customer.',
            default => null,
        };
    }

    public function estimateSendBlockedMessage(): string
    {
        return 'Add the vehicle VIN on this repair order before sending the estimate to the customer.';
    }
}
