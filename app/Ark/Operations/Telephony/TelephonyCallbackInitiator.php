<?php

namespace App\Ark\Operations\Telephony;

use App\Ark\Operations\Customers\Customer;
use App\Ark\Operations\PhoneNumber;
use App\Ark\Operations\Settings\ShopIntegrationCredentials;
use App\Models\User;
use RuntimeException;

class TelephonyCallbackInitiator
{
    public function __construct(
        private readonly TelephonyEndpointMatcher $endpointMatcher,
        private readonly TelephonyOutboundCallerId $callerId,
        private readonly OutboundVoiceCallControl $twilio,
        private readonly TelephonyCallbackStore $callbackStore,
        private readonly ShopIntegrationCredentials $credentials,
    ) {}

    /**
     * @return array{initiated: bool, message: string}
     */
    public function initiate(
        User $user,
        ?int $customerId = null,
        ?string $phone = null,
        ?int $repairOrderId = null,
        bool $mobileChannel = false,
    ): array {
        if (! $this->credentials->twilioConfigured() || ! $this->twilio->configured()) {
            throw new RuntimeException('Twilio is not configured for callbacks.');
        }

        $advisorDestination = $mobileChannel
            ? $this->endpointMatcher->mobileCallbackDestinationFor($user)
            : $this->endpointMatcher->callbackDestinationFor($user);

        if ($advisorDestination === null) {
            throw new RuntimeException($mobileChannel
                ? 'Add a cell phone on your staff profile before placing callbacks from ARK Mobile.'
                : 'Add a cell phone on your staff profile or a telephony endpoint before placing callbacks.');
        }

        $customerE164 = $this->resolveCustomerE164($customerId, $phone);

        if ($customerE164 === null) {
            throw new RuntimeException('A customer phone number is required for callback.');
        }

        $shopCallerId = $this->callerId->resolve();

        if ($shopCallerId === null) {
            throw new RuntimeException('Save your shop Twilio number in Settings before placing callbacks.');
        }

        $token = $this->callbackStore->issue(new TelephonyCallbackIntent(
            initiatedByUserId: $user->id,
            endpointId: $mobileChannel
                ? $this->endpointMatcher->mobileCallbackEndpointIdFor($user)
                : $this->endpointMatcher->callbackEndpointIdFor($user),
            customerE164: $customerE164,
            normalizedCustomerPhone: PhoneNumber::digits($customerE164) ?? $customerE164,
            customerId: $customerId,
            repairOrderId: $repairOrderId,
        ));

        $answerUrl = '';
        $statusCallbackUrl = '';

        $callSid = $this->twilio->createOutboundCall(
            $shopCallerId,
            $advisorDestination,
            $answerUrl,
            $statusCallbackUrl,
            timeout: TelephonyCallFlowSettings::fromShopSettings()->dialTimeoutSeconds(),
        );

        if ($callSid === null) {
            throw new RuntimeException('Callback could not be started. Try again in a moment.');
        }

        return [
            'initiated' => true,
            'message' => 'Your phone is ringing. Answer to reach the customer.',
        ];
    }

    private function resolveCustomerE164(?int $customerId, ?string $phone): ?string
    {
        if ($customerId !== null) {
            $customer = Customer::query()->find($customerId);
            $resolved = PhoneNumber::toE164($customer?->phone);

            if ($resolved !== null) {
                return $resolved;
            }
        }

        return PhoneNumber::toE164($phone);
    }
}
