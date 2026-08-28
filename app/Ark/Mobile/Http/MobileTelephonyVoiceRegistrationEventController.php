<?php

namespace App\Ark\Mobile\Http;

use App\Ark\Mobile\MobileDevice;
use App\Ark\Mobile\MobileStaffAccess;
use App\Ark\Operations\Telephony\MobileVoice\MobileVoiceEndpointRegistrar;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Client-reported mobile voice registration posture.
 *
 * Observation for all phases; persists Twilio Client voice readiness only for
 * explicit voice_ready / cleared phases — never for generic device registration.
 */
final class MobileTelephonyVoiceRegistrationEventController
{
    public function __invoke(
        Request $request,
        MobileStaffAccess $access,
        MobileVoiceEndpointRegistrar $voiceEndpoints,
    ): JsonResponse {
        abort_unless($access->canAccessShopCommunications($request->user()), 403);

        $validated = $request->validate([
            'phase' => ['required', 'string', 'max:64'],
            'message' => ['nullable', 'string', 'max:2000'],
            'device_name' => ['nullable', 'string', 'max:255'],
            'category' => ['nullable', 'string', 'max:32'],
            'event' => ['nullable', 'string', 'max:64'],
            'client_ts' => ['nullable', 'string', 'max:64'],
            'extension' => ['nullable', 'string', 'max:16'],
            'detail' => ['nullable', 'string', 'max:2000'],
            'caller' => ['nullable', 'string', 'max:2000'],
            'build_mode' => ['nullable', 'string', 'max:16'],
        ]);

        $phase = (string) $validated['phase'];
        $channel = $phase === 'lifecycle'
            ? 'mobile.voice.lifecycle'
            : 'mobile.voice.registration';

        Log::info($channel, [
            'server_ts' => now()->toIso8601String(),
            'user_id' => $request->user()?->id,
            'phase' => $phase,
            'message' => $validated['message'] ?? '',
            'device_name' => $validated['device_name'] ?? '',
            'category' => $validated['category'] ?? '',
            'event' => $validated['event'] ?? '',
            'client_ts' => $validated['client_ts'] ?? '',
            'extension' => $validated['extension'] ?? '',
            'detail' => $validated['detail'] ?? '',
            'caller' => $validated['caller'] ?? '',
            'build_mode' => $validated['build_mode'] ?? '',
        ]);

        $user = $request->user();
        $deviceName = trim((string) ($validated['device_name'] ?? ''));

        if ($user !== null && $deviceName !== '') {
            $device = MobileDevice::query()
                ->where('user_id', $user->id)
                ->where('device_name', $deviceName)
                ->first();

            if ($device instanceof MobileDevice) {
                if ($phase === 'voice_ready') {
                    $voiceEndpoints->markVoiceReady($device);
                } elseif (in_array($phase, ['unregistered', 'failed', 'not_connected'], true)) {
                    $voiceEndpoints->clearVoiceReady($device);
                }
            }
        } elseif ($user !== null && in_array($phase, ['unregistered', 'failed', 'not_connected'], true)) {
            $voiceEndpoints->clearVoiceReadyForUser($user);
        }

        return response()->json(['ok' => true]);
    }
}
