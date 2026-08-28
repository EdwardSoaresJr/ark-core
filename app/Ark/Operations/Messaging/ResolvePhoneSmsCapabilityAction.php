<?php

namespace App\Ark\Operations\Messaging;

use App\Ark\Operations\PhoneNumber;
use App\Ark\Operations\Settings\ShopIntegrationCredentials;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Throwable;

class ResolvePhoneSmsCapabilityAction
{
    public const FRESH_DAYS = 90;

    public function __construct(
        private readonly TwilioPhoneLookupClient $lookup,
        private readonly PhoneSmsCapabilityClassifier $classifier,
        private readonly ShopIntegrationCredentials $credentials,
    ) {}

    public function execute(string $phone, bool $forceRefresh = false): ?PhoneSmsCapability
    {
        $normalized = PhoneNumber::normalize($phone);

        if ($normalized === null || strlen($normalized) < 10) {
            return null;
        }

        $existing = PhoneSmsCapability::findByNormalizedPhone($normalized);

        if (! $forceRefresh && $existing !== null && $this->isFresh($existing)) {
            return $existing;
        }

        if (! $this->credentials->twilioConfigured()) {
            return $existing;
        }

        try {
            $result = $this->lookup->lookupLineType($normalized);
        } catch (Throwable $exception) {
            Log::warning('phone_sms_capability_lookup_failed', [
                'phone' => $normalized,
                'message' => $exception->getMessage(),
            ]);

            return $existing;
        }

        $classified = $this->classifier->classify(
            valid: $result['valid'],
            lineType: $result['line_type'],
            carrierName: $result['carrier_name'],
            validationErrors: $result['validation_errors'],
        );

        return PhoneSmsCapability::query()->updateOrCreate(
            ['normalized_phone' => $normalized],
            [
                'valid' => $result['valid'],
                'line_type' => $result['line_type'],
                'carrier_name' => $result['carrier_name'],
                'sms_capable' => $classified['sms_capable'],
                'reason' => $classified['reason'],
                'checked_at' => now(),
                'raw_payload' => $result['raw'],
            ],
        );
    }

    /**
     * Inbound SMS proves the number can receive/send SMS — no Lookup needed.
     */
    public function markCapableFromInboundSms(string $phone): ?PhoneSmsCapability
    {
        $normalized = PhoneNumber::normalize($phone);

        if ($normalized === null || strlen($normalized) < 10) {
            return null;
        }

        return PhoneSmsCapability::query()->updateOrCreate(
            ['normalized_phone' => $normalized],
            [
                'valid' => true,
                'line_type' => 'mobile',
                'carrier_name' => null,
                'sms_capable' => true,
                'reason' => null,
                'checked_at' => now(),
                'raw_payload' => ['source' => 'inbound_sms'],
            ],
        );
    }

    public function markNotCapableFromDeliveryFailure(
        string $phone,
        ?string $errorCode = null,
        ?string $detail = null,
    ): ?PhoneSmsCapability {
        $normalized = PhoneNumber::normalize($phone);

        if ($normalized === null || strlen($normalized) < 10) {
            return null;
        }

        if (! $this->isLikelyNonSmsDeliveryError($errorCode, $detail)) {
            return PhoneSmsCapability::findByNormalizedPhone($normalized);
        }

        $reason = 'Twilio delivery failed'
            .($errorCode !== null && $errorCode !== '' ? " ({$errorCode})" : '')
            .' — number cannot receive SMS.';

        return PhoneSmsCapability::query()->updateOrCreate(
            ['normalized_phone' => $normalized],
            [
                'valid' => true,
                'line_type' => 'landline',
                'carrier_name' => null,
                'sms_capable' => false,
                'reason' => $reason,
                'checked_at' => now(),
                'raw_payload' => [
                    'source' => 'delivery_failure',
                    'error_code' => $errorCode,
                    'detail' => $detail,
                ],
            ],
        );
    }

    public function assertCapableOrFail(string $phone): PhoneSmsCapability
    {
        $capability = $this->execute($phone);

        if ($capability === null) {
            throw new \RuntimeException('Phone number is invalid.');
        }

        if (! $capability->sms_capable) {
            throw new \RuntimeException($capability->blockReason() ?? 'This number cannot receive SMS.');
        }

        return $capability;
    }

    private function isFresh(PhoneSmsCapability $capability): bool
    {
        $checkedAt = $capability->checked_at;

        if (! $checkedAt instanceof Carbon) {
            return false;
        }

        return $checkedAt->gte(now()->subDays(self::FRESH_DAYS));
    }

    private function isLikelyNonSmsDeliveryError(?string $errorCode, ?string $detail): bool
    {
        $code = trim((string) $errorCode);
        // 21614: not a mobile number; 30006: landline or unreachable
        if (in_array($code, ['21614', '30006', '21612'], true)) {
            return true;
        }

        $haystack = strtolower(trim((string) $detail));

        return str_contains($haystack, 'landline')
            || str_contains($haystack, 'not a mobile')
            || str_contains($haystack, 'cannot be a landline');
    }
}
