<?php

namespace App\Ark\Operations\Communications;

use App\Ark\Operations\Telephony\CallSession;
use App\Ark\Operations\Telephony\CallSessionAnalysisProjection;
use App\Ark\Operations\Telephony\CallSessionAnalysisStatus;
use App\Ark\Operations\Telephony\CallSessionIntelligenceQuery;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator as Paginator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

final class CommunicationIntelligenceIndexQuery
{
    public function __construct(
        private readonly CallSessionIntelligenceQuery $calls,
        private readonly SmsIntelligenceQuery $sms,
    ) {}

    /**
     * @return LengthAwarePaginator<int, array<string, mixed>>
     */
    public function paginate(Request $request): LengthAwarePaginator
    {
        $channel = (string) $request->query('channel', 'all');

        if ($channel === 'calls') {
            return $this->calls->paginate($request)->through(
                fn (CallSession $session): array => $this->presentCallRow($session),
            );
        }

        if ($channel === 'sms') {
            return $this->sms->paginate($request)->through(
                fn (ConversationSmsIntelligenceSlice $slice): array => $this->sms->presentRow($slice),
            );
        }

        return $this->mergedPaginate($request);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function coachingQueue(Request $request, int $limit = 10): array
    {
        $channel = (string) $request->query('channel', 'all');

        if ($channel === 'calls') {
            return $this->calls->coachingQueue($request, $limit);
        }

        if ($channel === 'sms') {
            return $this->sms->coachingQueue($request, $limit);
        }

        $rows = collect($this->calls->coachingQueue($request, $limit * 2))
            ->map(fn (array $row): array => array_merge($row, [
                'kind' => 'call',
                'channel_label' => 'Call',
                'open_label' => 'Open call',
            ]))
            ->merge($this->sms->coachingQueue($request, $limit * 2))
            ->sort(function (array $left, array $right): int {
                $leftPinned = (bool) ($left['coaching_follow_up_pinned'] ?? false);
                $rightPinned = (bool) ($right['coaching_follow_up_pinned'] ?? false);

                if ($leftPinned !== $rightPinned) {
                    return $rightPinned <=> $leftPinned;
                }

                $leftAnalysis = [
                    'coaching_priority' => $left['coaching_priority'] ?? 'none',
                    'empathy_score' => $left['empathy_score'] ?? null,
                    'missed_upsell' => $left['missed_upsell'] ?? false,
                ];
                $rightAnalysis = [
                    'coaching_priority' => $right['coaching_priority'] ?? 'none',
                    'empathy_score' => $right['empathy_score'] ?? null,
                    'missed_upsell' => $right['missed_upsell'] ?? false,
                ];

                $weightCompare = CallSessionAnalysisProjection::coachingUrgencyWeight($rightAnalysis)
                    <=> CallSessionAnalysisProjection::coachingUrgencyWeight($leftAnalysis);

                if ($weightCompare !== 0) {
                    return $weightCompare;
                }

                return 0;
            })
            ->take($limit)
            ->values()
            ->all();

        return $rows;
    }

    /**
     * @return array<string, mixed>
     */
    public function presentCallRow(CallSession $callSession): array
    {
        $row = $this->calls->presentRow($callSession);
        $row['kind'] = 'call';
        $row['channel_label'] = 'Call';
        $row['open_label'] = 'Open call';

        return $row;
    }

    /**
     * @return LengthAwarePaginator<int, array<string, mixed>>
     */
    private function mergedPaginate(Request $request): LengthAwarePaginator
    {
        $from = Carbon::parse($request->query('from', now()->subDays(30)->toDateString()))->startOfDay();
        $to = Carbon::parse($request->query('to', now()->toDateString()))->endOfDay();
        $page = max(1, (int) $request->query('page', 1));
        $perPage = 25;

        $callQuery = CallSession::query()
            ->selectRaw("id as row_id, 'call' as row_kind, CASE WHEN coaching_follow_up_at IS NOT NULL THEN 0 ELSE 1 END as pin_rank, COALESCE(coaching_follow_up_at, started_at) as sort_at")
            ->whereBetween('started_at', [$from, $to]);

        if ($request->query('media') === 'recorded') {
            $callQuery->where(function ($scoped): void {
                $scoped->whereNotNull('recording_url')
                    ->orWhereNotNull('voicemail_url');
            });
        }

        $this->applyCallAnalysisFilter($callQuery, $request);

        $smsQuery = ConversationSmsIntelligenceSlice::query()
            ->selectRaw("id as row_id, 'sms' as row_kind, CASE WHEN coaching_follow_up_at IS NOT NULL THEN 0 ELSE 1 END as pin_rank, COALESCE(coaching_follow_up_at, last_message_at) as sort_at")
            ->where('message_count', '>=', ConversationSmsIntelligenceSliceTouch::MIN_MESSAGES)
            ->whereBetween('last_message_at', [$from, $to]);

        $this->applySmsAnalysisFilter($smsQuery, $request);

        $union = $callQuery->unionAll($smsQuery);

        $total = DB::query()->fromSub($union, 'merged_rows')->count();

        /** @var \Illuminate\Support\Collection<int, object{row_id: int, row_kind: string}> $pageRows */
        $pageRows = DB::query()
            ->fromSub($union, 'merged_rows')
            ->orderBy('pin_rank')
            ->orderByDesc('sort_at')
            ->orderByDesc('row_id')
            ->forPage($page, $perPage)
            ->get();

        $callIds = $pageRows->where('row_kind', 'call')->pluck('row_id')->all();
        $smsIds = $pageRows->where('row_kind', 'sms')->pluck('row_id')->all();

        $callsById = CallSession::query()
            ->with(['customer', 'owner', 'repairOrder'])
            ->whereIn('id', $callIds)
            ->get()
            ->keyBy('id');

        $smsById = ConversationSmsIntelligenceSlice::query()
            ->with([
                'conversation.owner',
                'conversation.participants.customer',
                'conversation.links',
            ])
            ->whereIn('id', $smsIds)
            ->get()
            ->keyBy('id');

        $rows = $pageRows->map(function (object $row) use ($callsById, $smsById): array {
            if ($row->row_kind === 'sms') {
                /** @var ConversationSmsIntelligenceSlice|null $slice */
                $slice = $smsById->get($row->row_id);

                return $slice ? $this->sms->presentRow($slice) : [];
            }

            /** @var CallSession|null $session */
            $session = $callsById->get($row->row_id);

            return $session ? $this->presentCallRow($session) : [];
        })->filter(fn (array $row): bool => $row !== [])->values();

        return new Paginator(
            $rows,
            $total,
            $perPage,
            $page,
            ['path' => $request->url(), 'query' => $request->query()],
        );
    }

    /**
     * @param  \Illuminate\Database\Eloquent\Builder<CallSession>  $query
     */
    private function applyCallAnalysisFilter(\Illuminate\Database\Eloquent\Builder $query, Request $request): void
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
     * @param  \Illuminate\Database\Eloquent\Builder<ConversationSmsIntelligenceSlice>  $query
     */
    private function applySmsAnalysisFilter(\Illuminate\Database\Eloquent\Builder $query, Request $request): void
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
}
