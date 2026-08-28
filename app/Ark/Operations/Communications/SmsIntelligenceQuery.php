<?php

namespace App\Ark\Operations\Communications;

use App\Ark\Operations\Customers\Customer;
use App\Ark\Operations\PhoneNumber;
use App\Ark\Operations\RepairOrders\RepairOrder;
use App\Ark\Operations\Settings\ShopDisplayTimezone;
use App\Ark\Operations\Telephony\CallSessionAnalysisProjection;
use App\Ark\Operations\Telephony\CallSessionAnalysisStatus;
use App\Ark\Operations\Telephony\CallSessionAnalyzer;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

final class SmsIntelligenceQuery
{
    /**
     * @return LengthAwarePaginator<int, ConversationSmsIntelligenceSlice>
     */
    public function paginate(Request $request): LengthAwarePaginator
    {
        $query = $this->baseQuery($request)
            ->orderByRaw('CASE WHEN coaching_follow_up_at IS NOT NULL THEN 0 ELSE 1 END')
            ->orderByDesc('coaching_follow_up_at')
            ->orderByDesc('last_message_at')
            ->orderByDesc('id');

        $this->applyAnalysisFilter($query, $request);

        return $query->paginate(25)->withQueryString();
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function coachingQueue(Request $request, int $limit = 10): array
    {
        $query = $this->baseQuery($request)
            ->where('analysis_status', CallSessionAnalysisStatus::Ready);

        if ($request->query('analysis') === 'pinned') {
            $query->whereNotNull('coaching_follow_up_at');
        } elseif ($request->query('analysis') === 'coaching') {
            $query->where(function ($scoped): void {
                $scoped->whereIn('analysis_json->coaching_priority', ['medium', 'high'])
                    ->orWhereNotNull('coaching_follow_up_at');
            });
        } else {
            $query->where(function ($scoped): void {
                $scoped->whereIn('analysis_json->coaching_priority', ['high', 'medium', 'low'])
                    ->orWhereNotNull('coaching_follow_up_at');
            });
        }

        return $query->get()
            ->sort(fn (ConversationSmsIntelligenceSlice $left, ConversationSmsIntelligenceSlice $right): int => $this->coachingSort($left, $right))
            ->take($limit)
            ->map(fn (ConversationSmsIntelligenceSlice $slice): array => $this->presentRow($slice))
            ->values()
            ->all();
    }

    /**
     * @return \Illuminate\Database\Eloquent\Builder<ConversationSmsIntelligenceSlice>
     */
    public function baseQuery(Request $request): \Illuminate\Database\Eloquent\Builder
    {
        $from = Carbon::parse($request->query('from', now()->subDays(30)->toDateString()))->startOfDay();
        $to = Carbon::parse($request->query('to', now()->toDateString()))->endOfDay();

        return ConversationSmsIntelligenceSlice::query()
            ->with([
                'conversation.owner',
                'conversation.participants.customer',
                'conversation.links',
            ])
            ->where('message_count', '>=', ConversationSmsIntelligenceSliceTouch::MIN_MESSAGES)
            ->whereBetween('last_message_at', [$from, $to]);
    }

    private function applyAnalysisFilter(\Illuminate\Database\Eloquent\Builder $query, Request $request): void
    {
        if ($request->query('analysis') === 'ready') {
            $query->where('analysis_status', CallSessionAnalysisStatus::Ready);
        } elseif ($request->query('analysis') === 'follow_up') {
            $query->where('analysis_status', CallSessionAnalysisStatus::Ready)
                ->where('analysis_json->follow_up_needed', true);
        } elseif ($request->query('analysis') === 'missed_upsell') {
            $query->where('analysis_status', CallSessionAnalysisStatus::Ready)
                ->where('analysis_json->missed_upsell', true);
        } elseif ($request->query('analysis') === 'coaching') {
            $query->where('analysis_status', CallSessionAnalysisStatus::Ready)
                ->where(function ($scoped): void {
                    $scoped->whereIn('analysis_json->coaching_priority', ['medium', 'high'])
                        ->orWhereNotNull('coaching_follow_up_at');
                });
        } elseif ($request->query('analysis') === 'pinned') {
            $query->whereNotNull('coaching_follow_up_at');
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function presentRow(ConversationSmsIntelligenceSlice $slice): array
    {
        $timezone = ShopDisplayTimezone::resolve();
        $conversation = $slice->conversation;
        $customer = $this->customerFor($conversation);
        $repairOrder = $this->repairOrderFor($conversation);
        $analysis = is_array($slice->analysis_json) ? $slice->analysis_json : [];
        $displayPhone = PhoneNumber::display($conversation?->contact_address)
            ?? $conversation?->contact_address
            ?? 'Unknown';

        return [
            'kind' => 'sms',
            'id' => $slice->id,
            'channel_label' => 'SMS',
            'direction_label' => 'SMS thread',
            'status_label' => $slice->message_count.' messages',
            'display_phone' => $displayPhone,
            'customer_name' => $customer?->name,
            'customer_url' => $customer ? route('operations.customers.show', $customer) : null,
            'staff_name' => $conversation?->owner?->name,
            'repair_order_number' => $repairOrder?->repair_order_id,
            'repair_order_url' => $repairOrder
                ? route('operations.repair-orders.show', $repairOrder)
                : null,
            'started_at_label' => $slice->activity_date->timezone($timezone)->format('M j, Y')
                .($slice->last_message_at ? ' · last '.$slice->last_message_at->timezone($timezone)->format('g:i A') : ''),
            'duration_label' => $slice->message_count.' msgs',
            'analysis_status' => $slice->analysis_status?->value,
            'analysis_status_label' => $slice->analysis_status?->label() ?? 'Not queued',
            'summary' => $slice->analysisSummary(),
            'sentiment' => $slice->analysisSentiment(),
            'sentiment_label' => CallSessionAnalysisProjection::sentimentLabel(
                (string) ($slice->analysisSentiment() ?? 'neutral'),
            ),
            'outcome' => data_get($analysis, 'outcome'),
            'customer_intent' => data_get($analysis, 'customer_intent'),
            'follow_up_needed' => $slice->analysisFollowUpNeeded(),
            'follow_up_notes' => data_get($analysis, 'follow_up_notes'),
            'suggested_reply' => filled(data_get($analysis, 'suggested_reply'))
                ? trim((string) data_get($analysis, 'suggested_reply'))
                : null,
            'missed_upsell' => (bool) data_get($analysis, 'missed_upsell', false),
            'missed_upsell_notes' => data_get($analysis, 'missed_upsell_notes'),
            'empathy_score' => data_get($analysis, 'empathy_score'),
            'empathy_label' => CallSessionAnalysisProjection::empathyLabel(
                is_numeric(data_get($analysis, 'empathy_score')) ? (int) data_get($analysis, 'empathy_score') : null,
            ),
            'empathy_notes' => data_get($analysis, 'empathy_notes'),
            'ownership_score' => data_get($analysis, 'ownership_score'),
            'ownership_label' => CallSessionAnalysisProjection::scoreLabel(
                is_numeric(data_get($analysis, 'ownership_score')) ? (int) data_get($analysis, 'ownership_score') : null,
            ),
            'clarity_score' => data_get($analysis, 'clarity_score'),
            'clarity_label' => CallSessionAnalysisProjection::scoreLabel(
                is_numeric(data_get($analysis, 'clarity_score')) ? (int) data_get($analysis, 'clarity_score') : null,
            ),
            'appointment_captured' => data_get($analysis, 'appointment_captured'),
            'appointment_notes' => data_get($analysis, 'appointment_notes'),
            'coaching_priority' => (string) data_get($analysis, 'coaching_priority', 'none'),
            'coaching_priority_label' => CallSessionAnalysisProjection::coachingPriorityLabel(
                (string) data_get($analysis, 'coaching_priority', 'none'),
            ),
            'coaching_notes' => data_get($analysis, 'coaching_notes'),
            'coaching_strengths' => data_get($analysis, 'coaching_strengths', []),
            'coaching_improvements' => data_get($analysis, 'coaching_improvements', []),
            'coaching_headline' => $this->coachingHeadline($analysis),
            'topics' => data_get($analysis, 'topics', []),
            'transcript' => $slice->transcript,
            'analysis_error' => $slice->analysis_error,
            'analyzed_at_label' => $slice->analyzed_at?->timezone($timezone)->format('M j, g:i A'),
            'recording_url' => null,
            'can_reanalyze' => ConversationSmsIntelligenceAnalyzer::enabled()
                && ConversationSmsIntelligenceSliceTouch::isEligible($slice),
            'reanalyze_url' => route('operations.owner.call-intelligence.sms.analyze', $slice),
            'show_url' => route('operations.owner.call-intelligence.sms.show', $slice),
            'open_label' => 'Open thread',
            'coaching_follow_up_pinned' => $slice->isCoachingFollowUpPinned(),
            'coaching_follow_up_at_label' => $slice->coaching_follow_up_at?->timezone($timezone)->format('M j, g:i A'),
            'toggle_coaching_follow_up_url' => route('operations.owner.call-intelligence.sms.follow-up.toggle', $slice),
            'coaching_pdf_url' => null,
            'media_kind_label' => null,
        ];
    }

    private function coachingSort(ConversationSmsIntelligenceSlice $left, ConversationSmsIntelligenceSlice $right): int
    {
        $leftPinned = $left->coaching_follow_up_at !== null;
        $rightPinned = $right->coaching_follow_up_at !== null;

        if ($leftPinned !== $rightPinned) {
            return $rightPinned <=> $leftPinned;
        }

        if ($leftPinned && $rightPinned) {
            $pinCompare = ($right->coaching_follow_up_at?->getTimestamp() ?? 0)
                <=> ($left->coaching_follow_up_at?->getTimestamp() ?? 0);

            if ($pinCompare !== 0) {
                return $pinCompare;
            }
        }

        $leftAnalysis = is_array($left->analysis_json) ? $left->analysis_json : [];
        $rightAnalysis = is_array($right->analysis_json) ? $right->analysis_json : [];

        $weightCompare = CallSessionAnalysisProjection::coachingUrgencyWeight($rightAnalysis)
            <=> CallSessionAnalysisProjection::coachingUrgencyWeight($leftAnalysis);

        if ($weightCompare !== 0) {
            return $weightCompare;
        }

        return ($right->last_message_at?->getTimestamp() ?? 0)
            <=> ($left->last_message_at?->getTimestamp() ?? 0);
    }

    /**
     * @param  array<string, mixed>  $analysis
     */
    private function coachingHeadline(array $analysis): ?string
    {
        $improvements = data_get($analysis, 'coaching_improvements', []);
        if (is_array($improvements) && isset($improvements[0]) && is_string($improvements[0]) && trim($improvements[0]) !== '') {
            return trim($improvements[0]);
        }

        $notes = data_get($analysis, 'coaching_notes');
        if (is_string($notes) && trim($notes) !== '') {
            return trim($notes);
        }

        return null;
    }

    private function customerFor(?\App\Ark\Operations\Conversations\Conversation $conversation): ?Customer
    {
        return $conversation?->participants
            ->first(fn ($participant) => $participant->customer_id !== null)
            ?->customer;
    }

    private function repairOrderFor(?\App\Ark\Operations\Conversations\Conversation $conversation): ?RepairOrder
    {
        $link = $conversation?->links
            ->first(fn ($item) => $item->linkable_type === RepairOrder::class);

        $linkable = $link?->linkable;

        return $linkable instanceof RepairOrder ? $linkable : null;
    }
}
