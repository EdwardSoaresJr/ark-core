<?php

namespace App\Ark\Operations\Recommendations;

use App\Ark\Operations\RepairOrders\RepairOrder;
use App\Ark\Operations\RepairOrders\RepairOrderConcern;
use App\Models\User;
use Illuminate\Validation\ValidationException;

final class RecordRecommendationPresentationAction
{
    public function __construct(
        private readonly RecordRecommendationEventAction $events,
    ) {}

    public function handle(
        Recommendation $recommendation,
        RepairOrder $repairOrder,
        ?User $actor = null,
        ?RepairOrderConcern $concern = null,
        ?int $amountCents = null,
        ?string $note = null,
    ): RecommendationEvent {
        $this->assertSameVehicle($recommendation, $repairOrder);

        $link = $recommendation->estimateLinkForRepairOrder($repairOrder);
        $concern ??= $link?->concern;
        $amountCents ??= $link?->amount_cents ?? $this->concernAmountCents($concern);

        return $this->events->handle(
            $recommendation,
            RecommendationEventType::Presented,
            actor: $actor,
            repairOrder: $repairOrder,
            concern: $concern,
            amountCents: $amountCents,
            note: $note,
        );
    }

    private function assertSameVehicle(Recommendation $recommendation, RepairOrder $repairOrder): void
    {
        if ((int) $recommendation->vehicle_id !== (int) $repairOrder->vehicle_id
            || (int) $recommendation->customer_id !== (int) $repairOrder->customer_id) {
            throw ValidationException::withMessages([
                'recommendation' => 'That recommendation does not belong to this repair order.',
            ]);
        }
    }

    private function concernAmountCents(?RepairOrderConcern $concern): ?int
    {
        if ($concern === null) {
            return null;
        }

        $concern->loadMissing('lines');

        $cents = (int) $concern->lines->sum(fn ($line): int => (int) $line->total_cents);

        return $cents > 0 ? $cents : null;
    }
}
