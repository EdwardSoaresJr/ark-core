<?php

namespace App\Ark\Operations\Telephony;

use App\Ark\Operations\Settings\ShopIntegrationCredentials;

class TelephonyCallbackPresenter
{
    public function __construct(
        private readonly ShopIntegrationCredentials $credentials,
    ) {}

    /**
     * @param  array<string, mixed>  $context
     * @return array{show_callback_action: bool, callback_customer_id: ?int, callback_phone: ?string}
     */
    public function forCallContext(array $context, ?CallSession $session = null): array
    {
        $phone = filled($context['normalized_from'] ?? null)
            ? (string) $context['normalized_from']
            : null;

        if ($phone === null && filled($context['display_phone'] ?? null)) {
            $phone = (string) $context['display_phone'];
        }

        $live = $session !== null
            && in_array($session->status, [CallSessionStatus::Ringing, CallSessionStatus::Answered], true);

        return [
            'show_callback_action' => $this->credentials->twilioConfigured()
                && filled($phone)
                && ! $live,
            'callback_customer_id' => isset($context['customer_id']) ? (int) $context['customer_id'] : null,
            'callback_phone' => $phone,
        ];
    }

    /**
     * @return array{show_callback_action: bool, callback_customer_id: int, callback_phone: ?string}
     */
    public function forCustomer(int $customerId, ?string $phone): array
    {
        return [
            'show_callback_action' => $this->credentials->twilioConfigured() && filled($phone),
            'callback_customer_id' => $customerId,
            'callback_phone' => $phone,
        ];
    }
}
