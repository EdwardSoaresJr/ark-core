<?php

namespace App\Ark\Operations\Payments;

use App\Ark\Operations\Documents\EstimateDocument;
use App\Ark\Operations\RepairOrders\RepairOrder;
use Illuminate\Support\Str;

final class CreateCustomerPayTokenAction
{
    public function execute(
        RepairOrder $repairOrder,
        EstimateDocument $invoice,
        int $expiresInDays = 30,
        bool $forStaffPreview = false,
    ): CustomerPayTokenResult {
        $plainToken = Str::random(64);

        $token = CustomerDocumentAccessToken::query()->create([
            'repair_order_id' => $repairOrder->id,
            'financial_document_id' => $invoice->id,
            'token_hash' => hash('sha256', $plainToken),
            'scope' => CustomerDocumentAccessToken::SCOPE_PAY_INVOICE,
            'amount_cents' => null,
            'expires_at' => $forStaffPreview
                ? now()->addHour()
                : now()->addDays($expiresInDays),
        ]);

        return new CustomerPayTokenResult($token, $plainToken);
    }
}
