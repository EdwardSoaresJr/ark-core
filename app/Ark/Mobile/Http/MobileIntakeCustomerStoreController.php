<?php

namespace App\Ark\Mobile\Http;

use App\Ark\Mobile\MobileIntakeCustomerProjection;
use App\Ark\Mobile\MobileStaffAccess;
use App\Ark\Operations\Customers\Customer;
use App\Ark\Operations\Settings\ShopSettings;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

final class MobileIntakeCustomerStoreController
{
    public function __invoke(
        Request $request,
        MobileStaffAccess $access,
        MobileIntakeCustomerProjection $projection,
    ): JsonResponse {
        abort_unless($access->canManageCustomers($request->user()), 403);

        $customerTypes = collect(ShopSettings::current()->customerTypeRows())
            ->pluck('name')
            ->all();

        $data = $request->validate([
            'first_name' => ['required', 'string', 'max:255'],
            'last_name' => ['required', 'string', 'max:255'],
            'phone' => ['required', 'string', 'max:32'],
            'email' => ['nullable', 'email', 'max:255'],
            'customer_type' => ['nullable', Rule::in($customerTypes)],
        ]);

        $data['customer_type'] ??= $customerTypes[0] ?? 'Retail';

        $customer = Customer::query()->create($data);

        return response()->json($projection->created($customer), 201);
    }
}
