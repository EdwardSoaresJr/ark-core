<?php

namespace App\Ark\Operations\Payments;

use App\Ark\Operations\RepairOrders\RepairOrder;
use Illuminate\Support\Str;

final class InitiatePortalDepositRequestAction
{
    public function __construct(
        private readonly SquareConfiguration $configuration,
    ) {}

    public function execute(
        RepairOrder $repairOrder,
        CustomerDocumentAccessToken $accessToken,
    ): PaymentGatewayAttempt {
        abort_unless($this->configuration->portalPayEnabled(), 422, 'Online deposits are not enabled.');
        abort_unless($accessToken->isDepositRequest(), 422, 'This pay link is not a deposit request.');
        abort_unless((int) $accessToken->amount_cents > 0, 422, 'Deposit amount must be greater than zero.');
        abort_unless($accessToken->repair_order_id === $repairOrder->id, 422, 'Deposit request does not match this repair order.');

        $repairOrder = $repairOrder->fresh();
        $repairOrder->loadMissing('customer');
        $repairOrder->ensureOpenForEditing();

        abort_if($repairOrder->isTerminal(), 422, 'Deposits cannot be collected on closed repair orders.');

        return PaymentGatewayAttempt::query()->create([
            'repair_order_id' => $repairOrder->id,
            'customer_id' => $repairOrder->customer_id,
            'financial_document_id' => null,
            'gateway' => PaymentGateway::Square,
            'capture_surface' => PaymentCaptureSurface::PortalDepositRequest,
            'amount_cents' => (int) $accessToken->amount_cents,
            'currency' => 'USD',
            'idempotency_key' => (string) Str::uuid(),
            'status' => PaymentGatewayAttemptStatus::Pending,
            'initiated_by' => null,
            'customer_access_token_id' => $accessToken->id,
            'initiated_at' => now(),
        ]);
    }
}
