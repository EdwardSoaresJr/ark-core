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
     * Action results are not the panel. `ok` and `message` describe the attempt.
     * After a successful attempt, the repair order reloads `present()`.
     * A failed attempt must not be copied over the quote already on screen.
     *
     * @return array{ok: bool, message: string|null}
     */
    public function refresh(RepairOrder $repairOrder, User $user): array;

    /**
     * @return array{ok: bool, message: string|null, candidates?: list<array<string, mixed>>}
     */
    public function candidates(RepairOrder $repairOrder, User $user): array;

    /**
     * @return array{ok: bool, message: string|null}
     */
    public function link(RepairOrder $repairOrder, User $user, string $externalId): array;

    /**
     * @return array{ok: bool, message: string|null}
     */
    public function makeCurrent(RepairOrder $repairOrder, User $user, string $linkId): array;
}
