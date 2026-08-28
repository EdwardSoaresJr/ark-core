<?php

namespace App\Ark\Operations\Briefing\Rules;

use App\Ark\Operations\Attention\ConversationAttentionCandidateBuilder;
use App\Ark\Operations\Briefing\BriefingConfidence;
use App\Ark\Operations\Briefing\BriefingContext;
use App\Ark\Operations\Briefing\BriefingEvidenceItem;
use App\Ark\Operations\Briefing\BriefingItem;
use App\Ark\Operations\Briefing\BriefingPriority;
use App\Ark\Operations\Conversations\Conversation;
use App\Ark\Operations\Conversations\ConversationMessage;
use App\Ark\Operations\Observations\OperationalObservationType;

final class CustomerWaitingResponseBriefingRule implements BriefingRule
{
    public function __construct(
        private readonly ConversationAttentionCandidateBuilder $attentionCandidates,
    ) {}

    public function key(): string
    {
        return 'customer_waiting_response';
    }

    public function items(BriefingContext $context): array
    {
        $items = [];

        $conversations = Conversation::query()
            ->where('waiting_on', \App\Ark\Operations\Conversations\ConversationWaitingOn::Shop)
            ->latest('updated_at')
            ->limit(30)
            ->get();

        foreach ($conversations as $conversation) {
            $candidate = $this->attentionCandidates->forConversation($conversation);

            if ($candidate === null) {
                continue;
            }

            $waiting = collect($candidate->observations)->first(
                fn ($observation) => $observation->type === OperationalObservationType::CustomerWaitingResponse,
            );

            if ($waiting === null) {
                continue;
            }

            $lastInbound = ConversationMessage::query()
                ->where('conversation_id', $conversation->id)
                ->where('direction', 'inbound')
                ->latest('created_at')
                ->first();

            $items[] = new BriefingItem(
                key: $this->key().'_'.$conversation->id,
                headline: $candidate->headline.' is waiting for a response',
                summary: filled($waiting->description) ? (string) $waiting->description : (string) $waiting->headline,
                priority: BriefingPriority::High,
                confidence: new BriefingConfidence(
                    score: min(100, max(60, $candidate->pressureScore)),
                    reason: 'Attention projection detected customer waiting response.',
                    signals: collect($candidate->reasons)->map(
                        fn (string $reason): array => ['label' => $reason, 'satisfied' => true],
                    )->all(),
                    facts: [
                        ['label' => 'Conversation', 'value' => '#'.$conversation->id],
                        ['label' => 'Pressure score', 'value' => (string) $candidate->pressureScore],
                    ],
                ),
                evidenceItems: array_filter([
                    $lastInbound !== null
                        ? new BriefingEvidenceItem(
                            sourceType: 'conversation_message',
                            summary: 'Last inbound message',
                            occurredAt: $lastInbound->created_at,
                            detail: \Illuminate\Support\Str::limit((string) $lastInbound->body, 120),
                            sourceId: $lastInbound->id,
                            sourceLabel: 'Message',
                        )
                        : null,
                ]),
                actionUrl: $candidate->customerId
                    ? route('operations.customers.show', $candidate->customerId).'?compose=text#customer-communication'
                    : route('operations.communications.queue'),
                actionLabel: 'Reply to customer',
                customerId: $candidate->customerId,
            );

            if (count($items) >= 5) {
                break;
            }
        }

        return $items;
    }
}
