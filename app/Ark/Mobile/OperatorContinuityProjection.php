<?php

namespace App\Ark\Mobile;

use App\Ark\Operations\Observations\OperationalObservationStream;
use App\Ark\Operations\Observations\OperationalObservationStreamEntry;
use App\Ark\Operations\Observations\OperationalObservationType;
use App\Ark\Operations\Shop\ShopTodayPulseProjection;
use App\Ark\Operations\Workstations\StationOrientationProjection;
use App\Models\User;
use Illuminate\Support\Carbon;

/**
 * Operator continuity — projection only.
 *
 * Continuity is not authority. There is no continuity database and no continuity
 * truth store. This class composes existing authorities and observations into one
 * snapshot that answers: given what ARK currently knows, what must this operator
 * not lose?
 *
 * Surfaces (home, badge, push, VVX microbrowser, watch) consume this projection.
 * Push is one transport; {@see Push\MobilePushService} delivers packets only.
 */
final class OperatorContinuityProjection
{
    private const MAX_MOMENTS = 8;

    public function __construct(
        private readonly MobileStaffAccess $access,
        private readonly MobileUserPresenter $userPresenter,
        private readonly OperationalObservationStream $observationStream,
        private readonly MobileObservationStreamProjection $observationStreamPresentation,
        private readonly MobileWorkProjection $work,
        private readonly StationOrientationProjection $stationOrientation,
        private readonly ShopTodayPulseProjection $todayPulse,
    ) {}

    /**
     * One compact continuity snapshot — home, widgets, badges, and watch consume the same payload.
     *
     * @return array<string, mixed>
     */
    public function forUser(User $user): array
    {
        $shell = $this->userPresenter->presentShell($user);
        $profile = (string) ($shell['home_profile'] ?? 'staff');
        $since = $user->last_seen_at instanceof Carbon ? $user->last_seen_at : null;
        $updatedAt = now();

        if ($profile === 'technician') {
            return $this->technicianSnapshot($user, $updatedAt);
        }

        if (! $this->access->canViewShopAttention($user)) {
            return $this->emptySnapshot($user, $since, $updatedAt);
        }

        $stream = $this->observationStreamPresentation->forUser($user);
        $entries = $this->observationStream->active($since, self::MAX_MOMENTS);
        $count = $this->observationStream->activeCount($since);
        $moments = $stream['items'] ?? [];
        $continuity = $this->continuityMeta($count, $entries, $since, $updatedAt);

        return [
            'badge' => $count,
            'continuity' => $continuity,
            'moments' => $moments,
            'next_best_action' => $this->nextBestActionFromMoment($moments[0] ?? null),
            'today' => $this->todayPulse->forUser($user),
            'station' => $this->stationOrientation->forCurrentOperator($user),
            'poll_after_seconds' => 45,
        ];
    }

