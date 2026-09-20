<?php

namespace App\Ark\Mobile;

use App\Ark\Operations\Observations\OperationalObservationStream;
use App\Ark\Operations\Observations\OperationalObservationStreamEntry;
use App\Ark\Operations\Observations\OperationalObservationType;
use App\Models\User;
use Illuminate\Support\Carbon;

/**
 * Presents the operational observation stream for Portable Station surfaces.
 */
final class MobileObservationStreamProjection
{
    private const MAX_ITEMS = 8;

    public function __construct(
        private readonly OperationalObservationStream $stream,
        private readonly MobileStaffAccess $access,
    ) {}

    /**
     * @return array{
     *     items: list<array<string, mixed>>,
     *     count: int,
     *     since_label: string|null,
     * }
     */
    public function forUser(User $user): array
    {
        if (! $this->access->canViewShopAttention($user)) {
            return ['items' => [], 'count' => 0, 'since_label' => null];
        }

        $since = $user->last_seen_at instanceof Carbon ? $user->last_seen_at : null;

        $entries = $this->stream->active($since, self::MAX_ITEMS);

        $items = $entries
            ->map(fn (OperationalObservationStreamEntry $entry): array => $this->presentEntry($entry))
            ->values()
            ->all();

        return [
            'items' => $items,
            'count' => $this->stream->activeCount($since),
            'since_label' => $since !== null ? 'Since you last checked' : null,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function presentEntry(OperationalObservationStreamEntry $entry): array
    {
        $type = OperationalObservationType::tryFrom((string) $entry->observation_type);

        return [
            'kind' => 'observation',
            'title' => $entry->headline,
            'subtitle' => $entry->description ?? '',
            'detail' => $entry->occurred_at->diffForHumans(),
            'customer_id' => $entry->customer_id,
            'repair_order_id' => MobileRepairOrderRouteId::normalize($entry->repair_order_id),
            'conversation_id' => $entry->conversation_id,
            'call_session_id' => null,
            'observation' => $entry->observation_type,
            'category' => $type?->category() ?? 'shop',
            'tone' => $type?->tone() ?? 'info',
            'deep_link' => $this->legacyDeepLink($entry),
            'route' => $this->companionRoute($entry),
            'occurred_at' => $entry->occurred_at->toIso8601String(),
        ];
    }

    private function legacyDeepLink(OperationalObservationStreamEntry $entry): string
    {
        return $entry->customer_id !== null ? 'customer' : 'attention';
    }

    private function companionRoute(OperationalObservationStreamEntry $entry): string
    {
        if ($entry->conversation_id !== null) {
            return MobileCompanionDeepLink::conversation((int) $entry->conversation_id);
        }

        if ($entry->repair_order_id !== null) {
            return MobileCompanionDeepLink::repairOrder(
                (int) MobileRepairOrderRouteId::normalize($entry->repair_order_id)
            );
        }

        if ($entry->customer_id !== null) {
            return MobileCompanionDeepLink::customer((int) $entry->customer_id);
        }

        return MobileCompanionDeepLink::home();
    }
}
