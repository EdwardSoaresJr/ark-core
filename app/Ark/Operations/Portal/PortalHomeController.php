<?php

namespace App\Ark\Operations\Portal;

use App\Ark\Operations\Customers\Customer;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;

final class PortalHomeController
{
    public function __construct(
        private readonly PortalHomeProjection $home,
    ) {}

    public function __invoke(): View
    {
        /** @var Customer $customer */
        $customer = Auth::guard('portal')->user();

        return view('portal.home', [
            'customer' => $customer,
            'home' => $this->home->forCustomer($customer),
        ]);
    }
}
