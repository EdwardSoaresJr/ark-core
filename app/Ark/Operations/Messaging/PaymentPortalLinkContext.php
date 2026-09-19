<?php

namespace App\Ark\Operations\Messaging;

use App\Ark\Operations\Documents\EstimateDocument;
use App\Ark\Operations\Financial\BalanceDueCalculator;
use App\Ark\Operations\Financial\FinancialDocumentType;
use App\Ark\Operations\Payments\CardPresentCaptureProjection;
use App\Ark\Operations\Payments\CreateCustomerPayTokenAction;
use App\Ark\Operations\Payments\CustomerDocumentAccessToken;
use App\Ark\Operations\RepairOrders\RepairOrder;
use Brick\Money\Money;
use RuntimeException;

final class PaymentPortalLinkContext
{
    public function __construct(
        private readonly BalanceDueCalculator $balanceDue,
        private readonly CreateCustomerPayTokenAction $payTokens,
        private readonly CardPresentCaptureProjection $capture,
    ) {}

    /**
     * @return array{token: CustomerDocumentAccessToken, url: string, balance_due_display: string, invoice: EstimateDocument}
     */
    public function forRepairOrder(RepairOrder $repairOrder): array
    {
        $repairOrder->loadMissing('customer');

        if (! $this->capture->portalPayEnabled()) {
            throw new RuntimeException('Customer portal payments are not enabled.');
        }

        $balance = $this->balanceDue->forRepairOrder($repairOrder);

        if (! $balance->hasIssuedInvoice) {
            throw new RuntimeException('Generate the final invoice before sending a payment link.');
        }

        if ($balance->balanceDueCents <= 0) {
            throw new RuntimeException('This repair order has no balance due.');
        }

        $invoice = $repairOrder->estimateDocuments()
            ->where('document_type', FinancialDocumentType::Invoice->value)
            ->latest('id')
            ->first();

        if ($invoice === null) {
            throw new RuntimeException('Final invoice could not be found for this repair order.');
        }

        $token = $this->payTokens->execute($repairOrder, $invoice);
        $url = route('portal.invoice-pay.show', ['token' => $token->plainToken]);
        $balanceDueDisplay = Money::ofMinor($balance->balanceDueCents, 'USD')->formatTo('en_US');

        return [
            'token' => $token,
            'url' => $url,
            'balance_due_display' => $balanceDueDisplay,
            'invoice' => $invoice,
        ];
    }
}
