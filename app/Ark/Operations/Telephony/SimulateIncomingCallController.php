<?php

namespace App\Ark\Operations\Telephony;

use App\Ark\Operations\Settings\ShopSettings;
use App\Ark\Operations\Telephony\Providers\NotConfiguredTelephonyProvider;
use App\Ark\Runtime\Authorization\ArkCapability;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SimulateIncomingCallController
{
    public function __invoke(
        Request $request,
        TelephonyProviderManager $providers,
        ProcessIncomingCallAction $process,
    ): JsonResponse {
        abort_unless(
            app()->environment('local', 'testing')
                || $request->user()?->can(ArkCapability::SettingsManage->value),
            404,
        );

        $phone = trim((string) ($request->json('phone') ?? $request->input('phone', '')));

        abort_if($phone === '', 422, 'Phone number is required.');

        $provider = $providers->current();

        if ($provider instanceof NotConfiguredTelephonyProvider) {
            return response()->json([
                'message' => 'Voice telephony is not configured.',
            ], 503);
        }

        $simulated = Request::create('/', 'POST', [
            'CallSid' => 'sim-'.uniqid(),
            'From' => $phone,
            'To' => ShopSettings::current()->telephony_inbound_number ?? '+15550000000',
            'CallStatus' => 'ringing',
            'Direction' => 'inbound',
        ]);

        $payload = $provider->parseIncomingVoiceRequest($simulated);
        $result = $process->execute($payload);

        return response()->json([
            'created' => $result['created'],
            'call_session_id' => $result['session']->id,
            'customer_id' => $result['context']?->customer?->id,
            'matched' => $result['context']?->hasMatch() ?? false,
            'open_repair_order_count' => $result['context']?->openRepairOrders->count() ?? 0,
        ]);
    }
}
