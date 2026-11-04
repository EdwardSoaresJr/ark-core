<?php

namespace App\Ark\Operations\Customers;

use App\Ark\Operations\Messaging\PhoneSmsCapability;
use App\Ark\Operations\PhoneNumber;
use App\Ark\Operations\Settings\ShopIntegrationCredentials;
use App\Ark\Platform\Communications\ManagedCommunicationsGate;
use Illuminate\Support\Carbon;

final class CustomerSmsSendEligibility
{
    public function __construct(
        private readonly Customer $customer,
        private readonly bool $twilioConfigured,
        private readonly ?PhoneSmsCapability $capability = null,
        private readonly ?string $phone = null,
    ) {}

    /**
     * Eligibility to text $phone (the customer's main phone when null). A number's capability is its own; the
     * customer's last delivery result describes the main phone only.
     */
    public static function for(Customer $customer, ShopIntegrationCredentials $credentials, ?string $phone = null): self
    {
        $normalized = PhoneNumber::normalize(filled($phone) ? $phone : $customer->phone);

        return new self(
            $customer,
            $credentials->twilioConfigured(),
            PhoneSmsCapability::findByNormalizedPhone($normalized),
            $normalized,
        );
    }

    public function canSend(): bool
    {
        return $this->blockReason() === null;
    }

    public function blockReason(): ?string
    {
        if (! filled($this->phone ?? $this->customer->phone)) {
            return 'Customer does not have a phone number on file.';
        }

        if (! $this->twilioConfigured && ! ManagedCommunicationsGate::platformSend()) {
            return 'Shop messaging is disabled.';
        }

        if (! $this->consentStatus()->allowsOutboundSms()) {
            return 'Customer has opted out of text messages. Call the customer or use email.';
        }

        if ($this->capability !== null && ! $this->capability->sms_capable) {
            return $this->capability->blockReason();
        }

        return null;
    }

    /** Consent / phone checks only - used when Platform owns transport. */
    public function consentBlockReason(): ?string
    {
        if (! filled($this->customer->phone)) {
            return 'Customer does not have a phone number on file.';
        }

        if (! $this->consentStatus()->allowsOutboundSms()) {
            return 'Customer has opted out of text messages. Call the customer or use email.';
        }

        return null;
    }

    public function deliveryWarning(): ?string
    {
        if (! $this->canSend()) {
            return null;
        }

        if (! $this->isMainPhone()) {
            return null;
        }

        $status = strtolower(trim((string) ($this->customer->last_sms_delivery_status ?? '')));

        if (! in_array($status, ['failed', 'undelivered'], true)) {
            return null;
        }

        $failedAt = $this->customer->last_sms_failed_at;

        if ($failedAt instanceof Carbon && $failedAt->lt(now()->subDays(90))) {
            return null;
        }

        $errorCode = trim((string) ($this->customer->last_sms_error_code ?? ''));

        if ($errorCode !== '') {
            return "Recent text delivery failed (Twilio {$errorCode}). Verify the customer's phone number before texting again.";
        }

        return 'Recent text delivery failed. Verify the customer\'s phone number before texting again.';
    }

    private function isMainPhone(): bool
    {
        return $this->phone === null || $this->phone === PhoneNumber::normalize($this->customer->phone);
    }

    public function optedOut(): bool
    {
        return $this->consentStatus() === CustomerSmsConsentStatus::OptedOut;
    }

    public function capability(): ?PhoneSmsCapability
    {
        return $this->capability;
    }

    private function consentStatus(): CustomerSmsConsentStatus
    {
        $status = $this->customer->sms_consent_status;

        if ($status instanceof CustomerSmsConsentStatus) {
            return $status;
        }

        if (is_string($status) && $status !== '') {
            return CustomerSmsConsentStatus::tryFrom($status) ?? CustomerSmsConsentStatus::Subscribed;
        }

        return CustomerSmsConsentStatus::Subscribed;
    }
}
