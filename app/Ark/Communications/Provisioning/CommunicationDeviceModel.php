<?php

namespace App\Ark\Communications\Provisioning;

use Illuminate\Database\Eloquent\Model;

/**
 * Platform catalog — firmware policy and builder dispatch for endpoint models.
 */
class CommunicationDeviceModel extends Model
{
    protected $fillable = [
        'slug',
        'manufacturer',
        'label',
        'minimum_firmware',
        'recommended_firmware',
        'latest_firmware',
        'builder',
        'enabled',
    ];

    protected function casts(): array
    {
        return [
            'builder' => EndpointProvisionBuilder::class,
            'enabled' => 'boolean',
        ];
    }

    public function isEnabled(): bool
    {
        return (bool) $this->enabled;
    }
}
