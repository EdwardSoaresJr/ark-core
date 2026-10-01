<?php

namespace App\Ark\Platform\Communications;

use App\Ark\Operations\Customers\Customer;
use App\Ark\Operations\PhoneNumber;

/**
 * Platform conversations often omit core_customer_id. Match the SMS number to a customer.
 */
final class PlatformConversationCustomerResolver
{
    /**
     * @param  list<string>  $contactAddresses
     * @return array<string, Customer>
     */
    public function mapByNormalizedPhone(array $contactAddresses): array
    {
        $phones = [];

        foreach ($contactAddresses as $address) {
            $normalized = PhoneNumber::normalize((string) $address);

            if ($normalized !== null) {
                $phones[$normalized] = true;
            }
        }

        if ($phones === []) {
            return [];
        }

        $map = [];

        Customer::query()
            ->whereIn('phone', array_keys($phones))
            ->orderBy('id')
            ->get()
            ->each(function (Customer $customer) use (&$map): void {
                $phone = PhoneNumber::normalize((string) $customer->phone);

                if ($phone !== null && ! isset($map[$phone])) {
                    $map[$phone] = $customer;
                }
            });

        return $map;
    }

    /**
     * @param  array<string, Customer>  $phoneMap
     */
    public function resolve(?int $coreCustomerId, string $contactAddress, array $phoneMap): ?Customer
    {
        if ($coreCustomerId !== null && $coreCustomerId > 0) {
            $customer = Customer::query()->find($coreCustomerId);

            if ($customer instanceof Customer) {
                return $customer;
            }
        }

        $phone = PhoneNumber::normalize($contactAddress);

        return $phone !== null ? ($phoneMap[$phone] ?? null) : null;
    }

    public function resolveOne(?int $coreCustomerId, string $contactAddress): ?Customer
    {
        return $this->resolve(
            $coreCustomerId,
            $contactAddress,
            $this->mapByNormalizedPhone([$contactAddress]),
        );
    }
}
