<?php

namespace App\Ark\Mobile;

use App\Ark\Operations\Attention\AdvisorNudgeDraftBuilder;
use App\Ark\Operations\Attention\CommunicationsAttentionNudgeProjection;
use App\Ark\Operations\Communications\ConversationSmsIntelligenceSlice;
use App\Ark\Operations\Communications\OperationalCommunicationDirection;
use App\Ark\Operations\Conversations\Conversation;
use App\Ark\Operations\Conversations\ConversationWaitingOn;
use App\Ark\Operations\Conversations\CustomerCallContextResolver;
use App\Ark\Operations\RepairOrders\RepairOrder;
use App\Ark\Operations\RepairOrders\RepairOrderVisitMode;
use App\Ark\Operations\Telephony\CallSession;
use App\Ark\Operations\Telephony\CallSessionAnalysisStatus;
use App\Ark\Operations\Timeline\OperationalEventKind;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * Advisor Brief — operational awareness before the advisor responds.
 *
 * Not a chatbot. One posture headline, recent signals, one recommendation, promises, suggested replies.
 */
final class MobileAdvisorBriefProjection
{
    public function __construct(
        private readonly CommunicationsAttentionNudgeProjection $nudges,
        private readonly AdvisorNudgeDraftBuilder $draftBuilder,
        private readonly CustomerCallContextResolver $callContextResolver,
    ) {}

    /**
     * @param  array<string, mixed>  $context  ConversationContextProjection payload
     * @param  list<array<string, mixed>>  $activities  Thread activities (oldest → newest)
     * @return array<string, mixed>
     */
    public function forThread(
        User $user,
        Conversation $conversation,
        array $context,
        array $activities,
        ?RepairOrder $primaryRo,
    ): array {
        $entityKey = 'conversation:'.$conversation->id;
        $nudge = $this->nudges->forSelection([
            'kind' => 'conversation',
            'key' => $entityKey,
        ], $user);

        $customer = is_array($context['customer'] ?? null) ? $context['customer'] : [];
        $vehicle = is_array($context['vehicle'] ?? null) ? $context['vehicle'] : null;
        $repairOrder = is_array($context['repair_order'] ?? null) ? $context['repair_order'] : null;
        $conversationMeta = is_array($context['conversation'] ?? null) ? $context['conversation'] : [];

        if ($primaryRo instanceof RepairOrder) {
            $primaryRo->loadMissing(['vehicle', 'customer']);
        }

        $recentSignals = $this->recentSignals($activities, $primaryRo, $conversation);
        $commitments = $this->commitments($conversation, $primaryRo);
        $suggestedReplies = $this->suggestedReplies($nudge, $conversation, $primaryRo, $recentSignals);

        return [
            'customer_name' => (string) ($customer['name'] ?? 'Customer'),
            'vehicle_label' => (string) ($vehicle['label'] ?? ''),
            'work_title' => $this->workTitle($primaryRo),
            'repair_order' => $repairOrder,
            'posture_headline' => $this->postureHeadline($conversation, $primaryRo),
            'waiting_on_label' => (string) ($conversationMeta['waiting_on_label'] ?? $conversation->waiting_on?->label() ?? ''),
            'advisor_label' => (string) ($conversationMeta['assigned_to'] ?? $conversation->owner?->name ?? ''),
            'recent_signals' => $recentSignals,
            'recommended_action' => $this->recommendedAction($nudge, $conversation, $primaryRo, $recentSignals),
            'commitments' => $commitments,
            'suggested_replies' => $suggestedReplies,
        ];
    }

    private function workTitle(?RepairOrder $repairOrder): ?string
    {
        if ($repairOrder === null) {
            return null;
        }

        $summary = trim((string) $repairOrder->concern_summary);

        if ($summary !== '') {
            return $summary;
        }

        $repairOrder->loadMissing('concerns');

        $first = $repairOrder->concerns->first();

        return $first !== null ? trim((string) $first->summary) : null;
    }

    private function postureHeadline(Conversation $conversation, ?RepairOrder $repairOrder): string
    {
        if ($repairOrder instanceof RepairOrder) {
            $status = strtoupper($repairOrder->statusDisplayLabel());

            if ($repairOrder->status->value === 'waiting_approval') {
                $work = $this->workTitle($repairOrder);

                if ($work !== null && $work !== '') {
                    return 'WAITING FOR '.strtoupper($work).' APPROVAL';
                }

                return 'WAITING FOR APPROVAL';
            }

            if ($status !== '') {
                return $status;
            }
        }

        return match ($conversation->waiting_on) {
            ConversationWaitingOn::Customer => 'WAITING ON CUSTOMER',
            ConversationWaitingOn::Shop => 'AWAITING ADVISOR ACTION',
            default => 'CUSTOMER CONVERSATION',
        };
    }

