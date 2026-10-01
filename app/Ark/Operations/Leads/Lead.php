<?php

namespace App\Ark\Operations\Leads;

use App\Ark\Operations\Appointments\AppointmentRequestAvailability;
use App\Ark\Operations\Conversations\Conversation;
use App\Ark\Operations\Customers\Customer;
use App\Ark\Operations\PhoneNumber;
use App\Ark\Operations\RepairOrders\RepairOrder;
use App\Ark\Operations\Vehicles\Vehicle;
use App\Models\User;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

#[Fillable([
    'uuid',
    'source',
    'state',
    'concern',
    'contact_name',
    'contact_phone',
    'contact_email',
    'contact_preference',
    'vehicle_year',
    'vehicle_make',
    'vehicle_model',
    'vehicle_vin',
    'conversation_id',
    'customer_id',
    'vehicle_id',
    'repair_order_id',
    'assigned_user_id',
    'first_contacted_at',
    'scheduled_at',
    'arrived_at',
    'converted_at',
    'lost_at',
    'lost_reason',
    'metadata',
    'ingress_ip',
    'ingress_user_agent',
    'ingress_referrer',
    'form_rendered_at',
    'spam_signals',
])]
class Lead extends Model
{
    protected static function booted(): void
    {
        static::creating(function (Lead $lead): void {
            if (! filled($lead->uuid)) {
                $lead->uuid = (string) Str::uuid();
            }
        });
    }

    protected function casts(): array
    {
        return [
            'source' => LeadSource::class,
            'state' => LeadState::class,
            'contact_preference' => LeadContactPreference::class,
            'vehicle_year' => 'integer',
            'first_contacted_at' => 'datetime',
            'scheduled_at' => 'datetime',
            'arrived_at' => 'datetime',
            'converted_at' => 'datetime',
            'lost_at' => 'datetime',
            'metadata' => 'array',
            'form_rendered_at' => 'datetime',
            'spam_signals' => 'array',
        ];
    }

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(Conversation::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(Vehicle::class);
    }

    public function repairOrder(): BelongsTo
    {
        return $this->belongsTo(RepairOrder::class);
    }

    public function assignedUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_user_id');
    }

    protected function contactPhone(): Attribute
    {
        return Attribute::make(
            set: fn (?string $value) => PhoneNumber::normalize($value),
        );
    }

    protected function displayPhone(): Attribute
    {
        return Attribute::make(
            get: fn () => PhoneNumber::display($this->attributes['contact_phone'] ?? null),
        );
    }

    public function roughVehicleLabel(): ?string
    {
        $parts = array_filter([
            $this->vehicle_year,
            $this->vehicle_make,
            $this->vehicle_model,
        ]);

        return $parts !== [] ? implode(' ', $parts) : null;
    }

    public function ageLabel(): string
    {
        return str_replace(' ago', '', $this->created_at->diffForHumans(short: true, parts: 1));
    }

    public function isOpen(): bool
    {
        return $this->state->isOpen();
    }

    public function isNotContacted(): bool
    {
        return $this->isOpen() && $this->first_contacted_at === null;
    }

    public function isAging(Carbon $now = null): bool
    {
        $now ??= now();

        return $this->isOpen()
            && $this->created_at->lt($now->copy()->subDay());
    }

    public function isWaitingFollowUp(Carbon $now = null): bool
    {
        $now ??= now();

        return $this->state === LeadState::WaitingCustomer
            && $this->updated_at->lt($now->copy()->subDays(2));
    }

    public function preferredPeriod(): ?string
    {
        $period = $this->metadata['preferred_period'] ?? null;

        if (! is_string($period) || $period === '') {
            return null;
        }

        return in_array($period, AppointmentRequestAvailability::periodValues(), true)
            ? $period
            : null;
    }

    public function preferredPeriodLabel(): ?string
    {
        $period = $this->preferredPeriod();

        return $period !== null
            ? AppointmentRequestAvailability::periodLabel($period)
            : null;
    }

    public function withPreferredPeriod(string $period): self
    {
        $metadata = is_array($this->metadata) ? $this->metadata : [];
        $metadata['preferred_period'] = $period;
        $this->metadata = $metadata;

        return $this;
    }

    public function scopeNotSpam(Builder $query): Builder
    {
        return $query->where('state', '!=', LeadState::Spam);
    }

    /**
     * @param  Builder<self>  $query
     */
    public function scopeSpam(Builder $query): Builder
    {
        return $query->where('state', LeadState::Spam);
    }

    /**
     * @param  Builder<self>  $query
     */
    public function scopeOpen(Builder $query): Builder
    {
        return $query->whereIn('state', array_map(
            fn (LeadState $state): string => $state->value,
            LeadState::openCases(),
        ));
    }

    /**
     * @param  Builder<self>  $query
     */
    public function scopeConverted(Builder $query): Builder
    {
        return $query->where('state', LeadState::Converted);
    }

    /**
     * @param  Builder<self>  $query
     */
    public function scopeLost(Builder $query): Builder
    {
        return $query->where('state', LeadState::Lost);
    }

    /**
     * @param  Builder<self>  $query
     */
    public function scopeNotContacted(Builder $query): Builder
    {
        return $query->open()->whereNull('first_contacted_at');
    }

    /**
     * @param  Builder<self>  $query
     */
    public function scopeAging(Builder $query, Carbon $now = null): Builder
    {
        $now ??= now();

        return $query->open()->where('created_at', '<', $now->copy()->subDay());
    }
}
