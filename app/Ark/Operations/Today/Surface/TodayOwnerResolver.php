<?php

namespace App\Ark\Operations\Today\Surface;

use App\Ark\Operations\RepairOrders\RepairOrder;
use App\Ark\Runtime\Authorization\ArkRole;
use App\Models\User;
use Illuminate\Support\Str;

final class TodayOwnerResolver
{
    private ?User $growthOwner = null;

    private ?User $defaultAdvisor = null;

    public function growthOwnerLabel(): string
    {
        return $this->firstName($this->growthOwnerUser());
    }

    public function defaultAdvisorLabel(): string
    {
        return $this->firstName($this->defaultAdvisorUser());
    }

    public function forRepairOrder(?RepairOrder $repairOrder): string
    {
        if ($repairOrder?->assignedTechnician !== null) {
            return $this->firstName($repairOrder->assignedTechnician);
        }

        return $this->defaultAdvisorLabel();
    }

    public function forUser(?User $user): string
    {
        return $this->firstName($user) ?: $this->defaultAdvisorLabel();
    }

    public function firstName(?User $user): string
    {
        if ($user === null) {
            return 'Team';
        }

        $parts = preg_split('/\s+/', trim((string) $user->name), 2) ?: [];

        return $parts[0] !== '' ? $parts[0] : 'Team';
    }

    private function growthOwnerUser(): ?User
    {
        if ($this->growthOwner !== null) {
            return $this->growthOwner;
        }

        $this->growthOwner = User::query()
            ->active()
            ->whereHas('roles', fn ($query) => $query->where('name', ArkRole::Admin->value))
            ->orderBy('name')
            ->first();

        return $this->growthOwner;
    }

    private function defaultAdvisorUser(): ?User
    {
        if ($this->defaultAdvisor !== null) {
            return $this->defaultAdvisor;
        }

        $this->defaultAdvisor = User::query()
            ->active()
            ->whereHas('roles', fn ($query) => $query->whereIn('name', [ArkRole::Advisor->value, ArkRole::Admin->value]))
            ->orderBy('name')
            ->first();

        return $this->defaultAdvisor;
    }
}
