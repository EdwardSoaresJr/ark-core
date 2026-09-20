<?php

namespace App\Ark\Operations\Messaging;

/**
 * Maps Twilio Lookup v2 line_type_intelligence to SMS deliverability.
 *
 * @see https://www.twilio.com/docs/lookup/v2-api/line-type-intelligence
 */
final class PhoneSmsCapabilityClassifier
{
    /**
     * Line types that typically cannot receive SMS.
     *
     * @var list<string>
     */
    private const NOT_CAPABLE = [
        'landline',
        'payphone',
        'pager',
        'voicemail',
        'premium',
        'sharedCost',
        'uan',
    ];

    /**
     * @param  list<string>|null  $validationErrors
     * @return array{sms_capable: bool, reason: ?string}
     */
    public function classify(
        bool $valid,
        ?string $lineType,
        ?string $carrierName = null,
        ?array $validationErrors = null,
    ): array {
        if (! $valid) {
            $detail = is_array($validationErrors) && $validationErrors !== []
                ? implode(', ', $validationErrors)
                : 'invalid number';

            return [
                'sms_capable' => false,
                'reason' => 'Number is not valid for SMS ('.$detail.').',
            ];
        }

        $type = trim((string) $lineType);

        if ($type === '') {
            return [
                'sms_capable' => true,
                'reason' => null,
            ];
        }

        if (in_array($type, self::NOT_CAPABLE, true)) {
            $label = $this->lineTypeLabel($type);
            $carrier = trim((string) ($carrierName ?? ''));

            return [
                'sms_capable' => false,
                'reason' => $carrier !== ''
                    ? "{$label} ({$carrier}) — cannot receive SMS."
                    : "{$label} — cannot receive SMS.",
            ];
        }

        return [
            'sms_capable' => true,
            'reason' => null,
        ];
    }

    public function lineTypeLabel(string $lineType): string
    {
        return match ($lineType) {
            'landline' => 'Landline',
            'mobile' => 'Mobile',
            'fixedVoip' => 'Fixed VoIP',
            'nonFixedVoip' => 'VoIP',
            'tollFree' => 'Toll-free',
            'premium' => 'Premium',
            'personal' => 'Personal',
            'payphone' => 'Payphone',
            'pager' => 'Pager',
            'voicemail' => 'Voicemail',
            'sharedCost' => 'Shared-cost',
            'uan' => 'UAN',
            'unknown' => 'Unknown line type',
            default => ucfirst($lineType),
        };
    }
}
