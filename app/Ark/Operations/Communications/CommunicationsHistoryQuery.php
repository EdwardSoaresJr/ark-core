<?php

namespace App\Ark\Operations\Communications;

use App\Ark\Operations\Conversations\Conversation;
use App\Ark\Operations\Conversations\ConversationContactSurface;
use App\Ark\Operations\PhoneNumber;
use App\Ark\Operations\Telephony\CallSession;
use App\Ark\Operations\Telephony\CallSessionStatus;
use App\Ark\Operations\Telephony\InboundCallerDisplayPhone;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

/**
 * Advisor-facing communications archive — all calls/conversations, any age when searched.
 */
final class CommunicationsHistoryQuery
{
    public function __construct(
        private readonly InboundCallerDisplayPhone $callerDisplayPhone,
    ) {}

    /**
     * @return array{
     *     q: string,
     *     from: string,
     *     to: string,
     *     media: string,
     * }
     */
    public function filters(Request $request): array
    {
        return [
            'q' => trim((string) $request->query('q', '')),
            // Default window stays tight — search (q) reaches all time.
            'from' => (string) $request->query('from', now()->subDays(30)->toDateString()),
            'to' => (string) $request->query('to', now()->toDateString()),
            'media' => (string) $request->query('media', ''),
        ];
    }

    /**
     * @return LengthAwarePaginator<int, CallSession>
     */
    public function paginateCalls(Request $request): LengthAwarePaginator
    {
        $filters = $this->filters($request);
        $query = CallSession::query()
            ->with(['customer', 'owner:id,name'])
            ->whereIn('status', [
                CallSessionStatus::Ringing,
                CallSessionStatus::Missed,
                CallSessionStatus::Answered,
                CallSessionStatus::Completed,
                CallSessionStatus::Failed,
            ]);

        if ($filters['q'] !== '') {
            $this->applySearch($query, $filters['q']);
        } else {
            $from = Carbon::parse($filters['from'])->startOfDay();
            $to = Carbon::parse($filters['to'])->endOfDay();
            $query->whereBetween('started_at', [$from, $to]);
        }

        if ($filters['media'] === 'recorded') {
            $query->where(function ($scoped): void {
                $scoped->whereNotNull('recording_url')
                    ->orWhereNotNull('voicemail_url');
            });
        }

        return $query
            ->orderByDesc('started_at')
            ->orderByDesc('id')
            ->paginate(40)
            ->withQueryString();
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function conversationMatches(Request $request, int $limit = 10): array
    {
        $q = trim((string) $request->query('q', ''));

        if ($q === '') {
            return [];
        }

        $digits = PhoneNumber::normalize($q) ?? preg_replace('/\D+/', '', $q);

        if ($digits === null || $digits === '') {
            return [];
        }

        return Conversation::query()
            ->where('contact_surface', ConversationContactSurface::Phone)
            ->where('contact_address', 'like', '%'.$digits.'%')
            ->with(['owner:id,name'])
            ->orderByDesc('updated_at')
            ->limit($limit)
            ->get()
            ->map(function (Conversation $conversation) use ($digits): array {
                $key = 'conversation:'.$conversation->id;
                $displayPhone = PhoneNumber::display((string) $conversation->contact_address)
                    ?? (string) $conversation->contact_address;

                return [
                    'key' => $key,
                    'kind' => 'conversation',
                    'headline' => $displayPhone,
                    'subtitle' => $conversation->owner?->name ?? 'SMS thread',
                    'channel_label' => 'Message',
                    'reason' => $conversation->status->label(),
                    'age_label' => $conversation->updated_at?->diffForHumans(short: true) ?? '',
                    'pressure_score' => null,
                    'assigned_label' => filled($conversation->owner?->name) ? (string) $conversation->owner->name : null,
                    'select_url' => route('operations.communications.history', $this->selectionQuery($key) + request()->only(['q', 'from', 'to', 'media'])),
                    'sort_at' => $conversation->updated_at?->toIso8601String() ?? '',
                ];
            })
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    public function presentCallListItem(CallSession $session): array
    {
        $session->loadMissing(['customer', 'owner:id,name']);
        $key = 'call:'.$session->id;
        $displayPhone = $this->callerDisplayPhone->forSession($session);
        $headline = $session->customer?->name ?? ($displayPhone !== '' ? $displayPhone : 'Unknown caller');
        $handled = $session->worked_at !== null;
        $hasRecording = filled($session->recording_url) || filled($session->voicemail_url);

        return [
            'key' => $key,
            'kind' => 'call',
            'headline' => $headline,
            'subtitle' => $displayPhone !== '' ? $displayPhone : 'Unknown caller',
            'snippet' => $hasRecording ? 'Recording on file' : '',
            'channel_label' => 'Phone',
            'reason' => trim(($handled ? 'Handled · ' : '').$session->status->operationalLabel()),
            'age_label' => $session->started_at?->diffForHumans(short: true) ?? '',
            'pressure_score' => null,
            'assigned_label' => filled($session->owned_by_user_id)
                ? ($session->owner?->name ?? null)
                : null,
            'select_url' => route('operations.communications.history', $this->selectionQuery($key) + request()->only(['q', 'from', 'to', 'media', 'page'])),
            'sort_at' => $session->started_at?->toIso8601String() ?? '',
        ];
    }

    /**
     * @param  \Illuminate\Database\Eloquent\Builder<CallSession>  $query
     */
    private function applySearch(\Illuminate\Database\Eloquent\Builder $query, string $q): void
    {
        $digits = PhoneNumber::normalize($q) ?? preg_replace('/\D+/', '', $q);
        $like = '%'.addcslashes($q, '%_').'%';

        $query->where(function ($scoped) use ($digits, $like): void {
            if ($digits !== null && $digits !== '') {
                $scoped->where('normalized_from', 'like', '%'.$digits.'%')
                    ->orWhere('normalized_to', 'like', '%'.$digits.'%');
            }

            $scoped->orWhereHas('customer', function ($customerQuery) use ($like): void {
                $customerQuery->where('first_name', 'like', $like)
                    ->orWhere('last_name', 'like', $like);
            });
        });
    }

    /**
     * @return array<string, int|string>
     */
    private function selectionQuery(string $key): array
    {
        [$kind, $id] = explode(':', $key, 2);

        return match ($kind) {
            'conversation' => ['conversation' => (int) $id],
            'call' => ['call' => (int) $id],
            default => [],
        };
    }
}
