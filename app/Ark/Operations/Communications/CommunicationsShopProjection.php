<?php

namespace App\Ark\Operations\Communications;

use App\Ark\Communications\Provisioning\CommunicationDeviceMacAddress;
use App\Ark\Communications\Provisioning\CommunicationDeviceModel;
use App\Ark\Operations\PhoneNumber;
use App\Ark\Operations\Realtime\SessionEvent;
use App\Ark\Operations\Settings\ShopDisplayTimezone;
use App\Ark\Operations\Settings\ShopSettings;
use App\Ark\Operations\Staff\StaffMemberController;
use App\Ark\Operations\Telephony\CallSession;
use App\Ark\Operations\Telephony\CallSessionStatus;
use App\Ark\Operations\Telephony\MobileVoice\MobileVoiceEndpointRegistrar;
use App\Ark\Operations\Telephony\TelephonyCallFlowSettings;
use App\Ark\Operations\Telephony\TelephonyEndpoint;
use App\Ark\Operations\Telephony\TelephonyEndpointType;
use App\Ark\Operations\Telephony\TelephonyExtension;
use App\Ark\Operations\Telephony\TelephonyHealth;
use App\Ark\Operations\Telephony\TelephonyProviderType;
use App\Ark\Operations\Workstations\Workstation;
use App\Ark\Operations\Workstations\WorkstationOperatorResolver;
use App\Models\User;
use Illuminate\Support\Collection;

/**
 * Answers: Can my shop communicate right now?
 * Readiness = shop health; Activity = what is happening on the floor now.
 */
final class CommunicationsShopProjection
{
    public function __construct(
        private readonly TelephonyHealth $telephonyHealth,
        private readonly WorkstationOperatorResolver $operatorResolver,
        private readonly MobileVoiceEndpointRegistrar $mobileVoiceEndpoints,
    ) {}

    public static function forCurrentShop(): self
    {
        return new self(
            TelephonyHealth::forCurrentShop(),
            app(WorkstationOperatorResolver::class),
            app(MobileVoiceEndpointRegistrar::class),
        );
    }

