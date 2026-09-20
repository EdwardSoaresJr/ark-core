<?php

namespace App\Ark\Mobile\Http;

use App\Ark\Mobile\MobileCustomerWorkspaceProjection;
use App\Ark\Mobile\MobileStaffAccess;
use App\Ark\Operations\Customers\Customer;
use App\Ark\Operations\Customers\CustomerSearchQuery;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class MobileCustomerWorkspaceController
{
    public function __invoke(
        Request $request,
        Customer $customer,
        MobileStaffAccess $access,
        MobileCustomerWorkspaceProjection $projection,
    ): JsonResponse {
        abort_unless($access->canViewCustomer($request->user()), 403);

        $customer = Customer::query()
            ->whereKey($customer->id)
            ->tap(fn ($query) => CustomerSearchQuery::withOperationalContext($query))
            ->firstOrFail();

        $data = $request->validate([
            'observation' => ['nullable', 'string', 'max:48'],
            'moment' => ['nullable', 'string', 'max:48'],
            'intent' => ['nullable', 'string', 'max:48'],
        ]);

        $requestedObservation = filled($data['observation'] ?? null)
            ? (string) $data['observation']
            : (filled($data['moment'] ?? null)
                ? (string) $data['moment']
                : (filled($data['intent'] ?? null) ? (string) $data['intent'] : null));

        return response()->json($projection->forCustomer(
            $customer,
            $request->user(),
            requestedObservation: $requestedObservation,
        ));
    }
}
