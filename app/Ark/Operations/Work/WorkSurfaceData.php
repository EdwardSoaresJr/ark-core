<?php

namespace App\Ark\Operations\Work;

use App\Ark\Operations\Appointments\UpcomingScheduleProjection;
use App\Ark\Operations\Attention\CustomerDecisionPressure;
use App\Ark\Operations\Communications\CommunicationsQueueChannelProjection;
use App\Ark\Operations\Communications\CommunicationsQueueResolver;
use App\Ark\Operations\Communications\CommunicationsSurfaceChannel;
use App\Ark\Operations\OperationsFeatures;
use App\Ark\Operations\Workboard\ShopPressureProjection;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

final class WorkSurfaceData
{
    public function __construct(
        private readonly CommunicationsQueueResolver $communicationsQueue,
        private readonly ShopPressureProjection $shopPressure,
        private readonly CustomerDecisionPressure $customerDecisionPressure,
        private readonly AdvisorWorkProjection $advisorWork,
        private readonly ScheduledDecisionProjection $scheduledDecisions,
        private readonly UpcomingScheduleProjection $upcomingSchedule,
        private readonly CommunicationsQueueChannelProjection $communicationsChannels,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function resolve(Request $request, bool $includeFullCommsQueue = false): array
    {
        $previousLastSeen = $request->attributes->get('operations.previous_last_seen_at');
        $previousLastSeenAt = $previousLastSeen instanceof Carbon
            ? $previousLastSeen
            : (is_string($previousLastSeen) ? Carbon::parse($previousLastSeen) : null);

        $user = $request->user();
        $queue = $includeFullCommsQueue
            ? $this->communicationsQueue->resolve($user, $previousLastSeenAt)
            : $this->communicationsQueue->resolveAttention($user, $previousLastSeenAt);
        $needsAttentionNow = array_values(array_filter(
            $queue['needs_attention'],
            fn (array $row): bool => (bool) ($row['matched'] ?? false),
        ));

        $sinceLastShift = $queue['since_last_shift'] ?? [];
        $unknown = $queue['unknown'] ?? [];
        $customerDecisionPressure = $this->customerDecisionPressure->resolve($user);
        $appointmentsEnabled = OperationsFeatures::appointmentsEnabled();
        $customerPressureCount = count($sinceLastShift) + count($needsAttentionNow) + count($unknown);
        $summary = is_array($queue['summary'] ?? null) ? $queue['summary'] : [];

        $data = [
            ...$queue,
            'needs_attention_now' => $needsAttentionNow,
            'shop_pressure' => $this->shopPressure->cardsFor($user),
            'comms_pressure_metric' => [
                'label' => 'Communications',
                'value' => $customerPressureCount,
                'hint' => $customerPressureCount > 0
                    ? (string) ($summary['breakdown_label'] ?? 'Needs reply')
                    : 'All clear',
                'tone' => 'customer',
                'url' => \App\Ark\Operations\Communications\CommunicationsNeedsYou::url(),
            ],
            'customer_decision_pressure' => $customerDecisionPressure,
            'follow_ups' => $this->advisorWork->followUpsForShop($user),
            'scheduled_decisions' => $this->scheduledDecisions->fromRows(
                $customerDecisionPressure['scheduled_later'] ?? [],
                $user,
            ),
            'tasks' => $this->advisorWork->tasksForShop($user),
            'appointments_enabled' => $appointmentsEnabled,
            'schedule' => $appointmentsEnabled
                ? $this->upcomingSchedule->resolve(viewer: $user)
                : ['today' => [], 'tomorrow' => [], 'upcoming' => [], 'total_count' => 0],
            'customer_pressure_count' => $customerPressureCount,
        ];

        return $this->communicationsChannels->apply($data, CommunicationsSurfaceChannel::All);
    }
}