    /**
     * @return array{
     *     business_number: ?string,
     *     business_number_display: ?string,
     *     operator_question: string,
     *     readiness_label: string,
     *     readiness_tone: string,
     *     activity: list<CommunicationsShopActivityRow>,
     *     coverage: list<CommunicationsShopCoverageRow>,
     *     attention: list<CommunicationsShopAttentionRow>,
     *     devices: list<CommunicationsShopDeviceRow>,
     *     device_count: int,
     *     workstations: list<CommunicationsShopWorkstationRow>,
     *     workstation_count: int,
     *     ring_targets: list<array{user_id: int, name: string, enabled: bool}>,
     *     after_hours_destination: string,
     *     health: list<CommunicationsShopHealthRow>,
     *     shop_ready: bool,
     *     setup_in_progress: bool,
     *     next_setup_step: ?string,
     *     voice_posture: string,
     *     active_setup_step: string,
     *     focus_workstation: ?CommunicationsShopWorkstationRow,
     *     setup_steps: list<array{key: string, label: string, passed: bool}>,
     *     pending_devices: list<CommunicationsShopPendingDeviceRow>,
     *     pending_device_count: int,
     *     device_models: \Illuminate\Support\Collection<int, CommunicationDeviceModel>,
     * }
     */
    public function resolve(): array
    {
        $settings = ShopSettings::current();
        $flow = TelephonyCallFlowSettings::fromShopSettings($settings);
        $businessNumber = filled($settings->telephony_inbound_number)
            ? (string) $settings->telephony_inbound_number
            : null;

        $devicesByUser = CommunicationDevice::query()
            ->with('assignedUser')
            ->orderBy('name')
            ->get()
            ->groupBy('assigned_user_id');

        $endpoints = TelephonyEndpoint::query()
            ->with('user')
            ->orderBy('position')
            ->orderBy('id')
            ->get()
            ->groupBy('user_id');

        $devices = $this->deviceRows();
        $workstations = $this->workstationRows();
        $pendingDevices = $this->pendingDeviceRows();
        $health = $this->healthRows($businessNumber);
        $attention = $this->attentionRows($businessNumber, $devices);
        $activeSessionsByUser = $this->activeSessionsByUser();
        $floorOperatorUserIds = Workstation::query()
            ->where('is_active', true)
            ->whereNotNull('current_operator_user_id')
            ->pluck('current_operator_user_id');
        $setupInProgress = $this->setupInProgress($workstations, $devices);
        $focusWorkstation = $this->focusWorkstation($workstations);
        $activeSetupStep = $this->activeSetupStep($workstations, $devices);
        $shopReady = collect($health)->every(fn (CommunicationsShopHealthRow $row): bool => $row->passed)
            && $attention === [];
        $voicePosture = $this->voicePosture($setupInProgress, $shopReady, $devices, $attention);
        $readinessLabel = match (true) {
            $shopReady => 'Ready',
            $setupInProgress => 'Setup in progress',
            default => 'Needs attention',
        };
        $readinessTone = match (true) {
            $shopReady => 'success',
            $setupInProgress => 'muted',
            default => 'warning',
        };

        return [
            'business_number' => $businessNumber,
            'business_number_display' => PhoneNumber::display($businessNumber) ?? $businessNumber,
            'operator_question' => $shopReady
                ? 'Communications are ready for customers.'
                : 'What needs attention before customers can reach you?',
            'readiness_label' => $readinessLabel,
            'readiness_tone' => $readinessTone,
            'activity' => $this->activityRows($devicesByUser, $activeSessionsByUser, $floorOperatorUserIds),
            'coverage' => $this->coverageRows($devicesByUser, $activeSessionsByUser, $floorOperatorUserIds),
            'attention' => $attention,
            'devices' => $devices,
            'device_count' => count($devices),
            'workstations' => $workstations,
            'workstation_count' => count($workstations),
            'ring_targets' => $this->ringTargets($endpoints),
            'after_hours_destination' => $flow->isOpenAt() ? 'Ring staff' : 'Voicemail',
            'health' => $health,
            'shop_ready' => $shopReady,
            'setup_in_progress' => $setupInProgress,
            'next_setup_step' => $this->nextSetupStep($workstations, $devices),
            'voice_posture' => $voicePosture,
            'active_setup_step' => $activeSetupStep,
            'focus_workstation' => $focusWorkstation,
            'setup_steps' => $this->setupSteps($workstations, $devices),
            'pending_devices' => $pendingDevices,
            'pending_device_count' => count($pendingDevices),
            'device_models' => CommunicationDeviceModel::query()
                ->where('enabled', true)
                ->orderBy('label')
                ->get(['id', 'slug', 'label']),
        ];
    }

    /**
     * @return array{
     *     user: User,
     *     devices: Collection<int, CommunicationDevice>,
     * }
     */
    public function personContext(User $user): array
    {
        return [
            'user' => $user,
            'devices' => CommunicationDevice::query()
                ->where('assigned_user_id', $user->id)
                ->orderBy('name')
                ->get(),
        ];
    }

    /**
     * @return array{
     *     device: CommunicationDevice,
     *     assigned_user: ?User,
     *     last_session: ?CallSession,
     * }
     */
    public function deviceContext(CommunicationDevice $device): array
    {
        $device->loadMissing('assignedUser');

        $lastSession = null;

        if ($device->assigned_user_id !== null) {
            $lastSession = CallSession::query()
                ->where('owned_by_user_id', $device->assigned_user_id)
                ->latest('started_at')
                ->first();
        }

        return [
            'device' => $device,
            'assigned_user' => $device->assignedUser,
            'last_session' => $lastSession,
        ];
    }

