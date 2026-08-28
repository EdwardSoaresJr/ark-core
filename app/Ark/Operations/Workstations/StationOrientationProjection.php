<?php

namespace App\Ark\Operations\Workstations;

use App\Ark\Operations\Communications\CommunicationDevice;
use App\Ark\Operations\Communications\CommunicationDeviceStatus;
use App\Ark\Operations\Telephony\CallSession;
use App\Ark\Operations\Telephony\CallSessionStatus;
use App\Ark\Operations\Telephony\TelephonyExtension;
use App\Models\User;

/**
 * Station orientation — situation at a place, not device inventory.
 *
 * Surfaces: VVX microbrowser, Shop Walk, portable station when bound to a station.
 */
final class StationOrientationProjection
{
    public function __construct(
        private readonly WorkstationOperatorResolver $operatorResolver,
    ) {}

    /**
     * @return array<string, mixed>|null
     */
    public function forDevice(CommunicationDevice $device): ?array
    {
        $device->loadMissing('workstation.currentOperator', 'workstation.devices', 'telephonyExtension');

        $workstation = $device->workstation;

        if ($workstation === null) {
            return null;
        }

        return $this->forWorkstation($workstation, $device);
    }

    /**
     * @return array<string, mixed>
     */
    public function forWorkstation(Workstation $workstation, ?CommunicationDevice $contextDevice = null): array
    {
        $workstation->loadMissing('currentOperator', 'devices');

        $primaryDevice = $contextDevice ?? $workstation->devices->sortBy('name')->first();
        $operator = $workstation->currentOperator instanceof User && $workstation->currentOperator->isActive()
            ? $workstation->currentOperator
            : null;

        $callOverlay = $this->callOverlayForStation($workstation, $primaryDevice, $operator);

        $deviceReady = $primaryDevice !== null && $primaryDevice->status->isRegistered();
        $posture = $callOverlay['posture'] ?? $this->idlePosture($operator, $primaryDevice, $deviceReady);
        $detail = $callOverlay['detail'] ?? $this->idleDetail($operator, $primaryDevice, $deviceReady);

        return [
            'workstation_id' => $workstation->id,
            'name' => $workstation->name,
            'location_label' => $workstation->displayLocation(),
            'operator_name' => $operator?->name,
            'operator_first_name' => $operator !== null ? $this->firstName($operator) : null,
            'posture' => $posture,
            'posture_tone' => $this->postureTone($posture),
            'current_situation' => $this->currentSituation($workstation, $operator, $primaryDevice, $deviceReady, $posture),
            'device_status' => $primaryDevice?->status->label() ?? 'No device',
            'is_ready' => $deviceReady && $operator !== null,
            'detail' => $detail,
            'live_call_session_id' => $callOverlay['call_session_id'] ?? null,
        ];
    }

    /**
     * Station where this user is the signed-in operator, if any.
     *
     * @return array<string, mixed>|null
     */
    public function forCurrentOperator(User $user): ?array
    {
        $workstation = Workstation::query()
            ->with(['currentOperator', 'devices'])
            ->where('is_active', true)
            ->where('current_operator_user_id', $user->id)
            ->first();

        if ($workstation === null) {
            return null;
        }

        return $this->forWorkstation($workstation);
    }

    /**
     * Live call at this station — operator ownership or ringing extension on this desk phone.
     *
     * @return array{posture: string, detail: string, call_session_id: int}|null
     */
    private function callOverlayForStation(
        Workstation $workstation,
        ?CommunicationDevice $device,
        ?User $operator,
    ): ?array {
        $extension = $device?->telephonyExtension ?? TelephonyExtension::primaryForWorkstation($workstation->id);
        $extensionId = $extension?->id;
        $extensionNumber = filled($extension?->extension) ? trim((string) $extension->extension) : null;

        $sessions = CallSession::query()
            ->with('customer:id,first_name,last_name')
            ->whereNull('ended_at')
            ->whereIn('status', [CallSessionStatus::Ringing, CallSessionStatus::Answered])
            ->latest('started_at')
            ->limit(12)
            ->get();

        foreach ($sessions as $session) {
            if (! $session->isActivelyLive()) {
                continue;
            }

            if ($operator !== null && $session->owned_by_user_id === $operator->id) {
                return $this->formatCallOverlay($session);
            }

            if ($extensionId !== null && (int) data_get($session->raw_payload, 'telephony_extension_id') === $extensionId) {
                return $this->formatCallOverlay($session);
            }

            if ($extensionNumber !== null && (string) data_get($session->raw_payload, 'asterisk_called_extension') === $extensionNumber) {
                return $this->formatCallOverlay($session);
            }
        }

        return null;
    }

    /**
     * @return array{posture: string, detail: string, call_session_id: int}
     */
    private function formatCallOverlay(CallSession $session): array
    {
        $customer = trim((string) ($session->customer?->name ?? ''));

        if ($session->status === CallSessionStatus::Ringing) {
            return [
                'posture' => 'Incoming call',
                'detail' => $customer !== '' ? $customer : 'Answer when ready',
                'call_session_id' => $session->id,
            ];
        }

        return [
            'posture' => 'On a call',
            'detail' => $customer !== '' ? $customer : 'Active call',
            'call_session_id' => $session->id,
        ];
    }

    private function idlePosture(?User $operator, ?CommunicationDevice $device, bool $deviceReady): string
    {
        if ($device === null) {
            return 'Needs device';
        }

        if (! $deviceReady) {
            return $device->status === CommunicationDeviceStatus::WaitingForRegistration
                ? 'Waiting for registration'
                : 'Offline';
        }

        if ($operator === null) {
            return 'Not signed in';
        }

        return 'Ready';
    }

    private function idleDetail(?User $operator, ?CommunicationDevice $device, bool $deviceReady): string
    {
        if ($device === null) {
            return 'Attach a phone to this station';
        }

        if (! $deviceReady) {
            return 'Phone not connected';
        }

        if ($operator === null) {
            return 'Sign in to take calls';
        }

        return 'No active calls';
    }

    private function currentSituation(
        Workstation $workstation,
        ?User $operator,
        ?CommunicationDevice $device,
        bool $deviceReady,
        string $posture,
    ): string {
        if ($posture === 'Incoming call' || $posture === 'On a call') {
            return $posture;
        }

        if ($device === null) {
            return "{$workstation->name} needs a phone";
        }

        if (! $deviceReady) {
            return "{$workstation->name} phone is not connected";
        }

        if ($operator === null) {
            return "{$workstation->name} is ready — sign in to answer customers";
        }

        return "{$workstation->name} is ready for customers";
    }

    private function postureTone(string $posture): string
    {
        return match ($posture) {
            'Ready' => 'success',
            'Incoming call' => 'warning',
            'On a call' => 'success',
            'Waiting for registration' => 'warning',
            'Not signed in' => 'muted',
            'Offline', 'Needs device' => 'danger',
            default => 'muted',
        };
    }

    private function firstName(User $user): string
    {
        $parts = preg_split('/\s+/', trim($user->name), 2);

        return $parts[0] ?? $user->name;
    }
}
