<?php

namespace App\Ark\Operations\Parts;

use App\Ark\Operations\RepairOrders\RepairOrder;
use App\Models\User;

/**
 * Provider-neutral linked external parts quotes for one repair order.
 * The catalog adapter stores and checks the external identifier. Core displays it.
 */
interface LinkedPartsQuotes
{
    public function available(): bool;

    /**
     * @return array{available: bool, provider_label: string, open_note: string, current: array<string, mixed>|null, history: list<array<string, mixed>>}
     */
    public function present(RepairOrder $repairOrder): array;

    /**
     * @return array<string, mixed>
     */
    public function refresh(RepairOrder $repairOrder, User $user): array;

    /**
     * @return array<string, mixed>
     */
    public function candidates(RepairOrder $repairOrder, User $user): array;

    /**
     * @return array<string, mixed>
     */
    public function link(RepairOrder $repairOrder, User $user, string $externalId): array;

    /**
     * @return array<string, mixed>
     */
    public function makeCurrent(RepairOrder $repairOrder, User $user, string $linkId): array;
}
