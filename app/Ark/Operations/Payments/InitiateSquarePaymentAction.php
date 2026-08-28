<?php

namespace App\Ark\Operations\Payments;

use App\Ark\Operations\Financial\BalanceDueCalculator;
use App\Ark\Operations\Financial\EstimateTotalsCalculator;
use App\Ark\Operations\Payments\Contracts\SquarePaymentsClient;
use App\Ark\Operations\RepairOrders\RepairOrder;
use App\Models\User;
use Illuminate\Support\Str;
use RuntimeException;

final class InitiateSquarePaymentAction
{
    public function __construct(
        private readonly SquareConfiguration $configuration,
        private readonly BalanceDueCalculator $balanceDue,
        private readonly EstimateTotalsCalculator $totalsCalculator,
        private readonly SquarePaymentsClient $square,
    ) {}

    public function execute(
        RepairOrder $repairOrder,
        PaymentCaptureSurface $surface,
        ?User $actor = null,
        ?float $requestedAmount = null,
        ?CustomerDocumentAccessToken $accessToken = null,
    ): PaymentGatewayAttempt {
        abort_unless($this->configuration->operational(), 422, 'Square payments are not configured.');

        $repairOrder = $repairOrder->fresh();
        $repairOrder->loadMissing('customer');

        $balance = $this->balanceDue->forRepairOrder($repairOrder);

        abort_unless($balance->hasIssuedInvoice, 422, 'Generate the final invoice before collecting payment.');
        abort_unless($balance->balanceDueCents > 0, 422, 'This repair order has no balance due.');

        $amountCents = $requestedAmount !== null
            ? $this->totalsCalculator->unitPriceCents($requestedAmount)
            : $balance->balanceDueCents;

        abort_if($amountCents <= 0, 422, 'Payment amount must be greater than zero.');
        abort_if($amountCents > $balance->balanceDueCents, 422, 'Payment amount cannot exceed the balance due.');

        $invoice = $this->balanceDue->issuedInvoice($repairOrder);

        if ($invoice === null) {
            throw new RuntimeException('Issued invoice is required before initiating Square payment.');
        }

        $attempt = PaymentGatewayAttempt::query()->create([
            'repair_order_id' => $repairOrder->id,
            'customer_id' => $repairOrder->customer_id,
            'financial_document_id' => $invoice->id,
            'gateway' => PaymentGateway::Square,
            'capture_surface' => $surface,
            'amount_cents' => $amountCents,
            'currency' => 'USD',
            'idempotency_key' => (string) Str::uuid(),
            'status' => PaymentGatewayAttemptStatus::Pending,
            'initiated_by' => $actor?->id,
            'customer_access_token_id' => $accessToken?->id,
            'initiated_at' => now(),
        ]);

        $attempt->load('repairOrder');

        if ($surface === PaymentCaptureSurface::Terminal) {
            abort_unless($this->configuration->terminalEnabled(), 422, 'Square terminal capture is not enabled.');

            $checkout = $this->square->createTerminalCheckout($attempt);

            $attempt->forceFill([
                'square_checkout_id' => $checkout->checkoutId,
            ])->save();
        }

        return $attempt->refresh();
    }
}
