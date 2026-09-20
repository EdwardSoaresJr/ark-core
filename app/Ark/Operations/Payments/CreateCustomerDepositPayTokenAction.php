<?php

namespace App\Ark\Operations\Payments;

use App\Ark\Operations\RepairOrders\RepairOrder;
use Illuminate\Support\Str;

final class CreateCustomerDepositPayTokenAction
{
    public function execute(
        RepairOrder $repairOrder,
        int $amountCents,
        int $expiresInDays = 30,
    ): CustomerPayTokenResult {
        abort_if($amountCents <= 0, 422, 'Deposit amount must be greater than zero.');

        $plainToken = Str::random(64);

        $token = CustomerDocumentAccessToken::query()->create([
            'repair_order_id' => $repairOrder->id,
            'financial_document_id' => null,
            'token_hash' => hash('sha256', $plainToken),
            'scope' => CustomerDocumentAccessToken::SCOPE_PAY_DEPOSIT,
            'amount_cents' => $amountCents,
            'expires_at' => now()->addDays($expiresInDays),
        ]);

        return new CustomerPayTokenResult($token, $plainToken);
    }
}
