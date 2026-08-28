<?php

namespace App\Ark\Operations\Messaging;

use App\Ark\Operations\Conversations\CustomerCallContextResolver;
use App\Ark\Operations\Customers\Customer;
use App\Ark\Operations\PhoneNumber;

class ProcessTwilioMessageDeliveryStatusAction
{
    public function __construct(
        private readonly CustomerCallContextResolver $callContextResolver,
        private readonly RecordCustomerSmsDeliveryStatusAction $deliveryStatus,
    ) {}

    /**
     * @param  array<string, mixed>  $payload
     */
    public function execute(array $payload): void
    {
        $messageSid = trim((string) ($payload['MessageSid'] ?? ''));

        if ($messageSid === '') {
            return;
        }

        $status = strtolower(trim((string) ($payload['MessageStatus'] ?? '')));

        if ($status === '') {
            return;
        }

        $customer = $this->resolveCustomer($payload);

        if (! $customer instanceof Customer) {
            return;
        }

        $this->deliveryStatus->execute(
            $customer,
            $messageSid,
            $status,
            trim((string) ($payload['ErrorCode'] ?? '')),
        );
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function resolveCustomer(array $payload): ?Customer
    {
        $to = PhoneNumber::normalize((string) ($payload['To'] ?? ''));

        if ($to === null) {
            return null;
        }

        return $this->callContextResolver->resolve($to)?->customer;
    }
}
