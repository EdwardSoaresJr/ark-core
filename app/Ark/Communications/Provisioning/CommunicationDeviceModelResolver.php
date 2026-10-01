<?php

namespace App\Ark\Communications\Provisioning;

use App\Ark\Operations\Communications\CommunicationDevice;
use Illuminate\Support\Str;

final class CommunicationDeviceModelResolver
{
    public function resolveForDevice(CommunicationDevice $device): ?CommunicationDeviceModel
    {
        if ($device->communication_device_model_id !== null) {
            return CommunicationDeviceModel::query()
                ->whereKey($device->communication_device_model_id)
                ->where('enabled', true)
                ->first();
        }

        $model = trim((string) ($device->model ?? ''));

        if ($model === '') {
            return null;
        }

        $slug = Str::lower($model);

        return CommunicationDeviceModel::query()
            ->where('enabled', true)
            ->where(function ($query) use ($slug, $model): void {
                $query->where('slug', $slug)
                    ->orWhere('slug', Str::slug($model))
                    ->orWhere('label', $model);
            })
            ->first();
    }
}
