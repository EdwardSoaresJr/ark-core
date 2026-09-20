<?php

namespace App\Ark\Operations\Conversations\Projections;

use App\Ark\Mobile\MobileCallRecordingPlayback;
use App\Ark\Mobile\MobileTelephonyDialProjection;
use App\Ark\Operations\PhoneNumber;
use App\Ark\Operations\RepairOrders\RepairOrder;
use App\Ark\Operations\Telephony\CallSession;
use App\Ark\Operations\Telephony\CallSessionDirection;
use App\Ark\Operations\Timeline\OperationalEventEntry;
use App\Ark\Operations\Timeline\OperationalEventKind;
use App\Ark\Operations\Timeline\OperationalEventSource;
use App\Models\User;
use Illuminate\Support\Facades\Route;

/**
 * Action affordances for one timeline activity — shared across mobile and web surfaces.
 *
 * @phpstan-type ConversationActivityAction array{
 *     key: string,
 *     label: string,
 *     type: string,
 *     enabled: bool,
 *     params?: array<string, mixed>,
 * }
 */
final class ConversationActivityActionBuilder
{
    public function __construct(
        private readonly MobileCallRecordingPlayback $mobileRecording,
        private readonly MobileTelephonyDialProjection $mobileDialProjection,
    ) {}

    /**
     * @return list<ConversationActivityAction>
     */
    public function forEntry(
        OperationalEventEntry $entry,
        ConversationSurface $surface,
        ?RepairOrder $primaryRepairOrder = null,
        ?string $contactPhone = null,
        ?User $viewer = null,
    ): array {
        $metadata = $entry->metadata;
        $repairOrderId = $this->repairOrderDatabaseId($metadata, $primaryRepairOrder);
        $repairOrderNumber = $this->repairOrderNumber($metadata, $primaryRepairOrder);
        $customerId = $metadata['customer_id'] ?? $primaryRepairOrder?->customer_id;

        $actions = match ($entry->kind) {
            OperationalEventKind::Call,
            OperationalEventKind::MissedCall,
            OperationalEventKind::Voicemail => $this->callActions($entry, $surface, $repairOrderId, $repairOrderNumber, $customerId, $contactPhone, $viewer),
            OperationalEventKind::EstimateViewed,
            OperationalEventKind::EstimateSent => $this->estimateActions($surface, $repairOrderId, $repairOrderNumber),
            OperationalEventKind::Approval => $this->estimateActions($surface, $repairOrderId, $repairOrderNumber, includeInspection: false),
            OperationalEventKind::Inspection => $this->inspectionActions($surface, $repairOrderId, $repairOrderNumber),
            OperationalEventKind::Payment => $this->paymentActions($surface, $repairOrderId, $repairOrderNumber),
            OperationalEventKind::PortalActivity,
            OperationalEventKind::Portal => $this->portalActions($surface, $customerId, $repairOrderId, $repairOrderNumber),
            OperationalEventKind::Sms,
            OperationalEventKind::Email,
            OperationalEventKind::Messenger => $this->messageActions($surface, $repairOrderId, $repairOrderNumber, $contactPhone, $viewer, $customerId),
            default => $this->genericActions($surface, $repairOrderId, $repairOrderNumber, $customerId),
        };

        return array_values(array_filter($actions, fn (array $action): bool => $action['enabled']));
    }

    /**
     * @return list<ConversationActivityAction>
     */
    private function callActions(
        OperationalEventEntry $entry,
        ConversationSurface $surface,
        ?int $repairOrderId,
        ?int $repairOrderNumber,
        ?int $customerId,
        ?string $contactPhone,
        ?User $viewer = null,
    ): array {
        $actions = [];
        $session = $entry->subject instanceof CallSession ? $entry->subject : null;
        $metadata = $entry->metadata;

        if ($session instanceof CallSession) {
            $playback = $surface === ConversationSurface::Mobile
                ? $this->mobileRecording->projectFor($session)
                : null;

            if (($playback['has_recording'] ?? false) && filled($playback['recording_path'] ?? null)) {
                $actions[] = $this->action(
                    'play_recording',
                    'Play recording',
                    'playback',
                    params: ['path' => $playback['recording_path']],
                );
            } elseif ($metadata['has_recording'] ?? false) {
                $actions[] = $this->webRecordingAction($session, 'recording', 'Play recording');
            }

            if (($playback['has_voicemail'] ?? false) && filled($playback['voicemail_path'] ?? null)) {
                $actions[] = $this->action(
                    'play_voicemail',
                    'Play voicemail',
                    'playback',
                    params: ['path' => $playback['voicemail_path']],
                );
            } elseif ($metadata['has_voicemail'] ?? false) {
                $actions[] = $this->webRecordingAction($session, 'voicemail', 'Play voicemail');
            }

            $callbackPhone = $session->direction === CallSessionDirection::Inbound
                ? ($session->normalized_from ?: $session->from_number)
                : ($session->normalized_to ?: $session->to_number);

            if (filled($callbackPhone)) {
                $actions[] = $this->callBackAction($callbackPhone, $surface, $viewer, $customerId, $repairOrderId);
            }
        } elseif (filled($contactPhone)) {
            $actions[] = $this->callBackAction($contactPhone, $surface, $viewer, $customerId, $repairOrderId);
        }

        return [
            ...$actions,
            ...$this->genericActions($surface, $repairOrderId, $repairOrderNumber, $customerId),
        ];
    }

