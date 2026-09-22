<?php

namespace App\Ark\Operations\Payments;

use App\Ark\Operations\Financial\BalanceDueCalculator;
use App\Ark\Operations\Financial\EstimateTotalsCalculator;
use App\Ark\Operations\Financial\RepairOrderDepositRecordingGuard;
use App\Ark\Operations\Payments\Contracts\SquarePaymentsClient;
use App\Ark\Platform\Payments\ManagedPaymentsGate;
use Illuminate\Validation\ValidationException;
use App\Ark\Operations\RepairOrders\RepairOrder;
use App\Models\User;
use Illuminate\Support\Str;
use RuntimeException;

final class InitiateSquareDepositAction
{
    public function __construct(
        private readonly SquareConfiguration $configuration,
        private readonly BalanceDueCalculator $balanceDue,
        private readonly EstimateTotalsCalculator $totalsCalculator,
        private readonly RepairOrderDepositRecordingGuard $depositGuard,
        private readonly SquarePaymentsClient $square,
        private readonly RequestPlatformPaymentCaptureAction $platformCapture,
    ) {}

    public function execute(
        RepairOrder $repairOrder,
        PaymentCaptureSurface $surface,
        ?User $actor = null,
        ?float $requestedAmount = null,
    ): PaymentGatewayAttempt {
        $managed = ManagedPaymentsGate::platformCapture();
        abort_unless(
            $managed || $this->configuration->operational(),
            422,
            $managed ? 'Card capture is not connected.' : 'Square payments are not configured.',
        );

        abort_unless(
            in_array($surface, [PaymentCaptureSurface::Terminal, PaymentCaptureSurface::Keyed], true),
            422,
            'Only terminal or keyed capture is supported for counter deposits.',
        );

        $repairOrder = $repairOrder->fresh();
        $repairOrder->loadMissing('customer');
        $repairOrder->ensureOpenForEditing();

        $balance = $this->balanceDue->forRepairOrder($repairOrder);

        abort_if($repairOrder->isTerminal(), 422, 'Deposits cannot be recorded on closed repair orders.');
        abort_if($balance->hasIssuedInvoice, 422, 'Deposits cannot be recorded after the final invoice is issued.');

        if ($requestedAmount === null) {
            throw new RuntimeException('Deposit amount is required.');
        }

        $amountCents = $this->totalsCalculator->unitPriceCents($requestedAmount);

        abort_if($amountCents <= 0, 422, 'Deposit amount must be greater than zero.');

        try {
            $this->depositGuard->validateAmount($repairOrder, $amountCents);
        } catch (ValidationException $exception) {
            abort(422, collect($exception->errors())->flatten()->first() ?? 'Deposit amount is not allowed.');
        }

        $idempotencyKey = (string) Str::uuid();
        $attempt = PaymentGatewayAttempt::query()->create([
            'repair_order_id' => $repairOrder->id,
            'customer_id' => $repairOrder->customer_id,
            'financial_document_id' => null,
            'gateway' => $managed ? PaymentGateway::Managed : PaymentGateway::Square,
            'capture_surface' => $surface,
            'amount_cents' => $amountCents,
            'currency' => 'USD',
            'public_id' => $idempotencyKey,
            'idempotency_key' => $idempotencyKey,
            'status' => PaymentGatewayAttemptStatus::Pending,
            'initiated_by' => $actor?->id,
            'initiated_at' => now(),
        ]);

        $attempt->load('repairOrder');

        if ($managed) {
            if ($surface === PaymentCaptureSurface::Terminal) {
                return $this->platformCapture->execute($attempt);
            }

            return $attempt->refresh();
        }

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