    /**
     * @param  list<array<string, mixed>>  $activities
     * @return list<array{label: string, kind: string}>
     */
    private function recentSignals(array $activities, ?RepairOrder $repairOrder, Conversation $conversation): array
    {
        $signals = [];
        $newestFirst = array_reverse($activities);

        foreach ($newestFirst as $activity) {
            if (! is_array($activity)) {
                continue;
            }

            $kind = (string) ($activity['kind'] ?? '');
            $activityType = (string) ($activity['activity_type'] ?? $activity['metadata']['activity_type'] ?? '');
            $side = (string) ($activity['activity_side'] ?? '');
            $label = trim((string) (($activity['summary'] ?? '') !== '' ? $activity['summary'] : ($activity['activity_label'] ?? '')));
            $timeLabel = (string) ($activity['time_label'] ?? '');

            if ($kind === 'ai_summary' || $activityType === 'ai_summary' || $activityType === 'advisor_brief') {
                continue;
            }

            if ($kind === 'estimate_viewed' && ! $this->hasSignalKind($signals, 'estimate_viewed')) {
                $signals[] = [
                    'kind' => 'estimate_viewed',
                    'label' => $this->signalLabel('Estimate viewed', $timeLabel, $label),
                ];
            }

            if ($kind === 'voicemail' && ! $this->hasSignalKind($signals, 'voicemail')) {
                $signals[] = [
                    'kind' => 'voicemail',
                    'label' => $this->signalLabel('Voicemail left', $timeLabel, $label),
                ];
            }

            if ($kind === 'missed_call' && ! $this->hasSignalKind($signals, 'missed_call')) {
                $signals[] = [
                    'kind' => 'missed_call',
                    'label' => $this->signalLabel('Missed call', $timeLabel, $label),
                ];
            }

            if ($activityType === 'inbound_sms' && ! $this->hasSignalKind($signals, 'last_inbound')) {
                $signals[] = [
                    'kind' => 'last_inbound',
                    'label' => $this->signalLabel('Last customer text', $timeLabel, Str::limit($label, 80)),
                ];
            }

            if ($activityType === 'outbound_sms' && ! $this->hasSignalKind($signals, 'last_outbound')) {
                $signals[] = [
                    'kind' => 'last_outbound',
                    'label' => $this->signalLabel('Last shop reply', $timeLabel, Str::limit($label, 80)),
                ];
            }

            if ($side === 'customer' && $kind === 'sms' && ! $this->hasSignalKind($signals, 'last_inbound')) {
                $signals[] = [
                    'kind' => 'last_inbound',
                    'label' => $this->signalLabel('Last customer text', $timeLabel, Str::limit($label, 80)),
                ];
            }

            if ($side === 'shop' && $kind === 'sms' && ! $this->hasSignalKind($signals, 'last_outbound')) {
                $signals[] = [
                    'kind' => 'last_outbound',
                    'label' => $this->signalLabel('Last shop reply', $timeLabel, Str::limit($label, 80)),
                ];
            }
        }

        if ($repairOrder instanceof RepairOrder && RepairOrderVisitMode::fromRepairOrder($repairOrder) === RepairOrderVisitMode::WaitingHere) {
            $openedAt = $repairOrder->opened_at ?? $repairOrder->created_at;

            if ($openedAt instanceof Carbon) {
                $days = (int) $openedAt->diffInDays(now());

                if ($days >= 1) {
                    $signals[] = [
                        'kind' => 'vehicle_at_shop',
                        'label' => $days === 1
                            ? 'Vehicle has remained at the shop 1 day'
                            : "Vehicle has remained at the shop {$days} days",
                    ];
                }
            }
        }

        if ($conversation->waiting_on === ConversationWaitingOn::Shop && ! $this->hasSignalKind($signals, 'awaiting_shop')) {
            $signals[] = [
                'kind' => 'awaiting_shop',
                'label' => 'Awaiting advisor action',
            ];
        }

        return array_slice($signals, 0, 6);
    }

    /**
     * @param  list<array{label: string, kind: string}>  $signals
     */
    private function hasSignalKind(array $signals, string $kind): bool
    {
        foreach ($signals as $signal) {
            if (($signal['kind'] ?? '') === $kind) {
                return true;
            }
        }

        return false;
    }

