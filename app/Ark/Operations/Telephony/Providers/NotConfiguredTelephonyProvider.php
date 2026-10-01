<?php

namespace App\Ark\Operations\Telephony\Providers;

use App\Ark\Operations\Telephony\Contracts\TelephonyProvider;
use App\Ark\Operations\Telephony\IncomingCallPayload;
use App\Ark\Operations\Telephony\TelephonyProviderType;
use Illuminate\Http\Request;
use RuntimeException;

final class NotConfiguredTelephonyProvider implements TelephonyProvider
{
    public function type(): TelephonyProviderType
    {
        return TelephonyProviderType::None;
    }

    public function parseIncomingVoiceRequest(Request $request): IncomingCallPayload
    {
        throw new RuntimeException('Voice telephony is not configured.');
    }

    public function buildIncomingVoiceResponse(IncomingCallPayload $payload): string
    {
        throw new RuntimeException('Voice telephony is not configured.');
    }
}
