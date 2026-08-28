<?php

namespace App\Ark\Operations\Workstations;

use App\Ark\Operations\Communications\CommunicationDevice;
use App\Models\User;

final class WorkstationOperatorResolver
{
    public function currentOperatorForDevice(CommunicationDevice $device): ?User
    {
        $device->loadMissing('workstation.currentOperator');

        $operator = $device->workstation?->currentOperator;

        return $operator instanceof User && $operator->isActive()
            ? $operator
            : null;
    }

    public function labelForDevice(CommunicationDevice $device): ?string
    {
        return $this->currentOperatorForDevice($device)?->name;
    }

    public function firstNameForDevice(CommunicationDevice $device): string
    {
        $operator = $this->currentOperatorForDevice($device);

        if (! $operator instanceof User) {
            return 'Not signed in';
        }

        $parts = preg_split('/\s+/', trim($operator->name), 2);

        return $parts[0] ?? $operator->name;
    }

    public function operatorUserIdForDevice(CommunicationDevice $device): ?int
    {
        return $this->currentOperatorForDevice($device)?->id;
    }
}
