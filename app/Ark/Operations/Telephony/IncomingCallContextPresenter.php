<?php

namespace App\Ark\Operations\Telephony;

use App\Ark\Operations\Conversations\ConversationMessage;
use App\Ark\Operations\Conversations\CustomerCallContext;
use App\Ark\Operations\Conversations\CustomerCallContextOpenRepairOrder;
use App\Ark\Operations\Encounters\EncounterSource;
use App\Ark\Operations\Leads\IngressCreateContactUrl;
use App\Ark\Orientation\Orientation;
use App\Ark\Orientation\OrientationDensity;
use Illuminate\Support\Facades\Route;

class IncomingCallContextPresenter
{
    public function __construct(
        private readonly TelephonyCallbackPresenter $callbackPresenter,
        private readonly InboundCallerDisplayPhone $callerDisplayPhone,
        private readonly Orientation $orientation,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function present(CallSession $session, ?CustomerCallContext $context): array
    {
        $customer = $context?->customer;
        $matched = $customer !== null;

        $displayPhone = $context?->displayPhone ?? $this->callerDisplayPhone->forSession($session);
        $normalizedPhone = $this->callerDisplayPhone->normalizedForSession($session) ?? $session->normalized_from;

        $payload = [
            'call_session_id' => $session->id,
            'provider_call_sid' => $session->provider_call_sid,
            'direction' => $session->direction->value,
            'direction_label' => $session->direction->queueLabel(),
            'status' => $session->status->value,
            'status_label' => $this->statusLabel($session->status),
            'is_actively_live' => $session->isActivelyLive(),
            'owned_by_user_id' => $session->owned_by_user_id,
            'owned_by_name' => $session->owner?->name,
            'display_phone' => $displayPhone,
            'normalized_from' => $normalizedPhone,
            'matched' => $matched,
            'customer_id' => $customer?->id,
            'customer_name' => $customer?->name,
            'customer_type' => $customer?->customer_type,
            'referral_label' => $customer?->referral_source
                ? (EncounterSource::tryFrom($customer->referral_source)?->label() ?? $customer->referral_source)
                : null,
            'vehicles' => $context?->vehicles
                ->map(fn ($vehicle): array => [
                    'display_name' => $vehicle->display_name,
                    'plate' => $vehicle->plate,
                ])
                ->values()
                ->all() ?? [],
            'open_repair_orders' => $context?->openRepairOrders
                ->map(fn (CustomerCallContextOpenRepairOrder $openRepairOrder): array => [
                    'repair_order_id' => $openRepairOrder->repairOrder->repair_order_id,
                    'vehicle_name' => $openRepairOrder->vehicle->display_name,
                    'status_label' => $openRepairOrder->repairOrder->statusDisplayLabel(),
                    'workflow_posture' => $openRepairOrder->workflowPostureLabel,
                    'workflow_next_action' => $openRepairOrder->workflowNextAction,
                    'orientation' => $openRepairOrder->orientation,
                    'url' => Route::has('operations.repair-orders.show')
                        ? route('operations.repair-orders.show', $openRepairOrder->repairOrder)
                        : null,
                ])
                ->values()
                ->all() ?? [],
            'orientation' => ($primaryOpenRepairOrder = $context?->openRepairOrders->first()) !== null
                ? array_merge(
                    $this->orientation->repairOrder($primaryOpenRepairOrder->repairOrder, OrientationDensity::Interrupt),
                    ['density' => OrientationDensity::Interrupt->value],
                )
                : null,
            'recent_conversation' => $context?->recentConversationMessages
                ->map(fn (ConversationMessage $message): array => [
                    'participant' => $message->participant->displayLabel(),
                    'body' => $message->body,
                    'occurred_at' => $message->occurred_at?->timezone(config('app.display_timezone'))->format('M j, g:i A'),
                ])
                ->values()
                ->all() ?? [],
            'lookup_url' => route('operations.caller-lookup', ['phone' => $normalizedPhone]),
            'customer_url' => $matched ? route('operations.customers.show', $customer) : null,
            'create_contact_url' => $matched
                ? null
                : IngressCreateContactUrl::forPhone($normalizedPhone, callSessionId: $session->id),
            'intake_url' => route('operations.intake.create', [
                'phone' => $normalizedPhone,
            ]),
        ];

        return array_merge(
            $payload,
            $this->callbackPresenter->forCallContext($payload, $session),
            $this->ringConnectionPosture($session),
        );
    }

    /**
     * @return array{cell_screening: bool, status_label?: string}
     */
    private function ringConnectionPosture(CallSession $session): array
    {
        $providerCallSid = $session->provider_call_sid;

        if (! filled($providerCallSid)) {
            return ['cell_screening' => false];
        }

        $state = app(TelephonyRingState::class)->get($providerCallSid);

        if (
            $state !== null
            && ($state['cell_screening'] ?? false)
            && ! ($state['answered'] ?? false)
        ) {
            return [
                'cell_screening' => true,
                'status_label' => 'Press 1 on cell',
            ];
        }

        return ['cell_screening' => false];
    }

    private function statusLabel(CallSessionStatus $status): string
    {
        return $status->operationalLabel();
    }
}
