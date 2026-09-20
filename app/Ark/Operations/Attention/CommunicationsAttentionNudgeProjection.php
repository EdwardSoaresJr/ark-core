<?php

namespace App\Ark\Operations\Attention;

use App\Ark\Operations\Communications\ConversationSmsIntelligenceSlice;
use App\Ark\Operations\Conversations\Conversation;
use App\Ark\Operations\Conversations\ConversationContactSurface;
use App\Ark\Operations\Conversations\ConversationLink;
use App\Ark\Operations\Conversations\ConversationMessage;
use App\Ark\Operations\Conversations\CustomerCallContextResolver;
use App\Ark\Operations\Communications\OperationalCommunicationDirection;
use App\Ark\Operations\Customers\Customer;
use App\Ark\Operations\Observations\OperationalObservationType;
use App\Ark\Operations\Telephony\CallSession;
use App\Ark\Operations\Telephony\CallSessionAnalysisStatus;
use App\Ark\Operations\Telephony\CallSessionDirection;
use App\Ark\Operations\Telephony\CallSessionStatus;
use App\Models\User;
use Illuminate\Support\Str;

/**
 * Advisor-facing nudge projection for Communications → Needs attention.
 *
 * Suggestions only — never mutates authority. Responses logged in advisor_nudge_responses.
 */
final class CommunicationsAttentionNudgeProjection
{
    private const SUPPRESS_HOURS = 8;

    public function __construct(
        private readonly ConversationAttentionCandidateBuilder $attentionCandidates,
        private readonly CustomerCallContextResolver $callContextResolver,
        private readonly AdvisorNudgeDraftBuilder $draftBuilder,
        private readonly ConversationAttentionObservationFilter $observationFilter,
    ) {}

    /**
     * @param  array<string, mixed>|null  $selected
     * @return array<string, mixed>|null
     */
    public function forSelection(?array $selected, ?User $viewer): ?array
    {
        if ($selected === null || $viewer === null) {
            return null;
        }

        $entityKey = (string) ($selected['key'] ?? '');

        if ($entityKey === '') {
            return null;
        }

        $nudges = match ($selected['kind'] ?? '') {
            'call' => $this->nudgesForCall((int) Str::after($entityKey, 'call:'), $entityKey, $viewer),
            'conversation' => $this->nudgesForConversation((int) Str::after($entityKey, 'conversation:'), $entityKey, $viewer),
            default => [],
        };

        if ($nudges === []) {
            return null;
        }

        // Suppression is tracked per nudge entity key — call nudges surfaced on
        // a merged conversation selection keep their own call:{id} identity.
        $suppressedByEntity = [];

        $visible = array_values(array_filter(
            $nudges,
            function (array $nudge) use ($viewer, $entityKey, &$suppressedByEntity): bool {
                $nudgeEntityKey = (string) ($nudge['entity_key'] ?? $entityKey);

                $suppressedByEntity[$nudgeEntityKey] ??= AdvisorNudgeResponse::suppressedNudgeKeys(
                    $viewer->id,
                    $nudgeEntityKey,
                    now()->subHours(self::SUPPRESS_HOURS),
                )->all();

                return ! in_array($nudge['key'], $suppressedByEntity[$nudgeEntityKey], true);
            },
        ));

        if ($visible === []) {
            return null;
        }

        usort($visible, fn (array $left, array $right): int => ($right['priority'] ?? 0) <=> ($left['priority'] ?? 0));

        $nudge = $visible[0];

        return $this->withDraftReply($nudge, $selected);
    }