    /**
     * @return list<ConversationActivityAction>
     */
    private function estimateActions(
        ConversationSurface $surface,
        ?int $repairOrderId,
        ?int $repairOrderNumber,
        bool $includeInspection = false,
    ): array {
        $actions = [];

        if ($repairOrderId !== null) {
            $actions[] = $this->openRepairOrderAction($surface, $repairOrderId, $repairOrderNumber);
            $actions[] = $this->action(
                'open_estimate',
                'Open estimate',
                'navigate',
                params: $this->repairOrderParams($surface, $repairOrderId, $repairOrderNumber),
            );
        }

        if ($includeInspection && $repairOrderId !== null) {
            $actions[] = $this->action(
                'open_inspection',
                'Open inspection',
                'navigate',
                enabled: false,
                params: $this->repairOrderParams($surface, $repairOrderId, $repairOrderNumber),
            );
        }

        return $actions;
    }

    /**
     * @return list<ConversationActivityAction>
     */
    private function inspectionActions(
        ConversationSurface $surface,
        ?int $repairOrderId,
        ?int $repairOrderNumber,
    ): array {
        if ($repairOrderId === null) {
            return [];
        }

        return [
            $this->action(
                'open_inspection',
                'View inspection',
                'navigate',
                params: $this->repairOrderParams($surface, $repairOrderId, $repairOrderNumber),
            ),
            $this->openRepairOrderAction($surface, $repairOrderId, $repairOrderNumber),
        ];
    }

    /**
     * @return list<ConversationActivityAction>
     */
    private function paymentActions(
        ConversationSurface $surface,
        ?int $repairOrderId,
        ?int $repairOrderNumber,
    ): array {
        $actions = [];

        if ($repairOrderId !== null) {
            $actions[] = $this->action(
                'view_receipt',
                'View receipt',
                'navigate',
                params: $this->repairOrderParams($surface, $repairOrderId, $repairOrderNumber),
            );
            $actions[] = $this->openRepairOrderAction($surface, $repairOrderId, $repairOrderNumber);
        }

        return $actions;
    }

    /**
     * @return list<ConversationActivityAction>
     */
    private function portalActions(
        ConversationSurface $surface,
        ?int $customerId,
        ?int $repairOrderId,
        ?int $repairOrderNumber,
    ): array {
        $actions = [];

        if ($customerId !== null) {
            $actions[] = $this->openCustomerAction($surface, $customerId);
        }

        if ($repairOrderId !== null) {
            $actions[] = $this->openRepairOrderAction($surface, $repairOrderId, $repairOrderNumber);
        }

        return $actions;
    }

    /**
     * @return list<ConversationActivityAction>
     */
    private function messageActions(
        ConversationSurface $surface,
        ?int $repairOrderId,
        ?int $repairOrderNumber,
        ?string $contactPhone,
        ?User $viewer = null,
        ?int $customerId = null,
    ): array {
        $actions = [];

        if (filled($contactPhone)) {
            $actions[] = $this->callBackAction($contactPhone, $surface, $viewer, $customerId, $repairOrderId);
        }

        if ($repairOrderId !== null) {
            $actions[] = $this->openRepairOrderAction($surface, $repairOrderId, $repairOrderNumber);
        }

        return $actions;
    }

    /**
     * @return list<ConversationActivityAction>
     */
    private function genericActions(
        ConversationSurface $surface,
        ?int $repairOrderId,
        ?int $repairOrderNumber,
        ?int $customerId,
    ): array {
        $actions = [];

        if ($repairOrderId !== null) {
            $actions[] = $this->openRepairOrderAction($surface, $repairOrderId, $repairOrderNumber);
        }

        if ($customerId !== null) {
            $actions[] = $this->openCustomerAction($surface, $customerId);
        }

        return $actions;
    }

