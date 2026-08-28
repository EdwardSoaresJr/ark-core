<?php

namespace App\Ark\Operations\Financial;

use App\Ark\Operations\Events\EventContract;
use App\Ark\Operations\Events\OperationalEvent;
use App\Ark\Operations\Events\OperationalEventRecorder;
use App\Ark\Operations\RepairOrders\RepairOrder;
use App\Models\User;

/**
 * Sole emitter for the Payment Received event contract.
 *
 * Truth lost if this disappeared: the shop no longer knows money was received.
 *
 * @see docs/mobile/contract-realization-register-v1.md
 */
final class EmitPaymentReceivedEvent
{
    public function __construct(
        private readonly OperationalEventRecorder $events,
        private readonly BalanceDueCalculator $balanceDue,
        private readonly NotifyRepairOrderFinancialChange $notifyFinancialChange,
    ) {}

    /**
     * @param  array<string, mixed>  $extraPayload
     */
    public function emit(
        RepairOrder $repairOrder,
        int $amountCents,
        PaymentMethod $method,
        ?User $actor = null,
        ?string $reference = null,
        ?int $ledgerEntryId = null,
        array $extraPayload = [],
    ): OperationalEvent {
        $repairOrder = $repairOrder->fresh();
        $balance = $this->balanceDue->forRepairOrder($repairOrder);
        $contract = EventContract::PaymentReceived;

        $event = $this->events->record(
            $contract->operationalEventName(),
            $repairOrder,
            actor: $actor,
            payload: array_merge([
                'event_contract' => $contract->value,
                'amount_cents' => $amountCents,
                'balance_due_cents' => $balance->balanceDueCents,
                'payment_status' => $repairOrder->paymentStatus()->value,
                'payment_method' => $method->value,
                'ledger_entry_id' => $ledgerEntryId,
                'repair_order_id' => $repairOrder->id,
                'reference' => $reference,
            ], $extraPayload),
        );

        $this->notifyFinancialChange->notify($repairOrder, reason: 'payment_received', actor: $actor);

        return $event;
    }
}
