<?php

namespace App\Ark\Operations\Communications;

use App\Ark\Communications\Provisioning\CommunicationDeviceMacAddress;
use App\Ark\Communications\Provisioning\EndpointProvisionServerUrl;
use App\Ark\Communications\Provisioning\FirstContactReadinessProjection;
use App\Ark\Platform\VoiceTransportConfiguration;
use App\Ark\Operations\Settings\ShopDisplayTimezone;
use App\Ark\Operations\Settings\ShopSettings;
use App\Ark\Operations\Telephony\CallSession;
use App\Ark\Operations\Telephony\CallSessionStatus;
use App\Ark\Operations\Workstations\WorkstationOperatorResolver;
use Illuminate\Support\Carbon;

/**
 * Device workspace truth — one projection per device render.
 */
final class CommunicationDeviceWorkspaceProjection
{
    public function __construct(
        private readonly WorkstationOperatorResolver $operatorResolver,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function resolve(CommunicationDevice $device): array
    {
        $device->loadMissing([
            'workstation.currentOperator',
            'workstation.primaryTelephonyExtension',
            'deviceModel',
            'telephonyExtension',
            'currentEndpointConfigurationProjection',
        ]);

        $operator = $this->operatorResolver->currentOperatorForDevice($device);
        $activeSession = $this->activeSessionForDevice($device, $operator?->id);
        $lastSession = $this->lastSessionForDevice($device, $operator?->id);
        $projection = $device->currentEndpointConfigurationProjection;
        $firstContact = app(FirstContactReadinessProjection::class)->forDevice($device);

        return [
            'device' => $device,
            'workstation_name' => $device->workstation?->name,
            'workstation_location' => $device->workstation?->displayLocation(),
            'extension_number' => $device->telephonyExtension?->extension
                ?? $device->workstation?->primaryTelephonyExtension?->extension,
            'extension_display_name' => $device->telephonyExtension?->display_name
                ?? $device->workstation?->primaryTelephonyExtension?->display_name,
            'provisioning_host' => VoiceTransportConfiguration::resolveRegistrar(),
            'provisioning_server_url' => EndpointProvisionServerUrl::base(),
            'provisioning_server_scheme' => EndpointProvisionServerUrl::scheme(),
            'current_operator_label' => $operator?->name,
            'status_label' => $device->status->isRegistered() ? 'Connected' : 'Offline',
            'status_tone' => $device->status->tone(),
            'current_activity' => $this->currentActivityLabel($device, $activeSession),
            'current_activity_tone' => $activeSession !== null ? 'success' : 'muted',
            'last_session_headline' => $this->lastSessionHeadline($lastSession),
            'last_session_when' => $this->lastSessionWhen($lastSession),
            'mac_address_display' => filled($device->mac_address)
                ? CommunicationDeviceMacAddress::display((string) $device->mac_address)
                : null,
            'device_model_label' => $device->deviceModel?->label ?? $device->model,
            'provision_url' => $device->provisionUrl(),
            'projection_status' => $projection !== null ? 'Current' : 'None',
            'projection_fingerprint' => $projection?->inputs_fingerprint,
            'projection_fingerprint_short' => $projection !== null
                ? strtoupper(substr($projection->inputs_fingerprint, 0, 4)).'…'
                : null,
            'projection_generated_at_label' => $this->projectionGeneratedAtLabel($projection?->generated_at),
            'projection_builder' => $projection?->builder?->value,
            'projection_serialized_config' => $projection?->serialized_config,
            'can_generate_config' => ! in_array($device->status, [CommunicationDeviceStatus::Provisioning], true),
            'can_download_config' => $device->hasProvisionConfig(),
            'first_contact' => $firstContact,
        ];
    }

    private function activeSessionForDevice(CommunicationDevice $device, ?int $operatorUserId): ?CallSession
    {
        if ($operatorUserId === null) {
            return null;
        }

        $session = CallSession::query()
            ->with('customer:id,first_name,last_name')
            ->where('owned_by_user_id', $operatorUserId)
            ->whereNull('ended_at')
            ->whereNull('worked_at')
            ->whereIn('status', [CallSessionStatus::Ringing, CallSessionStatus::Answered])
            ->latest('started_at')
            ->first();

        return $session instanceof CallSession && $session->isActivelyLive()
            ? $session
            : null;
    }

    private function lastSessionForDevice(CommunicationDevice $device, ?int $operatorUserId): ?CallSession
    {
        if ($operatorUserId === null) {
            return null;
        }

        return CallSession::query()
            ->with('customer:id,first_name,last_name')
            ->where('owned_by_user_id', $operatorUserId)
            ->latest('started_at')
            ->first();
    }

    private function currentActivityLabel(CommunicationDevice $device, ?CallSession $activeSession): string
    {
        if ($activeSession instanceof CallSession) {
            if ($activeSession->status === CallSessionStatus::Ringing) {
                return 'Ringing';
            }

            $customer = trim((string) ($activeSession->customer?->name ?? ''));

            return $customer !== ''
                ? "On a call with {$customer}"
                : 'On a call';
        }

        if ($device->status->isRegistered()) {
            return 'Idle';
        }

        if ($device->status === CommunicationDeviceStatus::WaitingForRegistration) {
            return 'Waiting for registration';
        }

        return 'Offline';
    }

    private function lastSessionHeadline(?CallSession $session): ?string
    {
        if (! $session instanceof CallSession) {
            return null;
        }

        $customer = trim((string) ($session->customer?->name ?? ''));

        return $customer !== '' ? "Customer: {$customer}" : 'Recent call';
    }

    private function lastSessionWhen(?CallSession $session): ?string
    {
        if (! $session instanceof CallSession || $session->started_at === null) {
            return null;
        }

        $started = $session->started_at->timezone(ShopDisplayTimezone::resolve());

        if ($started->isToday()) {
            return 'Today · '.$started->format('g:i A');
        }

        if ($started->isYesterday()) {
            return 'Yesterday';
        }

        return $started->format('M j');
    }

    private function projectionGeneratedAtLabel(?Carbon $generatedAt): ?string
    {
        if ($generatedAt === null) {
            return null;
        }

        $at = $generatedAt->timezone(ShopDisplayTimezone::resolve());
        $secondsAgo = max(0, (int) $generatedAt->diffInSeconds(now()));

        if ($secondsAgo < 60) {
            return $secondsAgo <= 1 ? 'Just now' : "{$secondsAgo} seconds ago";
        }

        if ($at->isToday()) {
            return 'Today · '.$at->format('g:i A');
        }

        if ($at->isYesterday()) {
            return 'Yesterday · '.$at->format('g:i A');
        }

        return $at->format('M j · g:i A');
    }
}
