<?php

namespace App\Ark\Operations\Workstations;

use App\Ark\Operations\Communications\CommunicationDevice;
use App\Ark\Operations\Settings\ShopSettings;
use App\Ark\Operations\Telephony\TelephonyExtension;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Workstation extends Model
{
    protected $fillable = [
        'shop_settings_id',
        'name',
        'location_label',
        'current_operator_user_id',
        'last_operator_user_id',
        'last_activity_at',
        'is_active',
        'accepts_scheduled_work',
    ];

    protected function casts(): array
    {
        return [
            'last_activity_at' => 'datetime',
            'is_active' => 'boolean',
            'accepts_scheduled_work' => 'boolean',
        ];
    }

    public function shopSettings(): BelongsTo
    {
        return $this->belongsTo(ShopSettings::class);
    }

    public function currentOperator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'current_operator_user_id');
    }

    public function lastOperator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'last_operator_user_id');
    }

    public function devices(): HasMany
    {
        return $this->hasMany(CommunicationDevice::class);
    }

    public function telephonyExtensions(): HasMany
    {
        return $this->hasMany(TelephonyExtension::class);
    }

    public function primaryTelephonyExtension(): HasOne
    {
        return $this->hasOne(TelephonyExtension::class)
            ->where('enabled', true)
            ->orderBy('extension');
    }

    public function browserBindings(): HasMany
    {
        return $this->hasMany(WorkstationBrowserBinding::class);
    }

    public function displayLocation(): string
    {
        return filled($this->location_label)
            ? (string) $this->location_label
            : $this->name;
    }

    /**
     * Desk phone / station appliance label — extension display name when provisioned.
     */
    public function applianceDisplayName(): string
    {
        $extension = $this->relationLoaded('primaryTelephonyExtension')
            ? $this->getRelation('primaryTelephonyExtension')
            : $this->primaryTelephonyExtension()->first();

        if ($extension instanceof TelephonyExtension && filled($extension->display_name)) {
            return trim((string) $extension->display_name);
        }

        if (filled($this->location_label)) {
            return trim((string) $this->location_label);
        }

        return $this->name;
    }

    public function primaryExtensionNumber(): ?string
    {
        $extension = $this->relationLoaded('primaryTelephonyExtension')
            ? $this->getRelation('primaryTelephonyExtension')
            : $this->primaryTelephonyExtension()->first();

        if (! $extension instanceof TelephonyExtension) {
            return null;
        }

        $number = trim((string) $extension->extension);

        return $number !== '' ? $number : null;
    }

    public function applianceStationLabel(): string
    {
        $name = $this->applianceDisplayName();
        $extension = $this->primaryExtensionNumber();

        if ($extension !== null) {
            return $name.' · ext '.$extension;
        }

        return $name;
    }
}