    /**
     * @return Collection<int, CallSession>
     */
    private function activeSessionsByUser(): Collection
    {
        return CallSession::query()
            ->with('customer:id,first_name,last_name')
            ->whereNotNull('owned_by_user_id')
            ->whereNull('ended_at')
            ->whereNull('worked_at')
            ->whereIn('status', [
                CallSessionStatus::Ringing,
                CallSessionStatus::Answered,
            ])
            ->orderByDesc('started_at')
            ->get()
            ->filter(fn (CallSession $session): bool => $session->isActivelyLive())
            ->unique('owned_by_user_id')
            ->keyBy('owned_by_user_id');
    }

    /**
     * @param  Collection<int|string|null, Collection<int, CommunicationDevice>>  $devicesByUser
     * @param  Collection<int, CallSession>  $activeSessionsByUser
     * @param  Collection<int, int>  $floorOperatorUserIds
     * @return list<CommunicationsShopActivityRow>
     */
    private function activityRows(
        Collection $devicesByUser,
        Collection $activeSessionsByUser,
        Collection $floorOperatorUserIds,
    ): array {
        return StaffMemberController::list()
            ->map(function (User $user) use ($devicesByUser, $activeSessionsByUser, $floorOperatorUserIds): CommunicationsShopActivityRow {
                $firstName = $this->staffFirstName($user);
                $session = $activeSessionsByUser->get($user->id);

                if ($session instanceof CallSession) {
                    if ($session->status === CallSessionStatus::Ringing) {
                        return new CommunicationsShopActivityRow(
                            userId: $user->id,
                            label: "{$firstName}'s phone is ringing",
                            tone: 'warning',
                        );
                    }

                    $customerName = trim((string) ($session->customer?->name ?? ''));
                    $label = $customerName !== ''
                        ? "{$firstName} is on a call with {$customerName}"
                        : "{$firstName} is on a call";

                    return new CommunicationsShopActivityRow(
                        userId: $user->id,
                        label: $label,
                        tone: 'success',
                    );
                }

                if ($this->userHasFloorPresence($devicesByUser, $floorOperatorUserIds, $user)) {
                    return new CommunicationsShopActivityRow(
                        userId: $user->id,
                        label: "{$firstName} is available",
                        tone: 'success',
                    );
                }

                return new CommunicationsShopActivityRow(
                    userId: $user->id,
                    label: "{$firstName} is offline",
                    tone: 'muted',
                );
            })
            ->values()
            ->all();
    }

    /**
     * @param  Collection<int|string|null, Collection<int, CommunicationDevice>>  $devicesByUser
     * @param  Collection<int, CallSession>  $activeSessionsByUser
     * @param  Collection<int, int>  $floorOperatorUserIds
     * @return list<CommunicationsShopCoverageRow>
     */
    private function coverageRows(
        Collection $devicesByUser,
        Collection $activeSessionsByUser,
        Collection $floorOperatorUserIds,
    ): array {
        return StaffMemberController::list()
            ->map(function (User $user) use ($devicesByUser, $activeSessionsByUser, $floorOperatorUserIds): CommunicationsShopCoverageRow {
                $session = $activeSessionsByUser->get($user->id);

                if ($session instanceof CallSession) {
                    if ($session->status === CallSessionStatus::Ringing) {
                        return new CommunicationsShopCoverageRow(
                            userId: $user->id,
                            name: $user->name,
                            statusTone: 'warning',
                            summary: 'Phone ringing',
                        );
                    }

                    $customerName = trim((string) ($session->customer?->name ?? ''));

                    return new CommunicationsShopCoverageRow(
                        userId: $user->id,
                        name: $user->name,
                        statusTone: 'success',
                        summary: $customerName !== ''
                            ? "On a call with {$customerName}"
                            : 'On a call',
                    );
                }

                $connected = $this->userHasFloorPresence($devicesByUser, $floorOperatorUserIds, $user);

                return new CommunicationsShopCoverageRow(
                    userId: $user->id,
                    name: $user->name,
                    statusTone: $connected ? 'success' : 'muted',
                    summary: $connected ? 'Available' : 'Offline',
                );
            })
            ->values()
            ->all();
    }

