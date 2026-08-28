<?php

namespace App\Ark\Operations\Messaging;

use App\Ark\Operations\Conversations\CustomerCallContextResolver;
use App\Ark\Operations\Customers\Customer;
use App\Ark\Operations\Customers\CustomerSmsConsentStatus;
use App\Ark\Operations\PhoneNumber;

final class ProcessInboundSmsConsentAction
{
    public function __construct(
        private readonly CustomerCallContextResolver $callContextResolver,
        private readonly RecordCustomerSmsConsentAction $consentRecorder,
    ) {}

    /**
     * @return array{handled: bool, confirmation: ?string}
     */
    public function execute(string $fromPhone, string $body, ?string $providerMessageSid = null): array
    {
        $optOutKeyword = SmsConsentKeyword::matchOptOut($body);

        if ($optOutKeyword !== null) {
            return $this->handleOptOut($fromPhone, $providerMessageSid);
        }

        $optInKeyword = SmsConsentKeyword::matchOptIn($body);

        if ($optInKeyword === null) {
            return ['handled' => false, 'confirmation' => null];
        }

        return $this->handleOptIn($fromPhone, $providerMessageSid);
    }

    /**
     * @return array{handled: bool, confirmation: ?string}
     */
    private function handleOptOut(string $fromPhone, ?string $providerMessageSid): array
    {
        $customer = $this->resolveCustomer($fromPhone);

        if ($customer instanceof Customer) {
            $this->consentRecorder->optOut($customer, $providerMessageSid);
        }

        return [
            'handled' => true,
            'confirmation' => $this->consentRecorder->confirmationMessage(CustomerSmsConsentStatus::OptedOut),
        ];
    }

    /**
     * @return array{handled: bool, confirmation: ?string}
     */
    private function handleOptIn(string $fromPhone, ?string $providerMessageSid): array
    {
        $customer = $this->resolveCustomer($fromPhone);

        if (! $customer instanceof Customer) {
            return ['handled' => false, 'confirmation' => null];
        }

        if ($customer->sms_consent_status === CustomerSmsConsentStatus::OptedOut) {
            $this->consentRecorder->optIn($customer, $providerMessageSid);

            return [
                'handled' => true,
                'confirmation' => $this->consentRecorder->confirmationMessage(CustomerSmsConsentStatus::Subscribed),
            ];
        }

        return ['handled' => false, 'confirmation' => null];
    }

    private function resolveCustomer(string $fromPhone): ?Customer
    {
        $normalized = PhoneNumber::normalize($fromPhone);

        if ($normalized === null) {
            return null;
        }

        return $this->callContextResolver->resolve($normalized)?->customer;
    }
}
