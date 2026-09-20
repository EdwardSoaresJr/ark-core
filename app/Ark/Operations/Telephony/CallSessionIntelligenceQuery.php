<?php

namespace App\Ark\Operations\Telephony;

use App\Ark\Operations\Settings\ShopDisplayTimezone;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

final class CallSessionIntelligenceQuery
{
    public function __construct(
        private readonly InboundCallerDisplayPhone $callerDisplayPhone,
        private readonly CallRecordingPlayback $recordingPlayback,
    ) {}

    /**
     * @return LengthAwarePaginator<int, CallSession>
     */
    public function paginate(Request $request): LengthAwarePaginator
    {
        $query = $this->baseQuery($request)
            ->orderByRaw('CASE WHEN coaching_follow_up_at IS NOT NULL THEN 0 ELSE 1 END')
            ->orderByDesc('coaching_follow_up_at')
            ->orderByDesc('started_at')
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

        $sessions = $query->get();

        return $sessions
            ->sort(function (CallSession $left, CallSession $right): int {
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

                $priorityCompare = CallSessionAnalysisProjection::coachingPriorityRank(
                    (string) data_get($leftAnalysis, 'coaching_priority', 'none'),
                ) <=> CallSessionAnalysisProjection::coachingPriorityRank(
                    (string) data_get($rightAnalysis, 'coaching_priority', 'none'),
                );

                if ($priorityCompare !== 0) {
                    return $priorityCompare;
                }

                return ($right->started_at?->getTimestamp() ?? 0)
                    <=> ($left->started_at?->getTimestamp() ?? 0);
            })
            ->take($limit)
            ->map(fn (CallSession $session): array => $this->presentRow($session))
            ->values()
            ->all();
    }

    /**
     * @return \Illuminate\Database\Eloquent\Builder<CallSession>
     */
    private function baseQuery(Request $request): \Illuminate\Database\Eloquent\Builder
    {
        $from = Carbon::parse($request->query('from', now()->subDays(30)->toDateString()))->startOfDay();
        $to = Carbon::parse($request->query('to', now()->toDateString()))->endOfDay();

        $query = CallSession::query()
            ->with(['customer', 'owner', 'repairOrder'])
            ->whereBetween('started_at', [$from, $to]);

        if ($request->query('media') === 'recorded') {
            $query->where(function ($scoped): void {
                $scoped->whereNotNull('recording_url')
                    ->orWhereNotNull('voicemail_url');
            });
        }

        return $query;
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
    public function presentRow(CallSession $callSession): array
    {
        $timezone = ShopDisplayTimezone::resolve();
        $recording = $this->recordingPlayback->projectFor($callSession);
        $duration = $callSession->analysisDurationSeconds();

        $analysis = is_array($callSession->analysis_json) ? $callSession->analysis_json : [];

        return [
            'id' => $callSession->id,
            'direction_label' => $callSession->directionLabel(),
            'status_label' => $callSession->status->label(),
            'display_phone' => $this->callerDisplayPhone->forSession($callSession),
            'customer_name' => $callSession->customer?->name,
            'customer_url' => $callSession->customer ? route('operations.customers.show', $callSession->customer) : null,
            'staff_name' => $callSession->owner?->name,
            'repair_order_number' => $callSession->repairOrder?->repair_order_id,
            'repair_order_url' => $callSession->repairOrder
                ? route('operations.repair-orders.show', $callSession->repairOrder)
                : null,
            'started_at_label' => $callSession->started_at?->timezone($timezone)->format('M j, Y g:i A'),
            'duration_label' => $duration !== null ? gmdate($duration >= 3600 ? 'H:i:s' : 'i:s', $duration) : '—',
            'analysis_status' => $callSession->analysis_status?->value,
            'analysis_status_label' => $callSession->analysis_status?->label() ?? 'Not queued',
            'summary' => $callSession->analysisSummary(),
            'sentiment' => $callSession->analysisSentiment(),
            'sentiment_label' => CallSessionAnalysisProjection::sentimentLabel(
                (string) ($callSession->analysisSentiment() ?? 'neutral'),
            ),
            'outcome' => data_get($analysis, 'outcome'),
            'customer_intent' => data_get($analysis, 'customer_intent'),
            'follow_up_needed' => $callSession->analysisFollowUpNeeded(),
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
            'transcript' => $callSession->transcript,
            'analysis_error' => $callSession->analysis_error,
            'analyzed_at_label' => $callSession->analyzed_at?->timezone($timezone)->format('M j, g:i A'),
            'recording_url' => $recording['recording_url'] ?? $recording['voicemail_url'],
            'can_reanalyze' => $callSession->analysisMediaKind() !== null && CallSessionAnalyzer::enabled(),
            'reanalyze_url' => route('operations.owner.call-intelligence.analyze', $callSession),
            'show_url' => route('operations.owner.call-intelligence.show', $callSession),
            'coaching_follow_up_pinned' => $callSession->isCoachingFollowUpPinned(),
            'coaching_follow_up_at_label' => $callSession->coaching_follow_up_at?->timezone($timezone)->format('M j, g:i A'),
            'toggle_coaching_follow_up_url' => route('operations.owner.call-intelligence.follow-up.toggle', $callSession),
            'coaching_pdf_url' => route('operations.owner.call-intelligence.coaching-pdf', $callSession),
            'media_kind_label' => match ($callSession->analysisMediaKind()) {
                'recording' => 'Recording',
                'voicemail' => 'Voicemail',
                default => null,
            },
        ];
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

        $missedUpsellNotes = data_get($analysis, 'missed_upsell_notes');
        if ((bool) data_get($analysis, 'missed_upsell', false) && is_string($missedUpsellNotes) && trim($missedUpsellNotes) !== '') {
            return trim($missedUpsellNotes);
        }

        return null;
    }
}
