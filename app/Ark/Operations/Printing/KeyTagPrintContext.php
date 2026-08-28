<?php

declare(strict_types=1);

namespace App\Ark\Operations\Printing;

use App\Ark\Operations\RepairOrders\RepairOrder;
use App\Ark\Operations\Settings\ShopSettings;
use App\Ark\Operations\Vehicles\Vehicle;
use Illuminate\Support\Str;

final class KeyTagPrintContext
{
    public const CUSTOMER_NAME_KEY_TAG_LIMIT = 18;

    public const VEHICLE_LINE_KEY_TAG_LIMIT = 30;

    public const BUSINESS_NAME_KEY_TAG_LIMIT = 28;

    public function __construct(
        public readonly string $businessNameLine,
        public readonly string $customerName,
        public readonly string $vehicleLine,
        public readonly ?string $licensePlateLine,
        public readonly ?string $vinLast8,
        public readonly ?string $vinFullCompact,
        /** @var 'last6'|'last8'|'full' */
        public readonly string $vinDisplayMode,
    ) {}

    /**
     * @param  'last6'|'last8'|'full'  $vinDisplayMode
     */
    public static function fromRepairOrder(RepairOrder $repairOrder, string $vinDisplayMode): self
    {
        $repairOrder->loadMissing(['customer', 'vehicle']);

        $customer = $repairOrder->customer;
        $custRaw = trim(($customer?->first_name ?? '').' '.($customer?->last_name ?? '')) ?: 'Customer';
        $cust = Str::limit($custRaw, self::CUSTOMER_NAME_KEY_TAG_LIMIT);

        $vehicle = $repairOrder->vehicle;
        $vehRaw = trim(implode(' ', array_filter([
            $vehicle?->year,
            $vehicle?->make,
            $vehicle?->model,
        ])));
        $veh = Str::limit($vehRaw, self::VEHICLE_LINE_KEY_TAG_LIMIT);

        $vin = $vehicle?->vin;
        $vinFullCompact = null;
        $vinLast8 = null;
        if (is_string($vin) && $vin !== '') {
            $compact = strtoupper((string) preg_replace('/\s+/', '', $vin));
            if ($compact !== '') {
                $vinFullCompact = $compact;
                $vinLast8 = strlen($compact) >= 8 ? substr($compact, -8) : $compact;
            }
        }

        $mode = match ($vinDisplayMode) {
            'full' => 'full',
            'last8' => 'last8',
            default => 'last6',
        };

        return new self(
            businessNameLine: self::businessNameFromShop(),
            customerName: $cust,
            vehicleLine: $veh,
            licensePlateLine: self::licensePlateDisplayLine($vehicle),
            vinLast8: $vinLast8,
            vinFullCompact: $vinFullCompact,
            vinDisplayMode: $mode,
        );
    }

    public function vinLineForKeyTag(): ?string
    {
        if ($this->vinDisplayMode === 'full') {
            if ($this->vinFullCompact === null || $this->vinFullCompact === '') {
                return null;
            }

            return $this->vinFullCompact;
        }

        if ($this->vinFullCompact === null || $this->vinFullCompact === '') {
            return null;
        }

        if ($this->vinDisplayMode === 'last8') {
            if ($this->vinLast8 === null || $this->vinLast8 === '') {
                return null;
            }

            return 'VIN …'.$this->vinLast8;
        }

        $n = strlen($this->vinFullCompact);
        $tail = $n >= 6 ? substr($this->vinFullCompact, -6) : $this->vinFullCompact;

        return 'VIN '.$tail;
    }

    public static function businessNameFromShop(): string
    {
        $name = trim((string) (ShopSettings::current()->shop_name ?? ''));

        return Str::limit($name, self::BUSINESS_NAME_KEY_TAG_LIMIT);
    }

    private static function licensePlateDisplayLine(?Vehicle $vehicle): ?string
    {
        if ($vehicle === null) {
            return null;
        }

        $nick = trim((string) ($vehicle->nickname ?? ''));
        $plate = trim((string) ($vehicle->plate ?? ''));
        $state = trim((string) ($vehicle->plate_state ?? ''));
        $platePart = $plate !== ''
            ? ($state !== '' ? $plate.' '.$state : $plate)
            : '';

        if ($nick === '' && $platePart === '') {
            return null;
        }

        if ($nick === '') {
            return $platePart;
        }

        if ($platePart === '') {
            return $nick;
        }

        return $nick.' '.$platePart;
    }
}
