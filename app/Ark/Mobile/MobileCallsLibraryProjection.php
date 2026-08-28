<?php

namespace App\Ark\Mobile;

use App\Ark\Operations\Communications\CallLibraryQuery;
use App\Ark\Operations\Settings\ShopDisplayTimezone;
use App\Ark\Operations\Telephony\CallRecordingPlayback;
use App\Ark\Operations\Telephony\CallSession;
use App\Ark\Operations\Telephony\CallSessionDirection;
use App\Ark\Operations\Telephony\InboundCallerDisplayPhone;
use Illuminate\Http\Request;

/**
 * Mobile Calls & VM library — automotive rows + companion deep links.
 */
final class MobileCallsLibraryProjection
{
    public function __construct(
        private readonly CallLibraryQuery $query,
        private readonly CallRecordingPlayback $playback,
        private readonly InboundCallerDisplayPhone $callerDisplayPhone,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function forRequest(Request $request): array
    {
        $filters = $this->query->filters($request);
        $paginator = $this->query->paginate($request);
        $timezone = ShopDisplayTimezone::resolve();

        $items = collect($paginator->items())
            ->map(fn (CallSession $session): array => $this->presentRow($session, $timezone))
            ->all();

        return [
            'filters' => $filters,
            'counts' => $this->query->counts($request),
            'items' => $items,
            'pagination' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
            ],
            'poll_after_seconds' => 45,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function presentRow(CallSession $session, string $timezone): array
    {
        $session->loadMissing(['customer', 'owner:id,name', 'repairOrder.vehicle']);
        $displayPhone = $this->callerDisplayPhone->forSession($session);
        $media = $this->playback->projectFor($session);
        $vehicle = $session->repairOrder?->vehicle;

        return [
            'id' => $session->id,
            'call_session_id' => $session->id,
            'headline' => $session->customer?->name ?? ($displayPhone !== '' ? $displayPhone : 'Unknown caller'),
            'phone' => $displayPhone !== '' ? $displayPhone : 'Unknown',
            'vehicle_label' => $vehicle?->display_name,
            'plate' => $vehicle?->plate,
            'repair_order_id' => $session->repair_order_id !== null
                ? MobileRepairOrderRouteId::normalize($session->repair_order_id)
                : null,
            'direction' => $session->direction->value,
            'direction_label' => $session->direction->queueLabel(),
            'status' => $session->status->value,
            'status_label' => $session->status->operationalLabel(),
            'started_label' => $session->started_at
                ?->timezone($timezone)
                ->format('M j g:i A'),
            'age_label' => $session->started_at?->diffForHumans(short: true) ?? '',
            'handled' => $session->worked_at !== null,
            'owner_name' => $session->owner?->name,
            'has_voicemail' => $media['has_voicemail'],
            'has_recording' => $media['has_recording'],
            'voicemail_duration' => $session->voicemail_duration_seconds,
            'recording_duration' => $session->recording_duration_seconds,
            'analysis_summary' => $session->analysisSummary(),
            'needs_voicemail_attention' => filled($session->voicemail_url) && $session->worked_at === null,
            'recording_api_path' => route('api.mobile.telephony.call-sessions.recording', $session, absolute: false),
            'customer_id' => $session->customer_id,
            'deep_link' => MobileCompanionDeepLink::calls($session->id),
            'routes' => [
                'call' => MobileCompanionDeepLink::calls($session->id),
                'repair_order' => $session->repair_order_id !== null
                    ? MobileCompanionDeepLink::repairOrder(
                        (int) MobileRepairOrderRouteId::normalize($session->repair_order_id)
                    )
                    : null,
                'customer' => $session->customer_id !== null
                    ? MobileCompanionDeepLink::customer((int) $session->customer_id)
                    : null,
            ],
        ];
    }
}
