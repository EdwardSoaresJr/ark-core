<?php

namespace App\Ark\Operations\Customers;

use App\Ark\Operations\Leads\LeadContactNameParser;
use App\Ark\Operations\Leads\LeadContactPreference;
use App\Ark\Operations\PhoneNumber;
use App\Ark\Operations\RepairOrders\RepairOrder;
use App\Ark\Operations\Settings\ShopSettings;
use App\Ark\Operations\EstimatePricing\CanonicalBillingClassCatalog;
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
    'billing_class_key',
    'store_credit_balance_cents',
    'notes',
])]
class Customer extends Model implements AuthenticatableContract
{
    use Authenticatable;

    protected static function booted(): void
    {
        // billing_class_key is identity; customer_type is the display name legacy readers use.
        // Whichever the caller changed, the other follows the canonical registry.
        static::saving(function (Customer $customer): void {
            if (! $customer->isDirty(['customer_type', 'billing_class_key'])) {
                return;
            }

            $settings = ShopSettings::current();

            if ($customer->isDirty('billing_class_key') && filled($customer->billing_class_key)) {
                $row = $settings->customerTypeRowByKeyOrName($customer->billing_class_key);
                if ($row !== null) {
                    $customer->customer_type = $row['name'];
                }

                return;
            }

            if ($customer->isDirty('customer_type')) {
                $row = $settings->customerTypeRowByKeyOrName(null, $customer->customer_type);
                $customer->billing_class_key = $row['key'] ?? null;
            }
        });

        // The last delivery result describes the main phone; a new number starts without one.
        static::updating(function (Customer $customer): void {
            if ($customer->isDirty('phone') && ! $customer->isDirty('last_sms_delivery_status')) {
                $customer->forceFill([
                    'last_sms_delivery_status' => null,
                    'last_sms_delivered_at' => null,
                    'last_sms_failed_at' => null,
                    'last_sms_error_code' => null,
                ]);
            }
        });
    }

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

    /**
     * The customer's catalog row: stored key first, then the display name (legacy rows).
     *
     * @return array<string, mixed>|null
     */
    public function billingClassRow(): ?array
    {
        return ShopSettings::current()->customerTypeRowByKeyOrName(
            $this->billing_class_key,
            $this->customer_type,
        );
    }

    public function billingClassKey(): ?string
    {
        return $this->billingClassRow()['key'] ?? null;
    }

    public function billingClassName(): string
    {
        return $this->billingClassRow()['name'] ?? (filled($this->customer_type) ? (string) $this->customer_type : 'Retail');
    }

    /**
     * Labor column this customer's class prices at (retail -> customer_pay). Unlinked classes use Retail.
     */
    public function billingClassLaborColumnKey(): string
    {
        return CanonicalBillingClassCatalog::laborColumnForClassRow($this->billingClassRow());
    }

    public function vehicles(): HasMany
    {
        return $this->hasMany(Vehicle::class);
    }

    public function repairOrders(): HasMany
    {
        return $this->hasMany(RepairOrder::class);
    }

    public function recommendations(): HasMany
    {
        return $this->hasMany(\App\Ark\Operations\Recommendations\Recommendation::class);
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
