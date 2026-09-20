<?php

namespace App\Ark\Operations\Communications;

use App\Ark\Operations\Settings\ShopDisplayTimezone;
use App\Ark\Operations\Telephony\TelephonyCallFlowSettings;
use Carbon\CarbonImmutable;

/**
 * Projection for Send Estimate scheduling UX — not contact evidence.
 *
 * @phpstan-type PendingSchedule array{
 *     id: int,
 *     scheduled_for: string,
 *     scheduled_for_label: string,
 *     delivery_mode: string,
 * }
 * @phpstan-type MorningSlot array{
 *     scheduled_for: string,
 *     label: string,
 *     day_key: string,
 * }
 */
final class ScheduledOutboundEstimateProjection
{
    /**
     * @return array{
     *     shop_is_open: bool,
     *     next_open_morning_label: string,
     *     upcoming: list<MorningSlot>,
     *     pending: ?PendingSchedule,
     * }
     */
    public function forRepairOrder(int $repairOrderId): array
    {
        $flow = TelephonyCallFlowSettings::fromShopSettings();
        $upcoming = TomorrowMorningSchedule::upcomingOpenMornings();
        $pending = ScheduledOutboundMessage::query()
            ->where('repair_order_id', $repairOrderId)
            ->where('type', ScheduledOutboundMessageType::EstimateSend)
            ->where('status', ScheduledOutboundMessageStatus::Scheduled)
            ->orderByDesc('id')
            ->first();

        return [
            'shop_is_open' => $flow->isOpenAt(CarbonImmutable::now($flow->timezone())),
            'next_open_morning_label' => $upcoming[0]['label'] ?? 'Next open morning · 8:00 AM',
            'upcoming' => $upcoming,
            'pending' => $pending === null ? null : [
                'id' => $pending->id,
                'scheduled_for' => $pending->scheduled_for?->utc()->toIso8601String() ?? '',
                'scheduled_for_label' => ShopDisplayTimezone::format($pending->scheduled_for, 'D M j · g:i A')
                    ?? ($upcoming[0]['label'] ?? 'Next open morning · 8:00 AM'),
                'delivery_mode' => $pending->delivery_mode->value,
            ],
        ];
    }
}