    private function signalLabel(string $prefix, string $timeLabel, string $detail): string
    {
        $parts = array_filter([$prefix, $timeLabel !== '' ? "at {$timeLabel}" : null]);

        if ($detail !== '' && ! str_contains(strtolower($detail), strtolower($prefix))) {
            $parts[] = '· '.$detail;
        }

        return implode(' ', $parts);
    }

    /**
     * @return list<array{text: string, source: string}>
     */
    private function commitments(Conversation $conversation, ?RepairOrder $repairOrder): array
    {
        $commitments = [];

        $slice = ConversationSmsIntelligenceSlice::query()
            ->where('conversation_id', $conversation->id)
            ->where('analysis_status', CallSessionAnalysisStatus::Ready)
            ->orderByDesc('activity_date')
            ->first();

        if ($slice !== null) {
            $outcome = trim((string) data_get($slice->analysis_json, 'outcome', ''));

            if ($outcome !== '' && str_contains(strtolower($outcome), 'promis')) {
                $commitments[] = [
                    'text' => $outcome,
                    'source' => 'sms_analysis',
                ];
            }
        }

        if ($repairOrder !== null) {
            $phone = trim((string) $conversation->contact_address);
            $customerId = $repairOrder->customer_id;

            if ($phone !== '') {
                $sessions = CallSession::query()
                    ->where('customer_id', $customerId)
                    ->where('analysis_status', CallSessionAnalysisStatus::Ready)
                    ->orderByDesc('started_at')
                    ->limit(3)
                    ->get();

                foreach ($sessions as $session) {
                    $summary = trim((string) data_get($session->analysis_json, 'summary', ''));

                    if ($summary !== '' && $this->looksLikeCommitment($summary)) {
                        $commitments[] = [
                            'text' => $summary,
                            'source' => 'call_analysis',
                        ];
                    }
                }
            }
        }

        $conversation->loadMissing(['messages' => fn ($query) => $query
            ->where('direction', OperationalCommunicationDirection::Outbound)
            ->orderByDesc('occurred_at')
            ->limit(20),
        ]);

        foreach ($conversation->messages as $message) {
            $body = trim((string) $message->body);

            if ($body === '' || ! $this->looksLikeCommitment($body)) {
                continue;
            }

            $commitments[] = [
                'text' => Str::limit($body, 160),
                'source' => 'shop_message',
            ];

            break;
        }

        return array_values(array_slice($commitments, 0, 3));
    }

