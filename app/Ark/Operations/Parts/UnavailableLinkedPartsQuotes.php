<?php

namespace App\Ark\Operations\Parts;

use App\Ark\Operations\RepairOrders\RepairOrder;
use App\Models\User;

/**
 * Stock Core has no catalog adapter. The repair order still opens.
 */
final class UnavailableLinkedPartsQuotes implements LinkedPartsQuotes
{
    public function available(): bool
    {
        return false;
    }

    public function present(RepairOrder $repairOrder): array
    {
        return [
            'available' => false,
            'provider_label' => '',
            'open_note' => '',
            'current' => null,
            'history' => [],
        ];
    }

    public function refresh(RepairOrder $repairOrder, User $user): array
    {
        return $this->unavailable();
    }

    public function candidates(RepairOrder $repairOrder, User $user): array
    {
        return $this->unavailable();
    }

    public function link(RepairOrder $repairOrder, User $user, string $externalId): array
    {
        return $this->unavailable();
    }

    public function makeCurrent(RepairOrder $repairOrder, User $user, string $linkId): array
    {
        return $this->unavailable();
    }

    /**
     * @return array{ok: false, available: false, message: string}
     */
    private function unavailable(): array
    {
        return [
            'ok' => false,
            'available' => false,
            'message' => 'No parts catalog is connected for linked quotes.',
        ];
    }
}
