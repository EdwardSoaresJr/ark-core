<?php

namespace App\Ark\Operations\Portal;

use App\Ark\Operations\Financial\BalanceDueCalculator;
use App\Ark\Operations\Financial\FinancialPositionProjection;
use App\Ark\Operations\Payments\CardPresentCaptureProjection;
use App\Ark\Operations\Payments\CreateCustomerPayTokenAction;
use App\Ark\Operations\RepairOrders\RepairOrder;
use Illuminate\Contracts\View\View;

class RepairOrderPortalPaymentPreviewController
{
    public function __invoke(
        RepairOrder $repairOrder,
        BalanceDueCalculator $balanceDue,
        CardPresentCaptureProjection $capture,
        CreateCustomerPayTokenAction $payTokens,
        PortalVehicleRecordsLink $vehicleRecordsLink,
    ): View {
        abort_unless($capture->portalPayEnabled(), 503);

        $repairOrder->loadMissing(['customer', 'vehicle']);

        $position = FinancialPositionProjection::for($repairOrder);
        $invoice = $balanceDue->issuedInvoice($repairOrder);

        abort_unless($position->hasIssuedFinalInvoice() && $invoice !== null, 404);
        abort_unless($position->customerOwesTodayCents > 0, 404);

        $token = $payTokens->execute($repairOrder, $invoice, forStaffPreview: true);

        return view('portal.invoice-pay', [
            'payMode' => 'invoice',
            'repairOrder' => $repairOrder,
            'invoice' => $invoice,
            'balanceDue' => $position->projectedBalanceLabel(),
            'balanceDueCents' => $position->customerOwesTodayCents,
            'balanceDueDecimal' => number_format($position->customerOwesTodayCents / 100, 2, '.', ''),
            'pageTitle' => 'Pay your invoice',
            'amountLabel' => 'Balance due',
            'payButtonLabel' => 'Pay '.$position->projectedBalanceLabel(),
            'token' => $token->plainToken,
            'square' => $capture->publicConfig(),
            'vehicleRecordsLink' => $vehicleRecordsLink->forVehicle(null, $repairOrder->vehicle),
            'staffPreview' => true,
        ]);
    }
}
