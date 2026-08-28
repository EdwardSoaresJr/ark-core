<?php

namespace App\Ark\Operations\Vehicles;

use App\Ark\Operations\Customers\Customer;
use App\Ark\Operations\RepairOrders\RepairOrder;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'legacy_arksms_vehicle_id',
    'customer_id',
    'vin',
    'normalized_vin',
    'plate',
    'plate_state',
    'year',
    'make',
    'model',
    'trim',
    'engine',
    'engine_display',
    'engine_code',
    'displacement_liters',
    'fuel_type',
    'aspiration',
    'transmission',
    'drive',
    'drivetrain',
    'body_style',
    'manufacturer',
    'normalized_vehicle_key',
    'vehicle_identity_source',
    'vehicle_identity_built_at',
    'color',
    'nickname',
    'public_notes',
    'private_notes',
    'insights_built_at',
])]
class Vehicle extends Model
{
    protected $casts = [
        'displacement_liters' => 'decimal:1',
        'insights_built_at' => 'datetime',
        'vehicle_identity_built_at' => 'datetime',
    ];

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function repairOrders(): HasMany
    {
        return $this->hasMany(RepairOrder::class);
    }

    public function getDisplayNameAttribute(): string
    {
        $vehicle = trim(implode(' ', array_filter([
            $this->year,
            $this->make,
            $this->model,
            $this->trim,
        ])));

        return $vehicle !== '' ? $vehicle : 'Vehicle';
    }

    public function getOperationalIdentityAttribute(): string
    {
        return $this->nickname ?: $this->display_name;
    }

    public function hasVin(): bool
    {
        return filled($this->authoritativeVin());
    }

    public function authoritativeVin(): ?string
    {
        return filled($this->vin) ? $this->vin : $this->normalized_vin;
    }

    public function hasEngineOnRecord(): bool
    {
        return filled($this->engine)
            || filled($this->engine_display)
            || filled($this->engine_code)
            || $this->displacement_liters !== null;
    }

    public function hasDriveOnRecord(): bool
    {
        return filled($this->drive) || filled($this->drivetrain);
    }

    public function identityPressure(): VehicleIdentityPressure
    {
        if (! $this->hasVin()) {
            return VehicleIdentityPressure::NoVin;
        }

        if ($this->hasDecodedIdentity()) {
            return VehicleIdentityPressure::VinDecoded;
        }

        return VehicleIdentityPressure::VinPresent;
    }

    public function identityPressureLabel(): string
    {
        return $this->identityPressure()->label();
    }

    public function identityPressureHint(): ?string
    {
        return $this->identityPressure()->visibilityHint();
    }

    public function hasDecodedIdentity(): bool
    {
        return filled($this->vehicle_identity_built_at) || filled($this->normalized_vehicle_key);
    }

    public function legacyOdometerReading(): ?int
    {
        if (! filled($this->private_notes)) {
            return null;
        }

        if (preg_match('/Legacy odometer:\s*(\d+)/', $this->private_notes, $matches) !== 1) {
            return null;
        }

        return (int) $matches[1];
    }
}
