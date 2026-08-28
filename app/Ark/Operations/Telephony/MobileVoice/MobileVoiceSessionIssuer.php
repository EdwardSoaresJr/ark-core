<?php

namespace App\Ark\Operations\Telephony\MobileVoice;

use App\Ark\Mobile\MobileDevice;
use App\Ark\Operations\Customers\Customer;
use App\Ark\Operations\PhoneNumber;
use App\Models\User;
use RuntimeException;

final class MobileVoiceSessionIssuer
{
    public function __construct(
        private readonly MobileVoiceTransportManager $transports,
        private readonly MobileVoiceEndpointRegistrar $endpointRegistrar,
        private readonly MobileVoiceConnectStore $connectStore,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function sessionFor(User $user, MobileDevice $device): array
    {
        $this->endpointRegistrar->ensureForDevice($device);

        return $this->transports->issueSession($user, $device);
    }

    /**
     * @return array<string, mixed>
     */
    public function connect(
        User $user,
        MobileDevice $device,
        ?int $customerId,
        ?string $phone,
        ?int $repairOrderId = null,
    ): array {
        if (! $this->transports->isInAppReady($user, $device)) {
            throw new RuntimeException($this->transports->readinessBlockReason($user, $device) ?? 'In-app voice is not ready.');
        }

        $customerE164 = $this->resolveCustomerE164($customerId, $phone);

        if ($customerE164 === null) {
            throw new RuntimeException('A customer phone number is required for in-app calling.');
        }

        $endpoint = $this->endpointRegistrar->ensureForDevice($device);

        $intent = new MobileVoiceConnectIntent(
            initiatedByUserId: $user->id,
            mobileDeviceId: $device->id,
            endpointId: $endpoint->id,
            customerE164: $customerE164,
            normalizedCustomerPhone: PhoneNumber::digits($customerE164) ?? $customerE164,
            customerId: $customerId,
            repairOrderId: $repairOrderId,
        );

        $connectToken = $this->connectStore->issue($intent);

        return $this->transports->issueConnect($user, $device, $intent, $connectToken);
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