    /**
     * @deprecated Prefer {@see forUser()} — retained for lightweight badge-only polls.
     *
     * @return array{
     *     count: int,
     *     metric: string,
     *     since_label: string|null,
     *     poll_after_seconds: int,
     * }
     */
    public function badgeForUser(User $user): array
    {
        $snapshot = $this->forUser($user);
        $continuity = is_array($snapshot['continuity'] ?? null) ? $snapshot['continuity'] : [];
        $since = isset($continuity['since']) ? Carbon::parse((string) $continuity['since']) : null;

        return [
            'count' => (int) ($snapshot['badge'] ?? 0),
            'metric' => 'operational_continuity',
            'since_label' => $since !== null ? 'Since you last checked' : null,
            'poll_after_seconds' => (int) ($snapshot['poll_after_seconds'] ?? 45),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function technicianSnapshot(User $user, Carbon $updatedAt): array
    {
        $work = $this->work->forUser($user);
        $items = collect($work['items'] ?? [])
            ->take(self::MAX_MOMENTS)
            ->map(fn (array $item): array => [
                'kind' => 'repair_order',
                'observation' => 'repair_order_waiting',
                'title' => (string) ($item['customer_name'] ?? 'Customer'),
                'subtitle' => trim(collect([
                    $item['vehicle_label'] ?? null,
                    $item['next_action'] ?? null,
                ])->filter()->implode(' · ')),
                'detail' => (string) ($item['concern_summary'] ?? ''),
                'repair_order_id' => $item['repair_order_id'] ?? null,
                'deep_link' => 'repair_order',
                'tone' => 'info',
            ])
            ->values()
            ->all();

        $count = (int) ($work['count'] ?? 0);

        return [
            'badge' => $count,
            'continuity' => [
                'count' => $count,
                'highest_priority' => $count > 0 ? OperationalObservationType::RepairOrderWaiting->value : null,
                'oldest_age_seconds' => null,
                'updated_at' => $updatedAt->toIso8601String(),
                'since' => null,
            ],
            'moments' => $items,
            'next_best_action' => $this->nextBestActionFromMoment($items[0] ?? null),
            'today' => $this->todayPulse->forUser($user),
            'station' => $this->stationOrientation->forCurrentOperator($user),
            'poll_after_seconds' => 45,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function emptySnapshot(User $user, ?Carbon $since, Carbon $updatedAt): array
    {
        return [
            'badge' => 0,
            'continuity' => [
                'count' => 0,
                'highest_priority' => null,
                'oldest_age_seconds' => null,
                'updated_at' => $updatedAt->toIso8601String(),
                'since' => $since?->toIso8601String(),
            ],
            'moments' => [],
            'next_best_action' => null,
            'today' => $this->todayPulse->forUser($user),
            'station' => $this->stationOrientation->forCurrentOperator($user),
            'poll_after_seconds' => 45,
        ];
    }

    /**
     * @param  \Illuminate\Support\Collection<int, OperationalObservationStreamEntry>  $entries
     * @return array<string, mixed>
     */
    private function continuityMeta(
        int $count,
        $entries,
        ?Carbon $since,
        Carbon $updatedAt,
    ): array {
        $highestPriority = null;
        $oldestOccurredAt = null;

        foreach ($entries as $entry) {
            $type = OperationalObservationType::tryFrom((string) $entry->observation_type);

            if ($type === null) {
                continue;
            }

            if ($highestPriority === null || $this->priorityRank($type) > $this->priorityRank($highestPriority)) {
                $highestPriority = $type;
            }

            if ($oldestOccurredAt === null || $entry->occurred_at->lt($oldestOccurredAt)) {
                $oldestOccurredAt = $entry->occurred_at;
            }
        }

        return [
            'count' => $count,
            'highest_priority' => $highestPriority?->value,
            'oldest_age_seconds' => $oldestOccurredAt !== null
                ? (int) $oldestOccurredAt->diffInSeconds($updatedAt)
                : null,
            'updated_at' => $updatedAt->toIso8601String(),
            'since' => $since?->toIso8601String(),
        ];
    }

    /**
     * @param  array<string, mixed>|null  $moment
     * @return array<string, mixed>|null
     */
    private function nextBestActionFromMoment(?array $moment): ?array
    {
        if ($moment === null) {
            return null;
        }

        $label = trim((string) ($moment['subtitle'] ?? ''));

        if ($label === '') {
            $label = (string) ($moment['title'] ?? 'Review next item');
        }

        return [
            'label' => $label,
            'observation' => $moment['observation'] ?? null,
            'deep_link' => $moment['deep_link'] ?? null,
            'customer_id' => $moment['customer_id'] ?? null,
            'conversation_id' => $moment['conversation_id'] ?? null,
            'repair_order_id' => $moment['repair_order_id'] ?? null,
        ];
    }

    private function priorityRank(OperationalObservationType $type): int
    {
        return match ($type) {
            OperationalObservationType::IncomingCall => 1000,
            OperationalObservationType::CustomerWaitingResponse,
            OperationalObservationType::CustomerSentMultipleMessages => 900,
            OperationalObservationType::CustomerReplied => 850,
            OperationalObservationType::RepairOrderOverdue,
            OperationalObservationType::AppointmentMissed => 800,
            OperationalObservationType::EstimateViewedMultipleTimes,
            OperationalObservationType::ConversationUnassigned => 750,
            OperationalObservationType::CustomerArrived => 700,
            OperationalObservationType::WarrantyApproved,
            OperationalObservationType::PartsArrived,
            OperationalObservationType::VehicleReady,
            OperationalObservationType::PaymentReceived => 650,
            OperationalObservationType::AppointmentUpcoming => 600,
            default => 500,
        };
    }
}
