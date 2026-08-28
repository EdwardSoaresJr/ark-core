<?php

namespace App\Ark\Operations\Payments;

use App\Ark\Operations\Customers\Customer;
use App\Ark\Operations\Financial\BalanceDueCalculator;
use App\Ark\Operations\PhoneNumber;
use App\Ark\Operations\Portal\PortalVehicleRecordsLink;
use App\Ark\Operations\Settings\ShopSettings;
use Brick\Money\Money;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class PortalInvoicePayShowController
{
    public function __invoke(
        Request $request,
        string $token,
        ResolveCustomerPayTokenAction $resolve,
        SquareConfiguration $square,
        BalanceDueCalculator $balanceDue,
        PortalVehicleRecordsLink $vehicleRecordsLink,
    ): View {
        $accessToken = $resolve->execute($token, touchUsage: false);

        abort_unless($accessToken !== null, 404);

        $repairOrder = $accessToken->repairOrder()
            ->with(['customer', 'vehicle'])
            ->firstOrFail();

        abort_unless($square->portalPayEnabled(), 503);

        /** @var Customer|null $portalCustomer */
        $portalCustomer = Auth::guard('portal')->user();

        $shop = ShopSettings::current();
        $shopPhone = PhoneNumber::display($shop->phone) ?: null;
        $shopPhoneTel = preg_replace('/\D+/', '', (string) $shop->phone) ?: null;
        $vehicleRecords = $vehicleRecordsLink->forVehicle($portalCustomer, $repairOrder->vehicle);

        if ($accessToken->isDepositRequest()) {
            $amountCents = (int) $accessToken->amount_cents;

            abort_unless($amountCents > 0, 410);
            abort_if($repairOrder->isTerminal(), 410);

            $amountDisplay = Money::ofMinor($amountCents, 'USD')->formatTo('en_US');
            $remaining = $repairOrder->balanceDue()->unappliedDepositsCents > 0;

            return view('portal.invoice-pay', [
                'payMode' => $remaining ? 'remaining_deposit' : 'deposit',
                'repairOrder' => $repairOrder,
                'invoice' => null,
                'balanceDue' => $amountDisplay,
                'balanceDueCents' => $amountCents,
                'balanceDueDecimal' => number_format($amountCents / 100, 2, '.', ''),
                'pageTitle' => $remaining ? 'Pay remaining balance' : 'Pay your deposit',
                'amountLabel' => $remaining ? 'Remaining balance' : 'Deposit requested',
                'payButtonLabel' => 'Pay '.$amountDisplay,
                'token' => $token,
                'square' => $square->publicConfig(),
                'vehicleRecordsLink' => $vehicleRecords,
                'shopPhone' => $shopPhone,
                'shopPhoneTel' => $shopPhoneTel,
            ]);
        }

        $balance = $balanceDue->forRepairOrder($repairOrder);
        $invoice = $accessToken->financialDocument;

        abort_unless($balance->hasIssuedInvoice && $invoice !== null, 404);
        abort_unless($balance->balanceDueCents > 0, 410);

        $balanceDisplay = Money::ofMinor($balance->balanceDueCents, 'USD')->formatTo('en_US');

        return view('portal.invoice-pay', [
            'payMode' => 'invoice',
            'repairOrder' => $repairOrder,
            'invoice' => $invoice,
            'balanceDue' => $balanceDisplay,
            'balanceDueCents' => $balance->balanceDueCents,
            'balanceDueDecimal' => number_format($balance->balanceDueCents / 100, 2, '.', ''),
            'pageTitle' => 'Pay your invoice',
            'amountLabel' => 'Balance due',
            'payButtonLabel' => 'Pay '.$balanceDisplay,
            'token' => $token,
            'square' => $square->publicConfig(),
            'vehicleRecordsLink' => $vehicleRecords,
            'shopPhone' => $shopPhone,
            'shopPhoneTel' => $shopPhoneTel,
        ]);
    }
}
