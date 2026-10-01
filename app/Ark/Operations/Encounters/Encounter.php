<?php

namespace App\Ark\Operations\Encounters;

use App\Ark\Operations\Customers\Customer;
use App\Ark\Operations\Events\OperationalEvent;
use App\Ark\Operations\RepairOrders\RepairOrder;
use App\Ark\Operations\Vehicles\Vehicle;
use App\Models\User;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Str;

#[Fillable([
    'uuid',
    'concern',
    'callback_name',
    'callback_phone',
    'rough_vehicle',
    'source',
    'operational_state',
    'last_operational_movement_at',
    'resolved_customer_id',
    'resolved_vehicle_id',
    'created_by',
    'tow_incoming',
    'waiting_here',
    'appointment',
    'drop_off',
    'needs_shuttle',
    'warranty',
    'fleet',
    'advisor_notes',
])]
class Encounter extends Model
{
    protected function casts(): array
    {
        return [
            'source' => EncounterSource::class,
            'operational_state' => EncounterOperationalState::class,
            'last_operational_movement_at' => 'datetime',
            'tow_incoming' => 'boolean',
            'waiting_here' => 'boolean',
            'appointment' => 'boolean',
            'drop_off' => 'boolean',
            'needs_shuttle' => 'boolean',
            'warranty' => 'boolean',
            'fleet' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (Encounter $encounter): void {
            $encounter->uuid ??= (string) Str::uuid();
            $encounter->operational_state ??= EncounterOperationalState::New;
            $encounter->last_operational_movement_at ??= now();
        });
    }

    /**
     * @param  Builder<Encounter>  $query
     * @return Builder<Encounter>
     */
    public function scopeOpenQueue(Builder $query): Builder
    {
        return $query->where('operational_state', EncounterOperationalState::New->value);
    }

    public function isOpenForIntake(): bool
    {
        return $this->operational_state === EncounterOperationalState::New;
    }

    public function resolvedCustomer(): BelongsTo
    {
        return $this->belongsTo(Customer::class, 'resolved_customer_id');
    }

    public function resolvedVehicle(): BelongsTo
    {
        return $this->belongsTo(Vehicle::class, 'resolved_vehicle_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function visit(): HasOne
    {
        return $this->hasOne(RepairOrder::class);
    }

    public function operationalEvents(): MorphMany
    {
        return $this->morphMany(OperationalEvent::class, 'aggregate')->orderBy('occurred_at')->orderBy('id');
    }

    /**
     * @return list<string>
     */
    public function quickFlags(): array
    {
        return collect([
            $this->waiting_here ? 'Waiting Here' : null,
            $this->drop_off ? 'Drop Off' : null,
            $this->needs_shuttle ? 'Needs Shuttle' : null,
            $this->fleet ? 'Fleet' : null,
            $this->warranty ? 'Warranty' : null,
            $this->tow_incoming ? 'Tow-In' : null,
            $this->appointment ? 'Appointment' : null,
        ])->filter()->values()->all();
    }
}