    /**
     * Floor presence for coverage — not ring-target configuration.
     *
     * Available when: signed in at a station, registered assigned desk phone, or live mobile app.
     *
     * @param  Collection<int|string|null, Collection<int, CommunicationDevice>>  $devicesByUser
     * @param  Collection<int, int>  $floorOperatorUserIds
     */
    private function userHasFloorPresence(
        Collection $devicesByUser,
        Collection $floorOperatorUserIds,
        User $user,
    ): bool {
        if ($floorOperatorUserIds->contains($user->id)) {
            return true;
        }

        /** @var Collection<int, CommunicationDevice> $userDevices */
        $userDevices = $devicesByUser->get($user->id, collect());

        if ($userDevices->contains(
            fn (CommunicationDevice $device): bool => $device->status->isRegistered(),
        )) {
            return true;
        }

        return $this->mobileVoiceEndpoints->userHasLiveCoverage($user);
    }

    private function staffFirstName(User $user): string
    {
        $parts = preg_split('/\s+/', trim($user->name), 2);

        return $parts[0] ?? $user->name;
    }

    /**
     * @param  list<CommunicationsShopDeviceRow>  $devices
     * @return list<CommunicationsShopAttentionRow>
     */
    private function attentionRows(?string $businessNumber, array $devices): array
    {
        $attention = [];

        if (! filled($businessNumber)) {
            $attention[] = new CommunicationsShopAttentionRow(
                message: 'Business number is not configured',
            );
        }

        foreach ($devices as $device) {
            if ($device->statusLabel !== 'Connected') {
                $attention[] = new CommunicationsShopAttentionRow(
                    message: "{$device->name} offline",
                    deviceId: $device->deviceId,
                );
            }
        }

        return $attention;
    }

    /**
     * @return list<CommunicationsShopDeviceRow>
     */
    private function deviceRows(): array
    {
        return CommunicationDevice::query()
            ->with('workstation.currentOperator')
            ->where('status', '!=', CommunicationDeviceStatus::Discovered)
            ->orderBy('name')
            ->get()
            ->map(fn (CommunicationDevice $device): CommunicationsShopDeviceRow => new CommunicationsShopDeviceRow(
                deviceId: $device->id,
                name: $device->name,
                statusTone: $device->status->tone(),
                statusLabel: $device->status->isRegistered() ? 'Connected' : 'Offline',
                currentOperatorLabel: $this->operatorResolver->labelForDevice($device),
                workstationName: $device->workstation?->name,
            ))
            ->all();
    }

    /**
     * @return list<CommunicationsShopWorkstationRow>
     */
    private function workstationRows(): array
    {
        $workstations = Workstation::query()
            ->with(['currentOperator', 'devices'])
            ->where('is_active', true)
            ->where('accepts_scheduled_work', false)
            ->orderBy('name')
            ->get();

        $extensionMap = TelephonyExtension::primaryMapForWorkstations(
            $workstations->pluck('id')->map(fn (mixed $id): int => (int) $id)->all(),
        );

        return $workstations
            ->map(function (Workstation $workstation) use ($extensionMap): CommunicationsShopWorkstationRow {
                $primaryDevice = $workstation->devices->sortBy('name')->first();
                $extension = $extensionMap[$workstation->id] ?? null;
                $isReady = $primaryDevice !== null && $primaryDevice->status->isRegistered();
                $stationStatusLabel = match (true) {
                    $primaryDevice === null => 'Needs device',
                    $isReady => 'Ready',
                    $primaryDevice->status === CommunicationDeviceStatus::WaitingForRegistration => 'Waiting for registration',
                    default => 'Offline',
                };
                $stationStatusTone = match (true) {
                    $isReady => 'success',
                    $primaryDevice === null => 'muted',
                    $primaryDevice->status === CommunicationDeviceStatus::WaitingForRegistration => 'warning',
                    default => 'danger',
                };

                return new CommunicationsShopWorkstationRow(
                    workstationId: $workstation->id,
                    name: $workstation->name,
                    rawLocationLabel: filled($workstation->location_label) ? (string) $workstation->location_label : null,
                    locationLabel: $workstation->displayLocation(),
                    currentOperatorLabel: $workstation->currentOperator?->name,
                    deviceCount: $workstation->devices->count(),
                    extensionNumber: $extension?->extension,
                    extensionDisplayName: $extension?->display_name,
                    primaryDeviceId: $primaryDevice?->id,
                    primaryDeviceName: $primaryDevice?->name,
                    primaryDeviceStatusLabel: $primaryDevice !== null
                        ? ($primaryDevice->status->isRegistered() ? 'Connected' : $primaryDevice->status->label())
                        : 'No device',
                    primaryDeviceStatusTone: $primaryDevice?->status->tone() ?? 'muted',
                    suggestedExtension: SuggestedWorkstationExtension::next(),
                    stationStatusLabel: $stationStatusLabel,
                    stationStatusTone: $stationStatusTone,
                    isReady: $isReady,
                    acceptsScheduledWork: (bool) $workstation->accepts_scheduled_work,
                );
            })
            ->all();
    }

