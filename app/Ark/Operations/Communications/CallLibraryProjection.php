<?php

namespace App\Ark\Operations\Communications;

use App\Ark\Operations\Settings\ShopDisplayTimezone;
use App\Ark\Operations\Telephony\CallRecordingPlayback;
use App\Ark\Operations\Telephony\CallSession;
use App\Ark\Operations\Telephony\CallSessionDirection;
use App\Ark\Operations\Telephony\InboundCallerDisplayPhone;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Request;

final class CallLibraryProjection
{
    public function __construct(
        private readonly CallLibraryQuery $query,
        private readonly CallRecordingPlayback $playback,
        private readonly InboundCallerDisplayPhone $callerDisplayPhone,
    ) {}

    /**
     * @return array{
     *     section: string,
     *     filters: array<string, string>,
     *     counts: array{voicemail: int, recording: int, missed_unhandled: int},
     *     rows: list<array<string, mixed>>,
     *     paginator: LengthAwarePaginator<int, CallSession>,
     * }
     */
    public function build(Request $request): array
    {
        $filters = $this->query->filters($request);
        $paginator = $this->query->paginate($request);
        $timezone = ShopDisplayTimezone::resolve();

        $rows = collect($paginator->items())
            ->map(fn (CallSession $session): array => $this->presentRow($session, $timezone))
            ->all();

        return [
            'section' => 'calls',
            'filters' => $filters,
            'counts' => $this->query->counts($request),
            'rows' => $rows,
            'paginator' => $paginator,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function presentRow(CallSession $session, string $timezone): array
    {
        $session->loadMissing(['customer', 'owner:id,name', 'repairOrder']);
        $displayPhone = $this->callerDisplayPhone->forSession($session);
        $media = $this->playback->projectFor($session);
        $phone = $session->direction === CallSessionDirection::Outbound
            ? ($session->normalized_to ?? $session->to_number)
            : ($session->normalized_from ?? $session->from_number);

        return [
            'id' => $session->id,
            'headline' => $session->customer?->name ?? ($displayPhone !== '' ? $displayPhone : 'Unknown caller'),
            'phone' => $displayPhone !== '' ? $displayPhone : 'Unknown',
            'direction' => $session->direction->queueLabel(),
            'status' => $session->status->operationalLabel(),
            'started_label' => $session->started_at
                ?->timezone($timezone)
                ->format('M j g:i A'),
            'age_label' => $session->started_at?->diffForHumans(short: true) ?? '',
            'handled' => $session->worked_at !== null,
            'owner' => $session->owner?->name,
            'voicemail_url' => $media['voicemail_url'],
            'recording_url' => $media['recording_url'],
            'has_voicemail' => $media['has_voicemail'],
            'has_recording' => $media['has_recording'],
            'voicemail_duration' => $session->voicemail_duration_seconds,
            'recording_duration' => $session->recording_duration_seconds,
            'customer_url' => $session->customer_id !== null
                ? route('operations.customers.show', $session->customer_id)
                : null,
            'text_url' => $session->customer_id !== null && filled($displayPhone)
                ? route('operations.customers.show', $session->customer_id).'?compose=text#customer-communication'
                : null,
            'repair_order_url' => $session->repair_order_id !== null
                ? route('operations.repair-orders.show', $session->repair_order_id)
                : null,
            'lookup_url' => filled($phone)
                ? route('operations.caller-lookup', ['phone' => $phone])
                : null,
            'mark_handled_url' => route('operations.communications.calls.mark-handled', $session),
        ];
    }
}
