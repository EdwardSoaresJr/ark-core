<?php

namespace App\Ark\Mobile;

use App\Ark\Operations\Conversations\CustomerCallContext;
use App\Ark\Operations\Conversations\CustomerCallContextOpenRepairOrder;
use App\Ark\Operations\Conversations\CustomerCallContextResolver;
use App\Ark\Operations\Telephony\CallSession;
use App\Ark\Operations\Telephony\CallSessionDirection;
use App\Ark\Operations\Telephony\InboundCallerDisplayPhone;
use App\Ark\Operations\Telephony\Projections\CallSessionCallerContextProjection;

/**
 * Incoming call screen payload — customer · vehicle · RO · estimate · last message.
 */
final class MobileIncomingCallContextProjection
{
    public function __construct(
        private readonly CustomerCallContextResolver $callContextResolver,
        private readonly CallSessionCallerContextProjection $callerContextProjection,
        private readonly InboundCallerDisplayPhone $callerDisplayPhone,
        private readonly MobileEstimateProjection $estimateProjection,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function forPhone(?string $phone): array
    {
        $context = $this->callContextResolver->resolve($phone);

        return $this->presentContext($context, callSessionId: null);
    }

    /**
     * @return array<string, mixed>
     */
    public function forCallSession(CallSession $session): array
    {
        $payload = $this->callerContextProjection->forSession($session);
        $context = $this->resolveContextFromPayload($session);

        return array_merge(
            $this->presentContext($context, callSessionId: $session->id),
            [
                'call_session_id' => $session->id,
                'direction' => $session->direction->value,
                'status' => $session->status->value,
                'status_label' => $session->status->operationalLabel(),
                'caller_context' => $payload,
            ],
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function presentContext(?CustomerCallContext $context, ?int $callSessionId): array
    {
        $customer = $context?->customer;
        $primaryOpen = $context?->openRepairOrders->first();
        $lastMessage = $context?->recentConversationMessages->first();
        $phone = $context?->displayPhone ?? '';
        $normalized = $context?->normalizedPhone ?? '';

        $estimate = null;
        if ($primaryOpen !== null) {
            $estimate = $this->estimateProjection->summary($primaryOpen->repairOrder);
        }

        return [
            'matched' => $customer !== null,
            'phone' => $phone,
            'normalized_phone' => $normalized,
            'customer' => $customer === null ? null : [
                'id' => $customer->id,
                'name' => $customer->name,
            ],
            'vehicle' => $primaryOpen === null ? null : [
                'id' => $primaryOpen->vehicle->id,
                'label' => $primaryOpen->vehicle->display_name,
                'plate' => $primaryOpen->vehicle->plate,
            ],
            'repair_order' => $primaryOpen === null ? null : [
                'id' => $primaryOpen->repairOrder->id,
                'repair_order_id' => $primaryOpen->repairOrder->repair_order_id,
                'status_label' => $primaryOpen->repairOrder->statusDisplayLabel(),
                'lifecycle_label' => $primaryOpen->workflowPostureLabel,
                'next_action' => $primaryOpen->workflowNextAction,
            ],
            'open_repair_order_count' => $context?->openRepairOrders->count() ?? 0,
            'estimate' => $estimate === null ? null : [
                'total_label' => $estimate['estimate_total_label'] ?? null,
                'approved_label' => $estimate['approved_total_label'] ?? null,
                'balance_due_label' => $estimate['balance_due_label'] ?? null,
                'has_invoice' => $estimate['has_issued_invoice'] ?? false,
            ],
            'last_message' => $lastMessage === null ? null : [
                'body' => $lastMessage->body,
                'participant' => $lastMessage->participant->displayLabel(),
                'occurred_at' => $lastMessage->occurred_at?->toIso8601String(),
            ],
            'deep_link' => MobileCompanionDeepLink::incomingCall($callSessionId, $normalized !== '' ? $normalized : null),
            'routes' => [
                'customer' => $customer !== null ? MobileCompanionDeepLink::customer($customer->id) : null,
                'repair_order' => $primaryOpen !== null
                    ? MobileCompanionDeepLink::repairOrder($primaryOpen->repairOrder->repair_order_id)
                    : null,
            ],
        ];
    }

    private function resolveContextFromPayload(CallSession $session): ?CustomerCallContext
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
}