    /**
     * @param  Collection<int|string|null, Collection<int, TelephonyEndpoint>>  $endpointsByUser
     * @return list<array{user_id: int, name: string, enabled: bool}>
     */
    private function ringTargets(Collection $endpointsByUser): array
    {
        $targets = [];

        foreach (StaffMemberController::list() as $user) {
            /** @var Collection<int, TelephonyEndpoint> $userEndpoints */
            $userEndpoints = $endpointsByUser->get($user->id, collect());

            if ($userEndpoints->isEmpty()) {
                continue;
            }

            $targets[] = [
                'user_id' => $user->id,
                'name' => $user->name,
                'enabled' => $userEndpoints->contains(fn (TelephonyEndpoint $endpoint): bool => (bool) $endpoint->enabled),
            ];
        }

        return $targets;
    }

    /**
     * @return list<CommunicationsShopHealthRow>
     */
    private function healthRows(?string $businessNumber): array
    {
        $voiceTone = $this->telephonyHealth->voiceIngressTone();
        $businessNumberOnline = filled($businessNumber)
            && $this->telephonyHealth->credentialsConfigured()
            && in_array($voiceTone, ['success', 'warning'], true);
        $phonesConnected = $this->phonesConnected();
        $providerConnected = in_array($this->telephonyHealth->connectionTone(), ['success', 'warning'], true);
        $testCallPassed = $this->testCallPassed();

        return [
            new CommunicationsShopHealthRow(
                label: 'Business Number Online',
                passed: $businessNumberOnline,
                detail: $businessNumberOnline
                    ? (PhoneNumber::display($businessNumber) ?? $businessNumber)
                    : 'Configure the shop business number',
            ),
            new CommunicationsShopHealthRow(
                label: 'Communications Healthy',
                passed: $businessNumberOnline && $phonesConnected && $providerConnected,
                detail: $businessNumberOnline && $phonesConnected
                    ? 'Customer access and station devices are online'
                    : 'Complete station setup below',
            ),
        ];
    }

    private function phonesConnected(): bool
    {
        return CommunicationDevice::query()
            ->whereIn('status', [
                CommunicationDeviceStatus::Connected,
                CommunicationDeviceStatus::Provisioned,
            ])
            ->exists();
    }

    private function testCallPassed(): bool
    {
        $recentTwilioSessionIds = CallSession::query()
            ->where('provider', TelephonyProviderType::Twilio)
            ->where('started_at', '>=', now()->subDays(7))
            ->pluck('id');

        if ($recentTwilioSessionIds->isEmpty()) {
            return false;
        }

        return SessionEvent::query()
            ->whereIn('call_session_id', $recentTwilioSessionIds)
            ->exists();
    }

