<?php

namespace App\Ark\Operations\Communications;

use App\Ark\Operations\PhoneNumber;
use App\Ark\Operations\Telephony\CallSession;
use App\Ark\Operations\Telephony\CallSessionDirection;
use App\Ark\Operations\Telephony\CallSessionStatus;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

/**
 * Advisor call library — phones only, with voicemail and recording playback.
 */
final class CallLibraryQuery
{
    /**
     * @return array{filter: string, handled: string, q: string, from: string, to: string}
     */
    public function filters(Request $request): array
    {
        $filter = (string) $request->query('filter', 'all');

        if (! in_array($filter, ['all', 'voicemail', 'recording', 'missed'], true)) {
            $filter = 'all';
        }

        $handled = (string) $request->query('handled', 'all');

        if (! in_array($handled, ['all', 'unhandled', 'handled'], true)) {
            $handled = 'all';
        }

        return [
            'filter' => $filter,
            'handled' => $handled,
            'q' => trim((string) $request->query('q', '')),
            'from' => (string) $request->query('from', now()->subDays(30)->toDateString()),
            'to' => (string) $request->query('to', now()->toDateString()),
        ];
    }

    /**
     * @return LengthAwarePaginator<int, CallSession>
     */
    public function paginate(Request $request): LengthAwarePaginator
    {
        $filters = $this->filters($request);
        $from = Carbon::parse($filters['from'])->startOfDay();
        $to = Carbon::parse($filters['to'])->endOfDay();

        $query = CallSession::query()
            ->with(['customer', 'owner:id,name', 'repairOrder'])
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
            $query->whereBetween('started_at', [$from, $to]);
        }

        match ($filters['filter']) {
            'voicemail' => $query->whereNotNull('voicemail_url'),
            'recording' => $query->whereNotNull('recording_url'),
            'missed' => $query->where(function ($scoped): void {
                $scoped->where('status', CallSessionStatus::Missed)
                    ->orWhere(function ($inbound): void {
                        $inbound->where('direction', CallSessionDirection::Inbound)
                            ->whereNull('answered_at')
                            ->whereIn('status', [CallSessionStatus::Completed, CallSessionStatus::Failed]);
                    });
            }),
            default => null,
        };

        match ($filters['handled']) {
            'unhandled' => $query->whereNull('worked_at'),
            'handled' => $query->whereNotNull('worked_at'),
            default => null,
        };

        return $query
            ->orderByDesc('started_at')
            ->orderByDesc('id')
            ->paginate(50)
            ->withQueryString();
    }

    /**
     * @return array{voicemail: int, recording: int, missed_unhandled: int}
     */
    public function counts(Request $request): array
    {
        $from = Carbon::parse($this->filters($request)['from'])->startOfDay();
        $to = Carbon::parse($this->filters($request)['to'])->endOfDay();

        $base = CallSession::query()->whereBetween('started_at', [$from, $to]);

        return [
            'voicemail' => (clone $base)->whereNotNull('voicemail_url')->count(),
            'recording' => (clone $base)->whereNotNull('recording_url')->count(),
            'missed_unhandled' => (clone $base)
                ->whereNull('worked_at')
                ->where(function ($scoped): void {
                    $scoped->where('status', CallSessionStatus::Missed)
                        ->orWhere(function ($inbound): void {
                            $inbound->where('direction', CallSessionDirection::Inbound)
                                ->whereNull('answered_at');
                        });
                })
                ->count(),
            'unhandled_voicemail' => (clone $base)
                ->whereNotNull('voicemail_url')
                ->whereNull('worked_at')
                ->count(),
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
}
