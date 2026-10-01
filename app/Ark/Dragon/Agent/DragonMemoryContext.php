<?php

namespace App\Ark\Dragon\Agent;

use App\Ark\Operations\Workstations\Workstation;
use App\Ark\Runtime\Authorization\ArkCapability;
use App\Ark\Runtime\Authorization\ArkRole;
use App\Models\User;

final class DragonMemoryContext
{
    public function __construct(
        public readonly ?User $user,
        public readonly ?Workstation $workstation,
        public readonly ?DragonAgentConversation $conversation,
        public readonly string $taughtBy,
        public readonly string $client,
    ) {}

    public static function empty(): self
    {
        return new self(null, null, null, 'system', 'unknown');
    }

    public function canWriteCompany(): bool
    {
        if ($this->user === null) {
            return false;
        }

        return $this->user->can(ArkCapability::SettingsManage->value)
            || $this->user->hasRole(ArkRole::Admin->value);
    }

    public function canWriteWorkstation(?Workstation $workstation = null): bool
    {
        $station = $workstation ?? $this->workstation;
        if ($station === null) {
            return false;
        }
        if ($this->canWriteCompany()) {
            return true;
        }
        if ($this->user !== null && (int) $station->current_operator_user_id === (int) $this->user->id) {
            return true;
        }

        return $this->client === 'station';
    }

    public function canWriteUser(?int $userId = null): bool
    {
        if ($this->user === null) {
            return false;
        }
        $target = $userId ?? $this->user->id;

        return (int) $target === (int) $this->user->id;
    }
}
