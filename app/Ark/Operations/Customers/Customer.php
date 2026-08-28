<?php

namespace App\Ark\Operations\Customers;

use App\Ark\Operations\Leads\LeadContactNameParser;
use App\Ark\Operations\Leads\LeadContactPreference;
use App\Ark\Operations\PhoneNumber;
use App\Ark\Operations\RepairOrders\RepairOrder;
use App\Ark\Operations\Vehicles\Vehicle;
use Illuminate\Auth\Authenticatable;
use Illuminate\Contracts\Auth\Authenticatable as AuthenticatableContract;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'legacy_arksms_customer_id',
    'first_name',
    'last_name',
    'phone',
    'sms_consent_status',
    'sms_consent_at',
    'last_sms_delivery_status',
    'last_sms_delivered_at',
    'last_sms_failed_at',
    'last_sms_error_code',
    'messenger_psid',
    'email',
    'contact_preference',
    'address_line_1',
    'address_line_2',
    'city',
    'state',
    'postal_code',
    'referral_source',
    'customer_type',
    'store_credit_balance_cents',
    'notes',
])]
class Customer extends Model implements AuthenticatableContract
{
    use Authenticatable;

    protected function casts(): array
    {
        return [
            'sms_consent_status' => CustomerSmsConsentStatus::class,
            'contact_preference' => LeadContactPreference::class,
            'sms_consent_at' => 'datetime',
            'last_sms_delivered_at' => 'datetime',
            'last_sms_failed_at' => 'datetime',
        ];
    }

    public function vehicles(): HasMany
    {
        return $this->hasMany(Vehicle::class);
    }

    public function repairOrders(): HasMany
    {
        return $this->hasMany(RepairOrder::class);
    }

    public function documents(): HasMany
    {
        return $this->hasMany(\App\Ark\Operations\Documents\Document::class);
    }

    public function getNameAttribute(): string
    {
        return LeadContactNameParser::formatFullName($this->first_name, $this->last_name);
    }

    protected function phone(): Attribute
    {
        return Attribute::make(
            set: fn (?string $value) => PhoneNumber::normalize($value),
        );
    }

    protected function email(): Attribute
    {
        return Attribute::make(
            set: function (?string $value): ?string {
                if ($value === null || trim($value) === '') {
                    return null;
                }

                return strtolower(trim($value));
            },
        );
    }

    protected function displayPhone(): Attribute
    {
        return Attribute::make(
            get: fn () => PhoneNumber::display($this->attributes['phone'] ?? null),
        );
    }

    public function hasPhone(): bool
    {
        return filled($this->phone);
    }

    public function hasEmail(): bool
    {
        return filled($this->email);
    }

    public function preferredContactLabel(): ?string
    {
        return $this->contact_preference?->outreachLabel();
    }

    public function hasAddress(): bool
    {
        return filled($this->address_line_1)
            || (filled($this->city) && filled($this->postal_code));
    }

    /**
     * @return list<string>
     */
    public function missingIdentityFieldLabels(): array
    {
        $missing = [];

        if (! $this->hasPhone()) {
            $missing[] = 'Phone';
        }

        if (! $this->hasEmail()) {
            $missing[] = 'Email';
        }

        if (! $this->hasAddress()) {
            $missing[] = 'Address';
        }

        return $missing;
    }

    public function identityPressure(): CustomerIdentityPressure
    {
        if ($this->hasPhone() && $this->hasEmail() && $this->hasAddress()) {
            return CustomerIdentityPressure::Complete;
        }

        if (! $this->hasPhone() && ! $this->hasEmail()) {
            return CustomerIdentityPressure::Critical;
        }

        return CustomerIdentityPressure::Incomplete;
    }

    public function identityPressureLabel(): string
    {
        return $this->identityPressure()->label();
    }

    public function identityPressureHint(): ?string
    {
        $missing = $this->missingIdentityFieldLabels();

        if ($missing === []) {
            return null;
        }

        return 'Missing: '.implode(', ', $missing);
    }

    protected function displayAddress(): Attribute
    {
        return Attribute::make(
            get: function (): ?string {
                $street = trim(implode(' ', array_filter([
                    $this->address_line_1,
                    $this->address_line_2,
                ])));

                $locality = trim(implode(', ', array_filter([
                    $this->city,
                    $this->state,
                ])));

                if (filled($this->postal_code)) {
                    $locality = trim($locality.' '.$this->postal_code);
                }

                $parts = array_filter([$street, $locality]);

                return $parts === [] ? null : implode(' · ', $parts);
            },
        );
    }
}
