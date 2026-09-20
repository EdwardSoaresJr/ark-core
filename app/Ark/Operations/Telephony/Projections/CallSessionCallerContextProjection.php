<?php

namespace App\Ark\Operations\Telephony\Projections;

use App\Ark\Operations\Conversations\ConversationMessage;
use App\Ark\Operations\Conversations\CustomerCallContext;
use App\Ark\Operations\Conversations\CustomerCallContextOpenRepairOrder;
use App\Ark\Operations\Conversations\CustomerCallContextResolver;
use App\Ark\Operations\Telephony\CallSession;
use App\Ark\Operations\Telephony\CallSessionDirection;
use App\Ark\Operations\Telephony\InboundCallerDisplayPhone;
use App\Ark\Operations\Telephony\IncomingCallContextPresenter;

/**
 * Read-only shadow pop for Asterisk (or any provider) call sessions.
 */
class CallSessionCallerContextProjection
{
    public function __construct(
        private readonly CustomerCallContextResolver $callContextResolver,
        private readonly IncomingCallContextPresenter $incomingCallContextPresenter,
        private readonly InboundCallerDisplayPhone $callerDisplayPhone,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function forSession(CallSession $session): array
    {
        $context = $this->resolveContext($session);
        $incoming = $this->incomingCallContextPresenter->present($session, $context);
        $primaryOpenRo = $context?->openRepairOrders->first();
        $lastMessage = $context?->recentConversationMessages->first();

        return [
            'call_session_id' => $session->id,
            'provider' => $session->provider->value,
            'provider_call_sid' => $session->provider_call_sid,
            'status' => $session->status->value,
            'status_label' => $session->status->operationalLabel(),
            'direction' => $session->direction->value,
            'matched' => $context?->customer !== null,
            'customer' => $context?->customer === null ? null : [
                'id' => $context->customer->id,
                'name' => $context->customer->name,
            ],
            'vehicle' => $primaryOpenRo === null ? null : [
                'display_name' => $primaryOpenRo->vehicle->display_name,
                'plate' => $primaryOpenRo->vehicle->plate,
            ],
            'repair_order' => $primaryOpenRo === null ? null : [
                'id' => $primaryOpenRo->repairOrder->id,
                'repair_order_id' => $primaryOpenRo->repairOrder->repair_order_id,
                'status_label' => $primaryOpenRo->repairOrder->statusDisplayLabel(),
                'workflow_posture' => $primaryOpenRo->workflowPostureLabel,
                'workflow_next_action' => $primaryOpenRo->workflowNextAction,
            ],
            'last_message' => $lastMessage === null ? null : [
                'participant' => $lastMessage->participant->displayLabel(),
                'body' => $lastMessage->body,
                'occurred_at' => $lastMessage->occurred_at?->timezone(config('app.display_timezone'))->format('M j, g:i A'),
            ],
            'attention_reason' => $this->attentionReason($primaryOpenRo, $lastMessage),
            'open_repair_orders' => $incoming['open_repair_orders'] ?? [],
            'incoming_call_context' => $incoming,
        ];
    }

    private function resolveContext(CallSession $session): ?CustomerCallContext
    {
        if ($session->customer_id !== null) {
            $session->loadMissing('customer');

            return $this->callContextResolver->resolveForCustomer($session->customer);
        }

        $lookupPhone = $session->direction === CallSessionDirection::Outbound
            ? ($session->normalized_to ?? $session->normalized_from)
            : ($this->callerDisplayPhone->normalizedForSession($session) ?? $session->normalized_from);

        return $this->callContextResolver->resolve($lookupPhone);
    }

    private function attentionReason(
        ?CustomerCallContextOpenRepairOrder $primaryOpenRo,
        ?ConversationMessage $lastMessage,
    ): ?string {
        if ($primaryOpenRo !== null && filled($primaryOpenRo->workflowNextAction)) {
            return $primaryOpenRo->workflowNextAction;
        }

        if ($primaryOpenRo !== null && filled($primaryOpenRo->workflowPostureLabel)) {
            return $primaryOpenRo->workflowPostureLabel;
        }

        if ($lastMessage !== null && filled($lastMessage->body)) {
            return $lastMessage->body;
        }

        return null;
    }
}
