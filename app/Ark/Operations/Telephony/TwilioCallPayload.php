<?php

namespace App\Ark\Operations\Telephony;

use App\Ark\Operations\PhoneNumber;
use Illuminate\Http\Request;

final class TwilioCallPayload
{
    public static function fromRequest(Request $request, ?CallSessionDirection $direction = null): IncomingCallPayload
    {
        $from = (string) $request->input('From', '');
        $to = (string) $request->input('To', '');
        $rawDirection = strtolower((string) $request->input('Direction', 'inbound'));

        return new IncomingCallPayload(
            provider: TelephonyProviderType::Twilio,
            providerCallSid: trim((string) $request->input('CallSid', '')),
            fromNumber: $from,
            toNumber: $to,
            normalizedFrom: PhoneNumber::normalize($from) ?? '',
            normalizedTo: PhoneNumber::normalize($to),
            status: CallSessionStatus::fromTwilioStatus($request->input('CallStatus')),
            rawPayload: $request->all(),
            direction: $direction ?? (str_starts_with($rawDirection, 'outbound')
                ? CallSessionDirection::Outbound
                : CallSessionDirection::Inbound),
        );
    }
}
