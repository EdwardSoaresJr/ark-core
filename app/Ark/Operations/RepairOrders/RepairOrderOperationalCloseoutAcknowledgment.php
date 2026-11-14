<?php

namespace App\Ark\Operations\RepairOrders;

use App\Ark\Operations\Events\OperationalEvent;
use App\Ark\Operations\Events\OperationalEventName;
use App\Ark\Operations\Events\OperationalEventRecorder;
use App\Models\User;

/**
 * Server-side accountability gate: unfinished operational work may proceed only with
 * a documented reason (or a prior acknowledgment of the same outstanding fingerprint).
 * Does not mark unfinished work complete.
 */
final class RepairOrderOperationalCloseoutAcknowledgment
{
    public const MISSING_REASON = 'Unfinished work remains on this repair order. Explain why you are proceeding before continuing.';

    public const FINGERPRINT_MISMATCH = 'Outstanding work changed since this acknowledgment. Review the current unfinished items and confirm again.';

    public const REASON_TOO_SHORT = 'Add a short reason explaining why you are proceeding with unfinished work.';

    private const MIN_REASON_LENGTH = 8;

    public function __construct(
        private readonly RepairOrderOperationalOutstandingWork $outstanding,
        private readonly OperationalEventRecorder $events,
    ) {}

    /**
     * @return array{
     *     required: bool,
     *     fingerprint: string,
     *     items: list<array{key: string, kind: string, title: string, detail: string}>,
     *     message: string|null,
     * }|null
     */
    public function challenge(
        RepairOrder $repairOrder,
        string $toStatusSlug,
        ?string $closeVariantKey = null,
        ?string $reason = null,
        ?string $fingerprint = null,
    ): ?array {
        if (! $this->outstanding->appliesTo($toStatusSlug, $closeVariantKey)) {
            return null;
        }

        $items = $this->outstanding->items($repairOrder);

        if ($items === []) {
            return null;
        }

        $currentFingerprint = $this->outstanding->fingerprint($repairOrder);

        if ($this->hasMatchingPriorAcknowledgment($repairOrder, $currentFingerprint)) {
            return null;
        }

        $trimmedReason = trim((string) $reason);
        $providedFingerprint = trim((string) $fingerprint);

        if ($trimmedReason === '') {
            return [
                'required' => true,
                'fingerprint' => $currentFingerprint,
                'items' => $items,
                'message' => self::MISSING_REASON,
            ];
        }

        if (mb_strlen($trimmedReason) < self::MIN_REASON_LENGTH) {
            return [
                'required' => true,
                'fingerprint' => $currentFingerprint,
                'items' => $items,
                'message' => self::REASON_TOO_SHORT,
            ];
        }

        if ($providedFingerprint !== '' && ! hash_equals($currentFingerprint, $providedFingerprint)) {
            return [
                'required' => true,
                'fingerprint' => $currentFingerprint,
                'items' => $items,
                'message' => self::FINGERPRINT_MISMATCH,
            ];
        }

        return [
            'required' => false,
            'fingerprint' => $currentFingerprint,
            'items' => $items,
            'message' => null,
            'reason' => $trimmedReason,
        ];
    }

    /**
     * @param  list<array{key: string, kind: string, title: string, detail: string}>  $items
     */
    public function record(
        RepairOrder $repairOrder,
        string $toStatusSlug,
        ?string $closeVariantKey,
        string $reason,
        string $fingerprint,
        array $items,
        ?User $actor,
    ): OperationalEvent {
        return $this->events->record(
            OperationalEventName::RepairOrderOperationalCloseoutAcknowledged,
            $repairOrder,
            actor: $actor,
            payload: [
                'to_status' => $toStatusSlug,
                'close_variant_key' => $closeVariantKey,
                'reason' => $reason,
                'fingerprint' => $fingerprint,
                'outstanding_items' => $items,
                'preserved_states' => true,
            ],
        );
    }

    private function hasMatchingPriorAcknowledgment(RepairOrder $repairOrder, string $fingerprint): bool
    {
        $latest = OperationalEvent::query()
            ->where('event_name', OperationalEventName::RepairOrderOperationalCloseoutAcknowledged->value)
            ->where('aggregate_type', $repairOrder->getMorphClass())
            ->where('aggregate_id', $repairOrder->getKey())
            ->orderByDesc('id')
            ->first();

        if ($latest === null) {
            return false;
        }

        $prior = (string) data_get($latest->payload_json, 'fingerprint', '');

        return $prior !== '' && hash_equals($fingerprint, $prior);
    }
}
