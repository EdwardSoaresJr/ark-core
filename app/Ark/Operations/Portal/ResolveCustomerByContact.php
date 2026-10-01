<?php

namespace App\Ark\Operations\Portal;

use App\Ark\Operations\Customers\Customer;
use App\Ark\Operations\PhoneNumber;

final class ResolveCustomerByContact
{
    /**
     * @return array{customer: Customer, channel: PortalAccessChannel, destination: string}|null
     */
    public function resolve(string $contactInput): ?array
    {
        $contactInput = trim($contactInput);

        if ($contactInput === '') {
            return null;
        }

        if (str_contains($contactInput, '@')) {
            return $this->resolveEmail($contactInput);
        }

        return $this->resolvePhone($contactInput);
    }

    /**
     * @return array{customer: Customer, channel: PortalAccessChannel, destination: string}|null
     */
    private function resolveEmail(string $email): ?array
    {
        $email = strtolower(trim($email));

        if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return null;
        }

        $customer = Customer::query()
            ->whereRaw('LOWER(email) = ?', [$email])
            ->first();

        if ($customer === null) {
            return null;
        }

        return [
            'customer' => $customer,
            'channel' => PortalAccessChannel::Email,
            'destination' => $email,
        ];
    }

    /**
     * @return array{customer: Customer, channel: PortalAccessChannel, destination: string}|null
     */
    private function resolvePhone(string $phoneInput): ?array
    {
        $phone = PhoneNumber::normalize($phoneInput);

        if ($phone === null || strlen($phone) !== 10) {
            return null;
        }

        $customer = Customer::query()
            ->where('phone', $phone)
            ->first();

        if ($customer === null) {
            return null;
        }

        return [
            'customer' => $customer,
            'channel' => PortalAccessChannel::Sms,
            'destination' => $phone,
        ];
    }
}
