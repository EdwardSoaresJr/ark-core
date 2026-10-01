<?php

namespace App\Ark\Operations\Communications;

use App\Ark\Communications\Provisioning\CommunicationDeviceModel;
use App\Ark\Communications\Provisioning\EndpointConfigurationProjection;
use App\Ark\Communications\Provisioning\EndpointProvisionServerUrl;
use App\Ark\Operations\Workstations\Workstation;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class CommunicationDevice extends Model
{
    protected $fillable = [
        'shop_settings_id',
        'communication_device_model_id',
        'workstation_id',
        'name',
        'model',
        'mac_address',
        'firmware_version',
        'assigned_user_id',
        'provider',
        'provider_identifier',
        'capabilities',
        'status',
        'is_active',
        'last_registered_at',
        'last_provisioned_at',
        'microbrowser_token',
    ];

    protected function casts(): array
    {
        return [
            'provider' => CommunicationDeviceProvider::class,
            'status' => CommunicationDeviceStatus::class,
            'capabilities' => 'array',
            'is_active' => 'boolean',
            'last_registered_at' => 'datetime',
            'last_provisioned_at' => 'datetime',
        ];
    }

    public function assignedUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_user_id');
    }

    public function workstation(): BelongsTo
    {
        return $this->belongsTo(Workstation::class);
    }

    public function deviceModel(): BelongsTo
    {
        return $this->belongsTo(CommunicationDeviceModel::class, 'communication_device_model_id');
    }

    public function endpointConfigurationProjections(): HasMany
    {
        return $this->hasMany(EndpointConfigurationProjection::class);
    }

    public function currentEndpointConfigurationProjection(): HasOne
    {
        return $this->hasOne(EndpointConfigurationProjection::class)
            ->whereNull('superseded_at')
            ->latest('generated_at');
    }

    public function telephonyExtension(): HasOne
    {
        return $this->hasOne(\App\Ark\Operations\Telephony\TelephonyExtension::class, 'communication_device_id');
    }

    public function kindLabel(): string
    {
        return match ($this->provider) {
            CommunicationDeviceProvider::Mobile => 'Mobile',
            default => 'Desk Phone',
        };
    }

    public function summaryLabel(): string
    {
        return $this->kindLabel().' · '.$this->status->label();
    }

    /**
     * @return list<CommunicationDeviceCapability>
     */
    public function capabilityEnums(): array
    {
        $values = is_array($this->capabilities) ? $this->capabilities : [];

        return array_values(array_filter(array_map(
            fn (mixed $value): ?CommunicationDeviceCapability => is_string($value)
                ? CommunicationDeviceCapability::tryFrom($value)
                : null,
            $values,
        )));
    }

    public function provisionConfigPath(): string
    {
        return "communication-devices/{$this->id}/provision.cfg";
    }

    public function hasProvisionConfig(): bool
    {
        return Storage::disk('local')->exists($this->provisionConfigPath());
    }

    public function provisionConfigFilename(): string
    {
        $slug = Str::slug($this->name);

        return $slug !== '' ? "{$slug}.cfg" : "device-{$this->id}.cfg";
    }

    public function provisionUrl(): ?string
    {
        if (! filled($this->mac_address)) {
            return null;
        }

        return EndpointProvisionServerUrl::base().strtoupper((string) $this->mac_address).'.cfg';
    }
}
