<?php

namespace App\Ark\Operations\Intake;

use App\Ark\Operations\Leads\Lead;
use App\Ark\Operations\PhoneNumber;

final class IntakeEntryQuery
{
    /**
     * @param  array{
     *     concern?: string|null,
     *     callback_name?: string|null,
     *     callback_phone?: string|null,
     * }  $data
     * @return array<string, int|string>
     */
    public static function fromWebsiteLead(array $data): array
    {
        $params = [];

        $phone = PhoneNumber::normalize($data['callback_phone'] ?? null);
        if ($phone !== null) {
            $params['phone'] = $phone;
        }

        $concern = trim((string) ($data['concern'] ?? ''));
        if ($concern !== '') {
            $params['concern'] = $concern;
        }

        $name = trim((string) ($data['callback_name'] ?? ''));
        if ($name !== '' && ! isset($params['customer_id'])) {
            $params['q'] = $name;
        }

        return $params;
    }

    /**
     * @return array<string, int|string>
     */
    public static function fromLead(Lead $lead): array
    {
        $params = self::fromWebsiteLead([
            'concern' => $lead->concern,
            'callback_name' => $lead->contact_name,
            'callback_phone' => $lead->contact_phone,
        ]);

        $params['lead_id'] = $lead->id;

        return $params;
    }

    /**
     * @return array<string, string>
     */
    public static function fromInboundPhoneMessage(string $phone, string $body): array
    {
        $params = [];

        $normalizedPhone = PhoneNumber::normalize($phone);
        if ($normalizedPhone !== null) {
            $params['phone'] = $normalizedPhone;
        }

        $concern = trim($body);
        if ($concern !== '') {
            $params['concern'] = mb_substr($concern, 0, 5000);
        }

        return $params;
    }

    /**
     * @return list<array{customer_states: string, recommendation_intent: string, billing_posture: string}>
     */
    public static function initialConcernRowsFromRequest(string $concern): array
    {
        $concern = trim($concern);

        if ($concern === '') {
            return [];
        }

        return [[
            'customer_states' => $concern,
            'recommendation_intent' => '',
            'billing_posture' => '',
        ]];
    }
}
