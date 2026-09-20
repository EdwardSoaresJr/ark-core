<?php

namespace App\Ark\Operations\RepairOrders;

use App\Ark\Operations\Financial\RepairOrderBalanceProjection;
use App\Ark\Operations\Financial\RepairOrderCloseoutAuthority;
use App\Ark\Operations\Staff\SoloShopOperations;
use App\Models\User;

final class RepairOrderLifecycleSelectCache
{
    /** @var list<string>|null */
    private ?array $actorRoleNames = null;

    private ?bool $lacksEstimateLines = null;

    private ?bool $requiresTechnicianAssignment = null;

    private ?bool $hasUnresolvedApprovedParts = null;

    private ?string $partsBlockerSummary = null;

    private ?string $standardCloseBlockingReason = null;

    public function __construct(
        private readonly RepairOrder $repairOrder,
        private readonly ?User $actor,
        private readonly SoloShopOperations $soloShop,
        private readonly RepairOrderCloseoutAuthority $closeout,
        private readonly ?RepairOrderBalanceProjection $balanceProjection = null,
    ) {
        $this->repairOrder->loadMissing(['lines', 'lines.concern']);
    }

    /**
     * @return list<string>
     */
    public function actorRoleNames(): array
    {
        if ($this->actorRoleNames !== null) {
            return $this->actorRoleNames;
        }

        if ($this->actor === null) {
            return $this->actorRoleNames = [];
        }

        $this->actor->loadMissing('roles');

        return $this->actorRoleNames = $this->actor->roles->pluck('name')->all();
    }

    public function lacksEstimateLines(): bool
    {
        return $this->lacksEstimateLines ??= $this->repairOrder->lines->isEmpty();
    }

    public function requiresTechnicianAssignment(): bool
    {
        return $this->requiresTechnicianAssignment ??= $this->soloShop->requiresTechnicianAssignment();
    }

    public function hasUnresolvedApprovedParts(): bool
    {
        return $this->hasUnresolvedApprovedParts ??= $this->repairOrder->hasUnresolvedApprovedParts();
    }

    public function partsBlockerSummary(): ?string
    {
        return $this->partsBlockerSummary ??= $this->repairOrder->partsBlockerSummary();
    }

    public function standardCloseBlockingReason(): ?string
    {
        return $this->standardCloseBlockingReason ??= $this->closeout->blockingReason(
            $this->repairOrder,
            balance: $this->balanceProjection?->balance,
            invoice: $this->balanceProjection?->invoice,
        );
    }
}
