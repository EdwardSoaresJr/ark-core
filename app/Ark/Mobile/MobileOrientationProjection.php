<?php

namespace App\Ark\Mobile;

use App\Models\User;

/**
 * Portable Station home — observation stream first, then attention/work fallbacks.
 */
final class MobileOrientationProjection
{
    private const MAX_ITEMS = 8;

    public function __construct(
        private readonly MobileUserPresenter $userPresenter,
        private readonly MobileObservationStreamProjection $observationStream,
        private readonly MobileAttentionProjection $attention,
        private readonly MobileWorkProjection $work,
        private readonly OperatorContinuityProjection $continuity,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function forUser(User $user): array
    {
        $shell = $this->userPresenter->presentShell($user);
        $profile = (string) ($shell['home_profile'] ?? 'staff');
        $observationStream = $this->observationStream->forUser($user);
        $attention = $this->attention->forUser($user);
        $continuitySnapshot = $this->continuity->forUser($user);
        $items = $this->priorityItems($user, $profile, $observationStream, $attention);
        $streamCount = (int) ($observationStream['count'] ?? 0);
        $attentionTotal = (int) ($attention['total_count'] ?? 0);
        $workCount = $profile === 'technician' ? count($items) : 0;

        $nextItem = $items[0] ?? null;
        $nextBestAction = is_array($nextItem)
            ? $this->nextBestAction($nextItem)
            : 'You\'re caught up';

        $continuityNext = $continuitySnapshot['next_best_action'] ?? null;
        if (is_array($continuityNext) && filled($continuityNext['label'] ?? null)) {
            $nextBestAction = (string) $continuityNext['label'];
        }

        return [
            'home_profile' => $profile,
            'current_situation' => $this->currentSituation($profile, $streamCount, $attentionTotal, $workCount, $observationStream),
            'context' => (string) ($shell['home_question'] ?? ''),
            'next_best_action' => $nextBestAction,
            'confidence' => $this->confidenceItems($profile, $streamCount, $attentionTotal),
            'actions' => $this->actions($profile, $shell['capabilities'] ?? []),
            'ownership' => (string) $user->name,
            'pressure' => $streamCount + $attentionTotal > 0
                ? ($streamCount + $attentionTotal).' waiting'
                : 'Clear',
            'items' => $items,
            'observation_stream_count' => $streamCount,
            'observation_since_label' => $observationStream['since_label'] ?? null,
            'attention_total' => $attentionTotal,
            'continuity' => $continuitySnapshot,
            'continuity_badge' => $continuitySnapshot['continuity'] ?? null,
            'poll_after_seconds' => 45,
            'push_enabled' => (bool) ($attention['push_enabled'] ?? false),
        ];
    }

    /**
     * @param  array<string, mixed>  $observationStream
     * @param  array<string, mixed>  $attention
     * @return list<array<string, mixed>>
     */
    private function priorityItems(User $user, string $profile, array $observationStream, array $attention): array
    {
        if ($profile === 'technician') {
            return collect($this->work->forUser($user)['items'] ?? [])
                ->take(self::MAX_ITEMS)
                ->map(fn (array $item): array => [
                    'kind' => 'repair_order',
                    'title' => (string) ($item['customer_name'] ?? 'Customer'),
                    'subtitle' => trim(collect([
                        $item['vehicle_label'] ?? null,
                        $item['status_label'] ?? null,
                    ])->filter()->implode(' · ')),
                    'detail' => (string) ($item['concern_summary'] ?? ''),
                    'repair_order_id' => $item['id'] ?? null,
                    'conversation_id' => null,
                    'call_session_id' => null,
                    'deep_link' => 'repair_order',
                    'occurred_at' => null,
                ])
                ->values()
                ->all();
        }

        $items = [];

        foreach ($observationStream['items'] ?? [] as $item) {
            if (! is_array($item)) {
                continue;
            }

            $items[] = $item;

            if (count($items) >= self::MAX_ITEMS) {
                return $items;
            }
        }

        foreach ($attention['sections'] ?? [] as $section) {
            if (! is_array($section)) {
                continue;
            }

            foreach ($section['items'] ?? [] as $item) {
                if (! is_array($item)) {
                    continue;
                }

                $items[] = $item;

                if (count($items) >= self::MAX_ITEMS) {
                    break 2;
                }
            }
        }

        return $items;
    }

    /**
     * @param  array<string, mixed>  $observationStream
     */
    private function currentSituation(
        string $profile,
        int $streamCount,
        int $attentionTotal,
        int $workCount,
        array $observationStream,
    ): string {
        $waiting = $streamCount + $attentionTotal;

        if (in_array($profile, ['manager', 'advisor'], true) && $waiting > 0) {
            $label = $observationStream['since_label'] ?? 'Needs attention';
            $noun = $waiting === 1 ? 'customer' : 'customers';

            return "{$label} · {$waiting} {$noun} waiting on you";
        }

        return match ($profile) {
            'technician' => $workCount > 0
                ? "{$workCount} assigned repair orders need work"
                : 'No assigned repair orders right now',
            'manager', 'advisor' => 'Nothing needs attention right now',
            default => 'Ready to work',
        };
    }

    /**
     * @param  array<string, mixed>  $item
     */
    private function nextBestAction(array $item): string
    {
        $subtitle = trim((string) ($item['subtitle'] ?? ''));

        if ($subtitle !== '') {
            return $subtitle;
        }

        return (string) ($item['title'] ?? 'Review next item');
    }

    /**
     * @return list<string>
     */
    private function confidenceItems(string $profile, int $streamCount, int $attentionTotal): array
    {
        return [];
    }

    /**
     * @param  array<string, mixed>  $capabilities
     * @return list<array<string, mixed>>
     */
    private function actions(string $profile, array $capabilities): array
    {
        // Keys must match bottom-nav tab keys so Home chips switch tabs.
        $actions = [];

        if ($capabilities['intake'] ?? false) {
            $actions[] = ['key' => 'intake', 'label' => 'New intake', 'enabled' => true];
        }

        if ($capabilities['repair_orders'] ?? false) {
            $actions[] = ['key' => 'work', 'label' => 'My work', 'enabled' => true];
        }

        if (in_array($profile, ['advisor', 'manager'], true)) {
            $actions[] = ['key' => 'schedule', 'label' => 'Schedule', 'enabled' => true];
        }

        if (($capabilities['owner_bookend'] ?? false) && $profile === 'manager') {
            $actions[] = ['key' => 'bookend', 'label' => 'Day Review', 'enabled' => true];
        }

        $actions[] = ['key' => 'apps', 'label' => 'All apps', 'enabled' => true];

        return $actions;
    }
}
