<?php

namespace App\Ark\Operations\Communications;

use App\Ark\Runtime\Authorization\ArkCapability;
use App\Models\User;
use Illuminate\Support\Carbon;

final class CommsChannelStripResolver
{
    public function __construct(
        private readonly CommunicationsQueueResolver $communicationsQueue,
        private readonly CommunicationsQueueChannelProjection $communicationsChannels,
    ) {}

    /**
     * @return list<array{slug: string, label: string, count: int, url: string, active: bool}>
     */
    public function tabsFor(?User $user, ?Carbon $previousLastSeenAt = null): array
    {
        if (! $user instanceof User || ! $user->can(ArkCapability::OperationsAccess->value)) {
            return [];
        }

        $queue = $this->communicationsQueue->resolveAttention($user, $previousLastSeenAt);
        $needsAttentionNow = array_values(array_filter(
            $queue['needs_attention'],
            fn (array $row): bool => (bool) ($row['matched'] ?? false),
        ));

        $data = $this->communicationsChannels->apply([
            ...$queue,
            'needs_attention_now' => $needsAttentionNow,
        ], CommunicationsSurfaceChannel::All);

        $tabs = $data['comms_channel_tabs'] ?? [];

        return is_array($tabs) ? $tabs : [];
    }
}
