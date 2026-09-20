<?php

namespace App\Ark\Operations\Communications;

enum CommunicationDeviceStatus: string
{
    case Unassigned = 'unassigned';
    case Discovered = 'discovered';
    case WaitingForRegistration = 'waiting_for_registration';
    case Connected = 'connected';
    case Provisioning = 'provisioning';
    case Provisioned = 'provisioned';
    case Offline = 'offline';
    case Error = 'error';

    public function label(): string
    {
        return match ($this) {
            self::Unassigned => 'Unassigned',
            self::Discovered => 'Ready to use',
            self::WaitingForRegistration => 'Waiting for Registration',
            self::Connected => 'Connected',
            self::Provisioning => 'Provisioning',
            self::Provisioned => 'Provisioned',
            self::Offline => 'Offline',
            self::Error => 'Error',
        };
    }

    public function tone(): string
    {
        return match ($this) {
            self::Connected, self::Provisioned => 'success',
            self::Discovered, self::WaitingForRegistration, self::Provisioning => 'warning',
            self::Error => 'danger',
            default => 'muted',
        };
    }

    public function isRegistered(): bool
    {
        return in_array($this, [self::Connected, self::Provisioned], true);
    }

    public function isOnline(): bool
    {
        return $this->isRegistered();
    }
}
