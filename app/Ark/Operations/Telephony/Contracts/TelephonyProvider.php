<?php

namespace App\Ark\Operations\Telephony\Contracts;

use App\Ark\Operations\Telephony\IncomingCallPayload;
use App\Ark\Operations\Telephony\TelephonyProviderType;
use Illuminate\Http\Request;

interface TelephonyProvider
{
    public function type(): TelephonyProviderType;

    public function parseIncomingVoiceRequest(Request $request): IncomingCallPayload;

    public function buildIncomingVoiceResponse(IncomingCallPayload $payload): string;
}
