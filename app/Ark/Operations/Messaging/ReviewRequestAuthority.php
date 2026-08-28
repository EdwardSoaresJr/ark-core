<?php

namespace App\Ark\Operations\Messaging;

use App\Ark\Operations\Communications\OperationalCommunicationChannel;
use App\Ark\Operations\Communications\OperationalCommunicationDirection;
use App\Ark\Operations\Conversations\ConversationMessage;
use App\Ark\Operations\RepairOrders\RepairOrder;
use App\Ark\Operations\Settings\ShopDisplayTimezone;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

final class ReviewRequestAuthority
{
    public const METADATA_KIND = 'review_request';

    /**
     * v1 idempotency is RO-scoped (ConversationMessage kind + legacy RO columns).
     *
     * Future production hardening (not this slice): prefer customer + recent window
     * (e.g. one request per ~30 days) with an explicit advisor override — so closing
     * several ROs the same day does not spam Google review requests.
     */

    /**
     * @return Collection<int, ConversationMessage>
     */
    public function messagesFor(RepairOrder $repairOrder): Collection
    {
        return ConversationMessage::query()
            ->where('direction', OperationalCommunicationDirection::Outbound)
            ->where(function ($query) use ($repairOrder): void {
                $query->where('metadata->kind', self::METADATA_KIND)
                    ->where('metadata->repair_order_id', $repairOrder->id);
            })
            ->with(['participant.user'])
            ->orderBy('occurred_at')
            ->orderBy('id')
            ->get();
    }

    public function latestMessage(RepairOrder $repairOrder): ?ConversationMessage
    {
        return $this->messagesFor($repairOrder)->last();
    }

    public function alreadySent(RepairOrder $repairOrder): bool
    {
        if ($this->latestMessage($repairOrder) !== null) {
            return true;
        }

        return $repairOrder->review_request_sent === true
            && $repairOrder->review_request_recorded_at instanceof Carbon;
    }

    /**
     * @return list<array{
     *     channel_label: string,
     *     when_label: string,
     *     by_label: ?string,
     * }>
     */
    public function historyEntries(RepairOrder $repairOrder): array
    {
        $messages = $this->messagesFor($repairOrder);

        if ($messages->isNotEmpty()) {
            $actorIds = $messages
                ->map(fn (ConversationMessage $message): mixed => $message->metadata['actor_user_id'] ?? null)
                ->filter(fn (mixed $id): bool => is_numeric($id))
                ->map(fn (mixed $id): int => (int) $id)
                ->unique()
                ->values()
                ->all();

            $actorsById = $actorIds === []
                ? collect()
                : User::query()->whereIn('id', $actorIds)->get(['id', 'name'])->keyBy('id');

            return $messages
                ->map(function (ConversationMessage $message) use ($actorsById): array {
                    return [
                        'channel_label' => $this->channelLabel($message->channel),
                        'when_label' => $this->whenLabel($message->occurred_at),
                        'by_label' => $this->actorName($message, $actorsById),
                    ];
                })
                ->values()
                ->all();
        }

        if (! $this->alreadySent($repairOrder) || ! $repairOrder->review_request_recorded_at instanceof Carbon) {
            return [];
        }

        $repairOrder->loadMissing('reviewRequestRecordedBy');

        return [[
            'channel_label' => 'Recorded',
            'when_label' => $this->whenLabel($repairOrder->review_request_recorded_at),
            'by_label' => $repairOrder->reviewRequestRecordedBy?->name,
        ]];
    }

    /**
     * @param  list<string>  $channels
     */
    public function summarizeChannels(array $channels): string
    {
        $labels = collect($channels)
            ->map(fn (string $channel): string => match ($channel) {
                OperationalCommunicationChannel::Sms->value => 'text',
                OperationalCommunicationChannel::Email->value => 'email',
                default => $channel,
            })
            ->unique()
            ->values();

        if ($labels->count() === 0) {
            return 'review request';
        }

        if ($labels->count() === 1) {
            return (string) $labels->first();
        }

        return $labels->join(' + ');
    }

    public function channelLabel(OperationalCommunicationChannel|string $channel): string
    {
        $value = $channel instanceof OperationalCommunicationChannel ? $channel->value : $channel;

        return match ($value) {
            OperationalCommunicationChannel::Sms->value => 'Text',
            OperationalCommunicationChannel::Email->value => 'Email',
            default => ucfirst($value),
        };
    }

    public function whenLabel(?Carbon $at): string
    {
        if ($at === null) {
            return '';
        }

        $local = ShopDisplayTimezone::present($at);
        $time = $local->format('g:i A');

        if ($local->isToday()) {
            return 'Today '.$time;
        }

        if ($local->isYesterday()) {
            return 'Yesterday '.$time;
        }

        return $local->format('M j, g:i A');
    }

    /**
     * @param  Collection<int|string, User>  $actorsById
     */
    private function actorName(ConversationMessage $message, Collection $actorsById): ?string
    {
        $actorId = $message->metadata['actor_user_id'] ?? null;

        if (is_numeric($actorId)) {
            $name = $actorsById->get((int) $actorId)?->name;

            if (filled($name)) {
                return (string) $name;
            }
        }

        $participantName = $message->participant?->user?->name
            ?? $message->participant?->display_name;

        return filled($participantName) ? (string) $participantName : null;
    }
}
