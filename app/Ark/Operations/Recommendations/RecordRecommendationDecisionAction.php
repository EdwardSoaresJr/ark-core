<?php

namespace App\Ark\Operations\Recommendations;

use App\Ark\Operations\RepairOrders\RepairOrder;
use App\Ark\Operations\RepairOrders\RepairOrderConcern;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

final class RecordRecommendationDecisionAction
{
    public function __construct(
        private readonly RecordRecommendationEventAction $events,
        private readonly SetRecommendationFollowUpAction $followUp,
    ) {}

    public function handle(
        Recommendation $recommendation,
        RecommendationEventType $decision,
        RepairOrder $repairOrder,
        ?User $actor = null,
        ?RepairOrderConcern $concern = null,
        ?int $amountCents = null,
        ?RecommendationDecisionReason $reason = null,
        ?string $note = null,
        Carbon|string|null $followUpAt = null,
        ?int $followUpOwnerUserId = null,
        bool $presentIfMissing = true,
    ): RecommendationEvent {
        if (! $decision->isDecision()) {
            throw ValidationException::withMessages([
                'decision' => 'That is not a customer decision.',
            ]);
        }

        if ((int) $recommendation->vehicle_id !== (int) $repairOrder->vehicle_id
            || (int) $recommendation->customer_id !== (int) $repairOrder->customer_id) {
            throw ValidationException::withMessages([
                'recommendation' => 'That recommendation does not belong to this repair order.',
            ]);
        }

        if (! $recommendation->isOpen()) {
            throw ValidationException::withMessages([
                'recommendation' => 'Resolved or dismissed recommendations cannot record a new decision.',
            ]);
        }

        $recommendation->loadMissing(['events', 'estimateLinks.concern.lines']);

        $link = $recommendation->estimateLinkForRepairOrder($repairOrder);
        $concern ??= $link?->concern;
        $amountCents ??= $link?->amount_cents ?? $this->concernAmountCents($concern);

        if ($presentIfMissing && ! $this->hasPresentationOnRepairOrder($recommendation, $repairOrder)) {
            $this->events->handle(
                $recommendation,
                RecommendationEventType::Presented,
                actor: $actor,
                repairOrder: $repairOrder,
                concern: $concern,
                amountCents: $amountCents,
            );
            $recommendation->load('events');
        }

        $event = $this->events->handle(
            $recommendation,
            $decision,
            actor: $actor,
            repairOrder: $repairOrder,
            concern: $concern,
            amountCents: $amountCents,
            reason: $reason,
            note: $note,
        );

        if ($decision === RecommendationEventType::Deferred && $followUpAt !== null) {
            $this->followUp->schedule($recommendation, $followUpAt, $actor, $followUpOwnerUserId);
        }

        return $event;
    }

    private function hasPresentationOnRepairOrder(Recommendation $recommendation, RepairOrder $repairOrder): bool
    {
        return $recommendation->events->contains(
            fn (RecommendationEvent $event): bool => $event->type === RecommendationEventType::Presented
                && (int) $event->repair_order_id === (int) $repairOrder->id,
        );
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
