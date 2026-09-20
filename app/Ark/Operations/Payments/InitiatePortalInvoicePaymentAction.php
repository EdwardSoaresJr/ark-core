<?php

namespace App\Ark\Operations\Payments;

use App\Ark\Operations\Financial\BalanceDueCalculator;
use App\Ark\Operations\RepairOrders\RepairOrder;
use App\Ark\Platform\Payments\ManagedPaymentsGate;
use Illuminate\Support\Str;
use RuntimeException;

final class InitiatePortalInvoicePaymentAction
{
    public function __construct(
        private readonly BalanceDueCalculator $balanceDue,
    ) {}

    public function execute(
        RepairOrder $repairOrder,
        CustomerDocumentAccessToken $accessToken,
    ): PaymentGatewayAttempt {
        abort_unless(ManagedPaymentsGate::platformCapture(), 422, 'Card capture is not connected.');

        $repairOrder = $repairOrder->fresh();
        $repairOrder->loadMissing('customer');

        $balance = $this->balanceDue->forRepairOrder($repairOrder);

        abort_unless($balance->hasIssuedInvoice, 422, 'Generate the final invoice before collecting payment.');
        abort_unless($balance->balanceDueCents > 0, 422, 'This repair order has no balance due.');

        $invoice = $this->balanceDue->issuedInvoice($repairOrder);

        if ($invoice === null) {
            throw new RuntimeException('Issued invoice is required before initiating payment.');
        }

        $idempotencyKey = (string) Str::uuid();

        return PaymentGatewayAttempt::query()->create([
            'repair_order_id' => $repairOrder->id,
            'customer_id' => $repairOrder->customer_id,
            'financial_document_id' => $invoice->id,
            'gateway' => PaymentGateway::Managed,
            'capture_surface' => PaymentCaptureSurface::Portal,
            'amount_cents' => $balance->balanceDueCents,
            'currency' => 'USD',
            'public_id' => $idempotencyKey,
            'idempotency_key' => $idempotencyKey,
            'status' => PaymentGatewayAttemptStatus::Pending,
            'initiated_by' => null,
            'customer_access_token_id' => $accessToken->id,
            'initiated_at' => now(),
        ])->refresh();
    }
}
