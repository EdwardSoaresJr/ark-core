<?php

namespace App\Ark\Operations\Conversations;

use App\Ark\Operations\Customers\Customer;

class MessengerCustomerResolver
{
    public function forPsid(string $psid): ?Customer
    {
        $normalized = trim($psid);

        if ($normalized === '') {
            return null;
        }

        return Customer::query()
            ->where('messenger_psid', $normalized)
            ->first();
    }
}
