<?php

namespace App\Ark\Operations\Communications;

use App\Ark\Operations\Settings\ShopDisplayTimezone;
use App\Ark\Operations\Telephony\TelephonyCallFlowSettings;
use Carbon\CarbonImmutable;

/**
 * Projection for SMS reply scheduling UX — not contact evidence.
 *
 * @phpstan-type PendingSchedule array{
 *     id: int,
 *     scheduled_for: string,
 *     scheduled_for_label: string,
 *     preview: string,
 * }
 * @phpstan-type MorningSlot array{
 *     scheduled_for: string,
 *     label: string,
 *     day_key: string,
 * }
 */
final class ScheduledOutboundSmsProjection
{
    /**
     * @return array{
     *     shop_is_open: bool,
     *     next_open_morning_label: string,
     *     upcoming: list<MorningSlot>,
     *     pending: ?PendingSchedule,
     * }
     */
    public function forCustomer(int $customerId): array
    {
        $flow = TelephonyCallFlowSettings::fromShopSettings();
        $upcoming = TomorrowMorningSchedule::upcomingOpenMornings();
        $pending = ScheduledOutboundMessage::query()
            ->where('customer_id', $customerId)
            ->where('type', ScheduledOutboundMessageType::SmsReply)
            ->where('status', ScheduledOutboundMessageStatus::Scheduled)
            ->orderByDesc('id')
            ->first();

        $preview = '';

        if ($pending !== null) {
            $body = trim((string) (($pending->payload_json['body'] ?? '') ?: ''));
            $preview = mb_strlen($body) > 80 ? mb_substr($body, 0, 77).'…' : $body;
        }

        return [
            'shop_is_open' => $flow->isOpenAt(CarbonImmutable::now($flow->timezone())),
            'next_open_morning_label' => $upcoming[0]['label'] ?? 'Next open morning · 8:00 AM',
            'upcoming' => $upcoming,
            'pending' => $pending === null ? null : [
                'id' => $pending->id,
                'scheduled_for' => $pending->scheduled_for?->utc()->toIso8601String() ?? '',
                'scheduled_for_label' => ShopDisplayTimezone::format($pending->scheduled_for, 'D M j · g:i A')
                    ?? ($upcoming[0]['label'] ?? 'Next open morning · 8:00 AM'),
                'preview' => $preview,
            ],
        ];
    }
}
