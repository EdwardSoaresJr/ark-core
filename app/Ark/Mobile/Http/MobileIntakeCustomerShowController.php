<?php

namespace App\Ark\Mobile\Http;

use App\Ark\Mobile\MobileIntakeCustomerProjection;
use App\Ark\Mobile\MobileStaffAccess;
use App\Ark\Operations\Customers\Customer;
use App\Ark\Operations\Customers\CustomerSearchQuery;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class MobileIntakeCustomerShowController
{
    public function __invoke(
        Request $request,
        Customer $customer,
        MobileStaffAccess $access,
        MobileIntakeCustomerProjection $projection,
    ): JsonResponse {
        abort_unless($access->canViewCustomer($request->user()), 403);

        $customer = Customer::query()
            ->whereKey($customer->id)
            ->tap(fn ($query) => CustomerSearchQuery::withOperationalContext($query))
            ->firstOrFail();

        return response()->json($projection->detail($customer));
    }
}
