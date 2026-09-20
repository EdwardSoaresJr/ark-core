<?php

namespace App\Ark\Operations\Intake;

use App\Ark\Operations\Customers\Customer;
use Illuminate\Http\JsonResponse;

class AdvisorIntakeCustomerShowController
{
    public function __invoke(Customer $customer): JsonResponse
    {
        return response()->json([
            'id' => $customer->id,
            'name' => $customer->name,
            'first_name' => $customer->first_name,
            'last_name' => $customer->last_name,
            'phone' => $customer->phone,
            'email' => $customer->email,
            'contact_preference' => $customer->contact_preference?->value,
            'address_line_1' => $customer->address_line_1,
            'address_line_2' => $customer->address_line_2,
            'city' => $customer->city,
            'state' => $customer->state,
            'postal_code' => $customer->postal_code,
            'referral_source' => $customer->referral_source,
            'customer_type' => $customer->customer_type ?: 'Retail',
        ]);
    }
}