    /**
     * @return list<CommunicationsShopPendingDeviceRow>
     */
    private function pendingDeviceRows(): array
    {
        return CommunicationDevice::query()
            ->with('deviceModel')
            ->where('status', CommunicationDeviceStatus::Discovered)
            ->orderByDesc('updated_at')
            ->get()
            ->map(function (CommunicationDevice $device): CommunicationsShopPendingDeviceRow {
                $mac = filled($device->mac_address)
                    ? CommunicationDeviceMacAddress::display((string) $device->mac_address)
                    : '—';

                $foundAgo = $device->updated_at !== null
                    ? 'Found '.$device->updated_at->diffForHumans()
                    : 'Found just now';

                return new CommunicationsShopPendingDeviceRow(
                    deviceId: $device->id,
                    name: $device->name,
                    modelLabel: $device->deviceModel?->label ?? (string) ($device->model ?? 'Phone'),
                    macDisplay: $mac,
                    foundAgoLabel: $foundAgo,
                );
            })
            ->all();
    }

    /**
     * @param  list<CommunicationsShopWorkstationRow>  $workstations
     * @param  list<CommunicationsShopDeviceRow>  $devices
     */
    private function setupInProgress(array $workstations, array $devices): bool
    {
        if ($workstations === []) {
            return $devices === [];
        }

        if ($devices === []) {
            return true;
        }

        foreach ($workstations as $workstation) {
            if ($workstation->deviceCount === 0) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  list<CommunicationsShopWorkstationRow>  $workstations
     * @param  list<CommunicationsShopDeviceRow>  $devices
     */
    private function nextSetupStep(array $workstations, array $devices): ?string
    {
        if ($workstations === []) {
            return 'Add a station — e.g. Front Counter.';
        }

        foreach ($workstations as $workstation) {
            if ($workstation->deviceCount === 0) {
                return "Plug in a phone for {$workstation->name}.";
            }
        }

        return null;
    }

    /**
     * @param  list<CommunicationsShopWorkstationRow>  $workstations
     */
    private function focusWorkstation(array $workstations): ?CommunicationsShopWorkstationRow
    {
        foreach ($workstations as $workstation) {
            if ($workstation->deviceCount === 0) {
                return $workstation;
            }
        }

        return $workstations[0] ?? null;
    }

    /**
     * @param  list<CommunicationsShopWorkstationRow>  $workstations
     * @param  list<CommunicationsShopDeviceRow>  $devices
     */
    private function activeSetupStep(array $workstations, array $devices): string
    {
        if ($workstations === []) {
            return 'workstation';
        }

        foreach ($workstations as $workstation) {
            if ($workstation->deviceCount === 0) {
                return 'phone';
            }
        }

        return 'complete';
    }

    /**
     * @param  list<CommunicationsShopWorkstationRow>  $workstations
     * @param  list<CommunicationsShopDeviceRow>  $devices
     * @return list<array{key: string, label: string, passed: bool}>
     */
    private function setupSteps(array $workstations, array $devices): array
    {
        $hasWorkstation = $workstations !== [];
        $hasPhone = $hasWorkstation
            && collect($workstations)->every(fn (CommunicationsShopWorkstationRow $row): bool => $row->deviceCount > 0);
        $firstContactComplete = collect($devices)->contains(
            fn (CommunicationsShopDeviceRow $device): bool => $device->statusLabel === 'Connected',
        );

        return [
            ['key' => 'workstation', 'label' => 'Station', 'passed' => $hasWorkstation],
            ['key' => 'phone', 'label' => 'Device', 'passed' => $hasPhone],
            ['key' => 'first_contact', 'label' => 'Connected', 'passed' => $firstContactComplete],
        ];
    }

    /**
     * @param  list<CommunicationsShopDeviceRow>  $devices
     * @param  list<CommunicationsShopAttentionRow>  $attention
     */
    private function voicePosture(bool $setupInProgress, bool $shopReady, array $devices, array $attention): string
    {
        if ($setupInProgress) {
            return 'setup';
        }

        if ($shopReady || $attention !== []) {
            return 'operate';
        }

        $hasConnectedPhone = collect($devices)->contains(
            fn (CommunicationsShopDeviceRow $device): bool => $device->statusLabel === 'Connected',
        );

        if (! $hasConnectedPhone) {
            return 'certify';
        }

        return 'operate';
    }
}
