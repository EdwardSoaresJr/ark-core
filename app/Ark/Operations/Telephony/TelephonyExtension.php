<?php

namespace App\Ark\Operations\Telephony;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TelephonyExtension extends Model
{
    protected $fillable = [
        'extension',
        'display_name',
        'user_id',
        'workstation_id',
        'communication_device_id',
        'mobile_device_id',
        'device_type',
        'enabled',
        'location',
        'notes',
        'secret',
    ];

    protected function casts(): array
    {
        return [
            'device_type' => TelephonyExtensionDeviceType::class,
            'enabled' => 'boolean',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(\App\Models\User::class);
    }

    public function workstation(): BelongsTo
    {
        return $this->belongsTo(\App\Ark\Operations\Workstations\Workstation::class);
    }

    public function communicationDevice(): BelongsTo
    {
        return $this->belongsTo(\App\Ark\Operations\Communications\CommunicationDevice::class);
    }

    public function mobileDevice(): BelongsTo
    {
        return $this->belongsTo(\App\Ark\Mobile\MobileDevice::class);
    }

    public function isEnabled(): bool
    {
        return (bool) $this->enabled;
    }

    /**
     * Primary enabled extension for a workstation — shared read path for Voice UI and provisioning.
     */
    public static function primaryForWorkstation(int $workstationId): ?self
    {
        return static::query()
            ->where('workstation_id', $workstationId)
            ->where('enabled', true)
            ->orderBy('extension')
            ->first();
    }

    /**
     * @param  list<int>  $workstationIds
     * @return array<int, self>
     */
    public static function primaryMapForWorkstations(array $workstationIds): array
    {
        if ($workstationIds === []) {
            return [];
        }

        $extensions = static::query()
            ->whereIn('workstation_id', $workstationIds)
            ->where('enabled', true)
            ->orderBy('workstation_id')
            ->orderBy('extension')
            ->get();

        $map = [];

        foreach ($extensions as $extension) {
            $workstationId = (int) $extension->workstation_id;

            if (! array_key_exists($workstationId, $map)) {
                $map[$workstationId] = $extension;
            }
        }

        return $map;
    }

    public static function queryForExtensionNumber(string $extensionNumber): Builder
    {
        return static::query()->where('extension', trim($extensionNumber));
    }
}