    /**
     * @param  array<string, mixed>  $nudge
     * @param  array<string, mixed>  $selected
     * @return array<string, mixed>
     */
    private function withDraftReply(array $nudge, array $selected): array
    {
        $actionType = (string) data_get($nudge, 'primary_action.type', '');

        if (! in_array($actionType, ['composer', 'redirect_form', 'anchor'], true)) {
            return $nudge;
        }

        $conversation = null;
        $customer = null;

        if (($selected['kind'] ?? '') === 'conversation') {
            $conversationId = (int) Str::after((string) ($selected['key'] ?? ''), 'conversation:');
            $conversation = Conversation::query()->find($conversationId);

            if ($conversation !== null) {
                $phone = trim((string) $conversation->contact_address);

                if ($phone !== '') {
                    $customer = $this->callContextResolver->resolve($phone)?->customer;
                }
            }
        } elseif (($selected['kind'] ?? '') === 'call') {
            $callSessionId = (int) Str::after((string) ($selected['key'] ?? ''), 'call:');
            $session = CallSession::query()->with('customer')->find($callSessionId);
            $customer = $session?->customer;
        }

        $draft = $this->draftBuilder->forNudge($nudge, $conversation, $customer);

        if ($draft !== null) {
            $nudge['draft_reply'] = $draft;
        }

        return $nudge;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function nudgesForCall(int $callSessionId, string $entityKey, User $viewer): array
    {
        $session = CallSession::query()->with(['customer', 'repairOrder'])->find($callSessionId);

        if ($session === null) {
            return [];
        }

        $nudges = [];

        if ($session->worked_at === null && in_array($session->status, [
            CallSessionStatus::Completed,
            CallSessionStatus::Answered,
            CallSessionStatus::Missed,
        ], true)) {
            $nudges[] = [
                'key' => 'call.mark_handled',
                'entity_key' => $entityKey,
                'headline' => 'Mark call handled',
                'message' => 'This call is still on Needs attention. Mark handled once follow-up is covered or the floor has context.',
                'rationale' => $session->status->operationalLabel().' · not marked handled',
                'priority' => $session->status === CallSessionStatus::Missed ? 95 : 88,
                'sources' => ['call.unhandled'],
                'primary_action' => [
                    'type' => 'form',
                    'label' => 'Mark handled',
                    'method' => 'POST',
                    'url' => route('operations.communications.calls.mark-handled', $session),
                    'fields' => [
                        'nudge_key' => 'call.mark_handled',
                        'entity_key' => $entityKey,
                        'section' => 'attention',
                    ],
                ],
            ];
        }

        if ($this->callNeedsNote($session)) {
            $nudges[] = [
                'key' => 'call.log_note',
                'entity_key' => $entityKey,
                'headline' => 'Log call note',
                'message' => 'No call note on this session yet. Log what happened so the next advisor has context.',
                'rationale' => 'Missing call note',
                'priority' => 72,
                'sources' => ['call.no_note'],
                'primary_action' => [
                    'type' => 'anchor',
                    'label' => 'Log call note',
                    'url' => '#comms-call-note-composer',
                ],
            ];
        }

        if ($session->analysis_status === CallSessionAnalysisStatus::Ready
            && (bool) data_get($session->analysis_json, 'follow_up_needed', false)) {
            $notes = trim((string) data_get($session->analysis_json, 'follow_up_notes', ''));
            $suggestedReply = trim((string) data_get($session->analysis_json, 'suggested_reply', ''));

            $nudges[] = [
                'key' => 'call.analysis_follow_up',
                'entity_key' => $entityKey,
                'headline' => 'Follow-up suggested',
                'message' => $notes !== ''
                    ? $notes
                    : trim((string) data_get($session->analysis_json, 'summary', 'Review this call and decide next step.')),
                'rationale' => 'Call analysis',
                'priority' => 78,
                'sources' => ['analysis.call'],
                'suggested_reply' => $suggestedReply !== '' ? $suggestedReply : null,
                'primary_action' => $this->callNeedsNote($session)
                    ? [
                        'type' => 'anchor',
                        'label' => 'Log call note',
                        'url' => '#comms-call-note-composer',
                    ]
                    : [
                        'type' => 'form',
                        'label' => 'Mark handled',
                        'method' => 'POST',
                        'url' => route('operations.communications.calls.mark-handled', $session),
                        'fields' => [
                            'nudge_key' => 'call.analysis_follow_up',
                            'entity_key' => $entityKey,
                            'section' => 'attention',
                        ],
                    ],
            ];
        }

        return $nudges;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function nudgesForConversation(int $conversationId, string $entityKey, User $viewer): array
    {
        $conversation = Conversation::query()->find($conversationId);

        if ($conversation === null) {
            return [];
        }

        $nudges = [];
        $candidate = $this->attentionCandidates->forConversation($conversation);

        if ($candidate !== null) {
            $customerId = ConversationLink::query()
                ->where('conversation_id', $conversation->id)
                ->where('linkable_type', Customer::class)
                ->value('linkable_id');

            if ($customerId === null) {
                $phone = trim((string) $conversation->contact_address);
                $customerId = $phone !== ''
                    ? $this->callContextResolver->resolve($phone)?->customer?->id
                    : null;
            }

            $filteredObservations = $this->observationFilter->filter(
                $candidate->observations,
                $conversation,
                is_numeric($customerId) ? (int) $customerId : null,
            );

            $filteredCandidate = new AttentionCandidate(
                entityKey: $candidate->entityKey,
                headline: $candidate->headline,
                pressureScore: $candidate->pressureScore,
                reasons: $candidate->reasons,
                observations: $filteredObservations,
                conversationId: $candidate->conversationId,
                customerId: $candidate->customerId,
            );

            foreach ($this->nudgesFromObservations($conversation, $entityKey, $filteredCandidate) as $nudge) {
                $nudges[] = $nudge;
            }
        }

        $slice = ConversationSmsIntelligenceSlice::query()
            ->where('conversation_id', $conversation->id)
            ->where('analysis_status', CallSessionAnalysisStatus::Ready)
            ->orderByDesc('activity_date')
            ->first();

        if ($slice !== null && $this->smsAnalysisFollowUpApplies($conversation, $slice)) {
            $summary = trim((string) data_get($slice->analysis_json, 'summary', 'Review this thread and reply if needed.'));
            $suggestedReply = trim((string) data_get($slice->analysis_json, 'suggested_reply', ''));

            $nudges[] = [
                'key' => 'conversation.sms_analysis_follow_up',
                'entity_key' => $entityKey,
                'headline' => 'SMS follow-up suggested',
                'message' => $summary,
                'rationale' => 'SMS analysis',
                'priority' => 76,
                'sources' => ['analysis.sms'],
                'suggested_reply' => $suggestedReply !== '' ? $suggestedReply : null,
                'primary_action' => $this->replyAction($conversation, $entityKey, 'conversation.sms_analysis_follow_up'),
            ];
        }

        // Calls merge into the conversation selection in the workspace list —
        // an unhandled call's nudges must not vanish behind that mapping.
        $session = $this->latestUnhandledInboundCall($conversation);

        if ($session !== null) {
            foreach ($this->nudgesForCall($session->id, 'call:'.$session->id, $viewer) as $nudge) {
                $nudges[] = $nudge;
            }
        }

        return $nudges;
    }

    private function latestUnhandledInboundCall(Conversation $conversation): ?CallSession
    {
        if ($conversation->contact_surface !== ConversationContactSurface::Phone) {
            return null;
        }

        $phone = trim((string) $conversation->contact_address);

        if ($phone === '') {
            return null;
        }

        return CallSession::query()
            ->where('direction', CallSessionDirection::Inbound)
            ->whereNull('worked_at')
            ->where('started_at', '>=', now()->subDays(7))
            ->where(function ($query) use ($phone): void {
                $query->where('normalized_from', $phone)
                    ->orWhere('from_number', 'like', '%'.$phone);
            })
            ->orderByDesc('started_at')
            ->first();
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function nudgesFromObservations(
        Conversation $conversation,
        string $entityKey,
        AttentionCandidate $candidate,
    ): array {
        $nudges = [];

        foreach ($candidate->observations as $observation) {
            $nudge = match ($observation->type) {
                OperationalObservationType::CustomerWaitingResponse => [
                    'key' => 'conversation.waiting_response',
                    'headline' => 'Customer waiting',
                    'message' => filled($observation->description)
                        ? (string) $observation->description
                        : (string) $observation->headline,
                    'rationale' => 'Observation',
                    'priority' => 86,
                    'sources' => ['observation.customer_waiting_response'],
                    'primary_action' => $this->replyAction($conversation, $entityKey, 'conversation.waiting_response'),
                ],
                OperationalObservationType::EstimateViewedMultipleTimes => [
                    'key' => 'conversation.estimate_views',
                    'headline' => 'Estimate viewed again',
                    'message' => 'Customer opened the estimate '
                        .($observation->metadata['view_count'] ?? 'multiple')
                        .' times — consider a check-in text.',
                    'rationale' => 'Observation',
                    'priority' => 82,
                    'sources' => ['observation.estimate_viewed_multiple_times'],
                    'primary_action' => $this->customerHubReplyAction($conversation, $entityKey, 'conversation.estimate_views'),
                ],
                OperationalObservationType::EstimateViewed => [
                    'key' => 'conversation.estimate_viewed',
                    'headline' => 'Estimate viewed',
                    'message' => 'Customer opened the estimate portal — follow up if approval hasn\'t come in.',
                    'rationale' => 'Observation',
                    'priority' => 68,
                    'sources' => ['observation.estimate_viewed'],
                    'primary_action' => $this->customerHubReplyAction($conversation, $entityKey, 'conversation.estimate_viewed'),
                ],
                OperationalObservationType::ConversationUnassigned => [
                    'key' => 'conversation.unassigned',
                    'headline' => 'Unassigned thread',
                    'message' => 'No advisor owns this conversation yet.',
                    'rationale' => 'Observation',
                    'priority' => 64,
                    'sources' => ['observation.conversation_unassigned'],
                    'primary_action' => [
                        'type' => 'form',
                        'label' => 'Assign to me',
                        'method' => 'POST',
                        'url' => route('operations.communications.conversations.assign', $conversation),
                        'fields' => [
                            'assign_to' => 'me',
                            'nudge_key' => 'conversation.unassigned',
                            'entity_key' => $entityKey,
                            'section' => 'attention',
                        ],
                    ],
                ],
                OperationalObservationType::CustomerSentMultipleMessages => [
                    'key' => 'conversation.multiple_messages',
                    'headline' => 'Multiple customer texts',
                    'message' => ($observation->metadata['message_count'] ?? 2)
                        .' customer messages — make sure nothing is unanswered.',
                    'rationale' => 'Observation',
                    'priority' => 84,
                    'sources' => ['observation.customer_sent_multiple_messages'],
                    'primary_action' => $this->replyAction($conversation, $entityKey, 'conversation.multiple_messages'),
                ],
                default => null,
            };

            if ($nudge !== null) {
                $nudge['entity_key'] = $entityKey;
                $nudges[] = $nudge;
            }
        }

        return $nudges;
    }

    /**
     * @return array<string, mixed>
     */
    private function replyAction(Conversation $conversation, string $entityKey, string $nudgeKey): array
    {
        if ($conversation->contact_surface !== ConversationContactSurface::Phone) {
            return [
                'type' => 'form',
                'label' => 'Mark read',
                'method' => 'POST',
                'url' => route('operations.communications.conversations.mark-read', $conversation),
                'fields' => [
                    'nudge_key' => $nudgeKey,
                    'entity_key' => $entityKey,
                    'section' => 'attention',
                ],
            ];
        }

        return [
            'type' => 'composer',
            'label' => 'Reply',
            'url' => '#comms-thread-composer',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function customerHubReplyAction(Conversation $conversation, string $entityKey, string $nudgeKey): array
    {
        $phone = trim((string) $conversation->contact_address);
        $context = $phone !== '' ? $this->callContextResolver->resolve($phone) : null;
        $customer = $context?->customer;

        if ($customer instanceof Customer) {
            return [
                'type' => 'composer',
                'label' => 'Reply with draft',
                'url' => '#comms-thread-composer',
            ];
        }

        return $this->replyAction($conversation, $entityKey, $nudgeKey);
    }

    private function callNeedsNote(CallSession $session): bool
    {
        return ! ConversationMessage::query()
            ->where('metadata->call_session_id', $session->id)
            ->where('metadata->call_note', true)
            ->exists();
    }

    private function smsAnalysisFollowUpApplies(Conversation $conversation, ConversationSmsIntelligenceSlice $slice): bool
    {
        if (! $slice->advisorFollowUpApplies()) {
            return false;
        }

        $lastMessage = ConversationMessage::query()
            ->where('conversation_id', $conversation->id)
            ->orderByDesc('occurred_at')
            ->orderByDesc('id')
            ->first();

        if ($lastMessage?->direction === OperationalCommunicationDirection::Outbound) {
            return false;
        }

        return true;
    }
}