    /**
     * @return ConversationActivityAction
     */
    private function openRepairOrderAction(
        ConversationSurface $surface,
        int $repairOrderId,
        ?int $repairOrderNumber,
    ): array {
        return $this->action(
            'open_ro',
            'View RO',
            'navigate',
            params: $this->repairOrderParams($surface, $repairOrderId, $repairOrderNumber),
        );
    }

    /**
     * @return ConversationActivityAction
     */
    private function openCustomerAction(ConversationSurface $surface, int $customerId): array
    {
        $params = ['customer_id' => $customerId];

        if ($surface === ConversationSurface::Web && Route::has('operations.customers.show')) {
            $params['url'] = route('operations.customers.show', $customerId);
        }

        return $this->action('open_customer', 'Open customer', 'navigate', params: $params);
    }

    /**
     * @return ConversationActivityAction
     */
    private function callBackAction(
        string $phone,
        ConversationSurface $surface,
        ?User $viewer = null,
        ?int $customerId = null,
        ?int $repairOrderDatabaseId = null,
    ): array {
        $normalized = PhoneNumber::normalize($phone) ?? $phone;
        $params = ['phone' => $normalized];

        if ($surface === ConversationSurface::Web) {
            $tel = PhoneNumber::telUri($normalized);
            if ($tel !== null) {
                $params['url'] = $tel;
            }
        }

        if ($surface === ConversationSurface::Mobile && $viewer instanceof User) {
            $params['dial_method'] = $this->mobileDialProjection->dialMethodFor($viewer);

            if ($customerId !== null) {
                $params['customer_id'] = $customerId;
            }

            if ($repairOrderDatabaseId !== null) {
                $params['repair_order_id'] = $repairOrderDatabaseId;
            }
        }

        $label = match ($params['dial_method'] ?? null) {
            'shop_callback' => 'Callback',
            'in_app' => 'Call',
            default => 'Call back',
        };

        return $this->action('call_back', $label, 'dial', params: $params);
    }

    /**
     * @return ConversationActivityAction
     */
    private function webRecordingAction(CallSession $session, string $kind, string $label): array
    {
        if (! Route::has('operations.telephony.call-sessions.recording')) {
            return $this->action($kind === 'voicemail' ? 'play_voicemail' : 'play_recording', $label, 'playback', enabled: false);
        }

        return $this->action(
            $kind === 'voicemail' ? 'play_voicemail' : 'play_recording',
            $label,
            'link',
            params: [
                'url' => route('operations.telephony.call-sessions.recording', ['callSession' => $session, 'kind' => $kind]),
            ],
        );
    }

    /**
     * @param  array<string, mixed>  $params
     * @return ConversationActivityAction
     */
    private function action(
        string $key,
        string $label,
        string $type,
        bool $enabled = true,
        array $params = [],
    ): array {
        return [
            'key' => $key,
            'label' => $label,
            'type' => $type,
            'enabled' => $enabled,
            'params' => $params === [] ? null : $params,
        ];
    }

    /**
     * @param  array<string, mixed>  $metadata
     */
    private function repairOrderDatabaseId(array $metadata, ?RepairOrder $primaryRepairOrder): ?int
    {
        $subjectRoId = $metadata['repair_order_id'] ?? null;

        if ($subjectRoId !== null && is_numeric($subjectRoId)) {
            $resolved = RepairOrder::query()
                ->whereKey((int) $subjectRoId)
                ->orWhere('repair_order_id', (int) $subjectRoId)
                ->value('id');

            if ($resolved !== null) {
                return (int) $resolved;
            }
        }

        return $primaryRepairOrder?->id;
    }

    /**
     * @param  array<string, mixed>  $metadata
     */
    private function repairOrderNumber(array $metadata, ?RepairOrder $primaryRepairOrder): ?int
    {
        if ($primaryRepairOrder !== null) {
            return $primaryRepairOrder->repair_order_id;
        }

        $subjectRoId = $metadata['repair_order_id'] ?? null;

        if ($subjectRoId === null || ! is_numeric($subjectRoId)) {
            return null;
        }

        return RepairOrder::query()
            ->whereKey((int) $subjectRoId)
            ->orWhere('repair_order_id', (int) $subjectRoId)
            ->value('repair_order_id');
    }

    /**
     * @return array<string, mixed>
     */
    private function repairOrderParams(
        ConversationSurface $surface,
        int $repairOrderId,
        ?int $repairOrderNumber,
    ): array {
        $params = [
            'repair_order_id' => $repairOrderId,
            'repair_order_number' => $repairOrderNumber,
        ];

        if ($surface === ConversationSurface::Web && Route::has('operations.repair-orders.show')) {
            $params['url'] = route('operations.repair-orders.show', $repairOrderId);
        }

        return array_filter($params, fn ($value): bool => $value !== null);
    }
}