    private function looksLikeCommitment(string $text): bool
    {
        $lower = strtolower($text);

        foreach ([
            "i'll call",
            'i will call',
            'call you tomorrow',
            'parts arrive',
            'parts will',
            'answer this afternoon',
            'get back to you',
            'follow up',
            'we will have',
            "we'll have",
        ] as $needle) {
            if (str_contains($lower, $needle)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  array<string, mixed>|null  $nudge
     * @param  list<array{label: string, kind: string}>  $signals
     * @return array{label: string, nudge_key: string|null, entity_key: string}|null
     */
    private function recommendedAction(
        ?array $nudge,
        Conversation $conversation,
        ?RepairOrder $repairOrder,
        array $signals,
    ): ?array {
        $entityKey = 'conversation:'.$conversation->id;

        if ($nudge !== null) {
            $headline = trim((string) ($nudge['headline'] ?? ''));
            $message = trim((string) ($nudge['message'] ?? ''));

            return [
                'label' => $this->humanRecommendedAction($headline, $message, $nudge),
                'nudge_key' => filled($nudge['key'] ?? null) ? (string) $nudge['key'] : null,
                'entity_key' => (string) ($nudge['entity_key'] ?? $entityKey),
            ];
        }

        foreach ($signals as $signal) {
            $action = match ($signal['kind']) {
                'missed_call' => 'Return missed call',
                'voicemail' => 'Listen to voicemail and call back',
                'estimate_viewed' => 'Follow up on estimate',
                'last_inbound' => 'Reply to customer',
                'awaiting_shop' => 'Respond to customer',
                default => null,
            };

            if ($action !== null) {
                return [
                    'label' => $action,
                    'nudge_key' => null,
                    'entity_key' => $entityKey,
                ];
            }
        }

        if ($repairOrder !== null && $repairOrder->status->value === 'waiting_approval') {
            return [
                'label' => 'Follow up on estimate approval',
                'nudge_key' => null,
                'entity_key' => $entityKey,
            ];
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $nudge
     */
    private function humanRecommendedAction(string $headline, string $message, array $nudge): string
    {
        $key = (string) ($nudge['key'] ?? '');

        return match ($key) {
            'conversation.waiting_response' => 'Reply to customer',
            'conversation.estimate_views', 'conversation.estimate_viewed' => 'Follow up on estimate',
            'conversation.sms_analysis_follow_up' => filled($message) ? Str::limit($message, 120) : 'Send follow-up text',
            'call.mark_handled' => 'Mark call handled after follow-up',
            default => filled($message) ? Str::limit($message, 120) : ($headline !== '' ? $headline : 'Review and respond'),
        };
    }

    /**
     * @param  array<string, mixed>|null  $nudge
     * @param  list<array{label: string, kind: string}>  $signals
     * @return list<array{id: string, text: string, nudge_key: string|null, entity_key: string}>
     */
    private function suggestedReplies(
        ?array $nudge,
        Conversation $conversation,
        ?RepairOrder $repairOrder,
        array $signals,
    ): array {
        $entityKey = 'conversation:'.$conversation->id;
        $replies = [];
        $seen = [];

        if ($nudge !== null) {
            $draft = $this->draftBuilder->forNudge($nudge, $conversation, $this->customerFor($conversation));

            if ($draft === null) {
                $draft = trim((string) ($nudge['suggested_reply'] ?? ''));
            }

            if ($draft !== '' && ! $this->looksLikeAdvisorCoaching($draft)) {
                $replies[] = $this->replyRow('primary', $draft, (string) ($nudge['key'] ?? ''), $entityKey);
                $seen[strtolower($draft)] = true;
            }
        }

        $templates = [
            'voicemail' => 'We received your voicemail. I\'ll call you shortly.',
            'missed_call' => 'Sorry we missed your call — how can I help?',
            'estimate_viewed' => 'Your estimate is still awaiting approval. Happy to answer any questions.',
            'vehicle_ready' => 'Your vehicle is ready for pickup.',
        ];

        $signalKinds = array_column($signals, 'kind');

        if (in_array('voicemail', $signalKinds, true)) {
            $this->pushTemplate($replies, $seen, 'voicemail', $templates['voicemail'], $entityKey);
        }

        if (in_array('missed_call', $signalKinds, true)) {
            $this->pushTemplate($replies, $seen, 'missed_call', $templates['missed_call'], $entityKey);
        }

        if (in_array('estimate_viewed', $signalKinds, true)
            || ($repairOrder !== null && $repairOrder->status->value === 'waiting_approval')) {
            $this->pushTemplate($replies, $seen, 'estimate', $templates['estimate_viewed'], $entityKey);
        }

        if ($repairOrder !== null && in_array($repairOrder->status->value, ['ready_pickup', 'completed'], true)) {
            $this->pushTemplate($replies, $seen, 'vehicle_ready', $templates['vehicle_ready'], $entityKey);
        }

        return array_slice($replies, 0, 3);
    }

    /**
     * @param  list<array{id: string, text: string, nudge_key: string|null, entity_key: string}>  $replies
     * @param  array<string, bool>  $seen
     */
    private function pushTemplate(array &$replies, array &$seen, string $id, string $text, string $entityKey): void
    {
        $key = strtolower($text);

        if (isset($seen[$key])) {
            return;
        }

        $replies[] = $this->replyRow($id, $text, null, $entityKey);
        $seen[$key] = true;
    }

    /**
     * @return array{id: string, text: string, nudge_key: string|null, entity_key: string}
     */
    private function replyRow(string $id, string $text, ?string $nudgeKey, string $entityKey): array
    {
        return [
            'id' => $id,
            'text' => $text,
            'nudge_key' => $nudgeKey,
            'entity_key' => $entityKey,
        ];
    }

    private function looksLikeAdvisorCoaching(string $text): bool
    {
        $lower = strtolower($text);

        foreach ([
            'advisor should',
            'the advisor',
            'shop left',
            'no advisor reply',
            'operational failure',
        ] as $needle) {
            if (str_contains($lower, $needle)) {
                return true;
            }
        }

        return false;
    }

    private function customerFor(Conversation $conversation): ?\App\Ark\Operations\Customers\Customer
    {
        $phone = trim((string) $conversation->contact_address);

        if ($phone === '') {
            return null;
        }

        return $this->callContextResolver->resolve($phone)?->customer;
    }
}
