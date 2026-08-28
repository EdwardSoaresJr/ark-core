<?php

namespace App\Ark\Mobile;

use App\Ark\Operations\Conversations\ConversationResolver;
use App\Ark\Operations\Conversations\CustomerCallContextOpenRepairOrder;
use App\Ark\Operations\Conversations\CustomerCallContextResolver;
use App\Ark\Operations\Customers\Customer;
use App\Ark\Operations\Customers\CustomerHubCommsTimeline;
use App\Ark\Operations\Messaging\RepairOrderConversationSendProjection;
use App\Ark\Operations\PhoneNumber;
use App\Ark\Operations\RepairOrders\RepairOrder;
use App\Ark\Operations\RepairOrders\RepairOrderStatus;
use App\Ark\Operations\Vehicles\Vehicle;
use App\Models\User;
use Illuminate\Support\Collection;

/**
 * Portable Station — customer workspace loads authority for blocks; orientation follows observations.
 */
final class MobileCustomerWorkspaceProjection
{
    public function __construct(
        private readonly CustomerCallContextResolver $callContextResolver,
        private readonly CustomerHubCommsTimeline $hubCommsTimeline,
        private readonly ConversationResolver $conversationResolver,
        private readonly MobileTelephonyDialProjection $dialProjection,
        private readonly MobileStaffAccess $access,
        private readonly MobileCustomerWorkspaceObservationSelector $observationSelector,
        private readonly MobileCustomerWorkspaceLayoutEngine $layoutEngine,
        private readonly MobileEstimateProjection $estimate,
        private readonly MobileScheduleProjection $schedule,
        private readonly MobileUserPresenter $userPresenter,
        private readonly RepairOrderConversationSendProjection $sendProjection,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function forCustomer(
        Customer $customer,
        User $viewer,
        ?string $requestedObservation = null,
    ): array {
        $customer->load([
            'vehicles' => fn ($query) => $query->orderByDesc('id'),
            'repairOrders' => fn ($query) => $query
                ->with(['vehicle', 'concerns.lines'])
                ->latest()
                ->limit(12),
        ]);

        $profile = $this->userPresenter->repairOrderWorkspaceProfile($viewer);
        $showMoney = $profile !== 'technician';
        $moneyFor = fn (RepairOrder $repairOrder): ?array => $showMoney
            ? $this->moneySummary($repairOrder)
            : null;

        $callContext = $this->callContextResolver->resolveForCustomer($customer, 8);
        $normalizedPhone = $callContext->normalizedPhone !== ''
            ? $callContext->normalizedPhone
            : null;

        $timeline = $this->hubCommsTimeline->buildForCustomer(
            $customer,
            $normalizedPhone,
            60,
        );

        $requested = filled($requestedObservation) ? (string) $requestedObservation : null;
        $openRepairOrders = $customer->repairOrders
            ->filter(fn (RepairOrder $repairOrder): bool => in_array(
                $repairOrder->status->value,
                RepairOrderStatus::operationalQueueValues(),
                true,
            ))
            ->values();

        $observation = $this->observationSelector->select(
            $requested,
            $timeline,
            $openRepairOrders->isNotEmpty(),
        );
        $inferred = $requested === null;

        /** @var CustomerCallContextOpenRepairOrder|null $primaryOpen */
        $primaryOpen = $callContext->openRepairOrders->first();
        $primaryRo = $primaryOpen?->repairOrder ?? $openRepairOrders->first();
        $primaryVehicle = $primaryOpen?->vehicle ?? $customer->vehicles->first();
        $primaryVehicleLabel = $this->primaryVehicleLabel($customer, $primaryVehicle);

        $conversation = $this->conversationResolver->findForPhone($customer->phone);
        $canCommunicate = $this->access->canAccessShopCommunications($viewer);
        $canReply = $canCommunicate && $this->access->canReplyToCustomer($viewer);

        $messagePreview = $this->layoutEngine->messagePreview($timeline);
        $callPreview = $this->layoutEngine->callPreview($timeline);
        $timelineItems = $this->layoutEngine->presentTimeline($timeline);

        $primaryRoMoney = $primaryRo !== null ? $moneyFor($primaryRo) : null;
        $primarySend = $primaryRo !== null && $showMoney
            ? $this->sendProjection->forRepairOrder($primaryRo, $viewer)
            : null;

        $upcomingAppointment = $this->access->canManageAppointments($viewer)
            ? $this->schedule->upcomingForCustomer($customer->id, $viewer)
            : null;

        $layoutContext = [
            'customer' => $customer,
            'viewer' => $viewer,
            'timeline' => $timeline,
            'primary_open' => $primaryOpen,
            'primary_repair_order' => $primaryRo,
            'call_preview' => $callPreview,
            'can_reply' => $canReply,
            'can_intake' => $this->access->canPerformIntake($viewer),
            'conversation_id' => $conversation?->id,
            'dial_method' => $this->dialProjection->dialMethodFor($viewer),
            'default_orientation' => $this->defaultOrientation($customer, $viewer, $primaryOpen, $openRepairOrders),
            'conversation_payload' => $canCommunicate ? [
                'enabled' => $conversation !== null || $messagePreview !== [] || ($canReply && filled($customer->phone)),
                'conversation_id' => $conversation?->id,
                'preview' => $messagePreview,
                'can_reply' => $canReply,
                'has_history' => $conversation !== null || $messagePreview !== [],
            ] : null,
            'calls_payload' => [
                'enabled' => $callPreview !== [],
                'preview' => $callPreview,
            ],
            'repair_order_payload' => $this->layoutEngine->repairOrderPayload($primaryRo, $primaryRoMoney),
            'open_work_payload' => $this->layoutEngine->openWorkPayload($openRepairOrders, $moneyFor),
            'vehicle_payload' => $this->layoutEngine->vehiclePayload($primaryVehicle, $primaryVehicleLabel),
            'appointment_payload' => [
                'enabled' => $this->access->canManageAppointments($viewer),
                'upcoming' => $upcomingAppointment,
                'headline' => $upcomingAppointment !== null
                    ? ($upcomingAppointment['time_label'].' · '.($upcomingAppointment['concern'] ?? 'Appointment'))
                    : 'No upcoming appointment',
                'detail' => $upcomingAppointment !== null
                    ? trim(collect([
                        $upcomingAppointment['vehicle_label'] ?? null,
                        $upcomingAppointment['status_label'] ?? null,
                    ])->filter()->implode(' · '))
                    : 'Schedule a drop-off or call ahead from the phone.',
            ],
            'timeline_payload' => [
                'enabled' => $timelineItems !== [],
                'items' => $timelineItems,
            ],
        ];

        $composed = $this->layoutEngine->compose($observation, $layoutContext, $inferred);

        return [
            'customer' => [
                'id' => $customer->id,
                'name' => $customer->name,
                'first_name' => $customer->first_name,
                'last_name' => $customer->last_name,
                'phone' => $customer->phone,
                'display_phone' => $customer->display_phone,
                'email' => $customer->email,
                'customer_type' => $customer->customer_type ?: 'Retail',
            ],
            'primary_vehicle_label' => $primaryVehicleLabel,
            'observation' => $composed['observation'],
            'orientation' => $composed['orientation'],
            'blocks' => $composed['blocks'],
            'quick_actions' => $this->quickActions(
                $customer,
                $viewer,
                $canReply,
                $conversation?->id,
                $messagePreview !== [],
                $primaryRo,
                $primaryRoMoney,
                $primarySend,
            ),
            'repair_orders' => $customer->repairOrders
                ->map(function (RepairOrder $repairOrder) use ($moneyFor): array {
                    $money = $moneyFor($repairOrder);
                    $isOpen = in_array(
                        $repairOrder->status->value,
                        RepairOrderStatus::operationalQueueValues(),
                        true,
                    );

                    return array_filter([
                        'id' => $repairOrder->id,
                        'repair_order_id' => $repairOrder->repair_order_id,
                        'number_label' => (string) $repairOrder->repair_order_id,
                        'status' => $repairOrder->status->value,
                        'status_label' => $repairOrder->status->label(),
                        'vehicle_label' => $repairOrder->vehicle?->display_name ?? 'Vehicle',
                        'concern_summary' => $repairOrder->concern_summary,
                        'is_open' => $isOpen,
                        'estimate_total_label' => $money['estimate_total_label'] ?? null,
                        'balance_due_label' => $money['balance_due_label'] ?? null,
                        'balance_due_outstanding' => $money !== null
                            ? (bool) ($money['balance_due_outstanding'] ?? false)
                            : null,
                    ], fn (mixed $value): bool => $value !== null);
                })
                ->values()
                ->all(),
            'vehicles' => $customer->vehicles
                ->map(fn (Vehicle $vehicle): array => MobileIntakeVehicleProjection::summary($vehicle))
                ->values()
                ->all(),
            'timeline' => [
                'items' => $timelineItems,
                'count' => count($timelineItems),
            ],
            'poll_after_seconds' => 45,
        ];
    }

    /**
     * @param  Collection<int, RepairOrder>  $openRepairOrders
     * @return array<string, mixed>
     */
    private function defaultOrientation(
        Customer $customer,
        User $viewer,
        ?CustomerCallContextOpenRepairOrder $primaryOpen,
        Collection $openRepairOrders,
    ): array {
        $firstName = trim((string) $customer->first_name);
        $callLabel = $firstName !== '' ? "Call {$firstName}" : 'Call customer';

        $situation = 'Ready when you are';
        $nextLabel = $callLabel;
        $nextKey = 'call';

        if ($primaryOpen !== null) {
            $orientation = is_array($primaryOpen->orientation ?? null) ? $primaryOpen->orientation : [];
            $situation = (string) ($orientation['situation'] ?? $primaryOpen->workflowPostureLabel ?? $situation);

            $workflowNext = trim((string) ($primaryOpen->workflowNextAction ?? ''));
            if ($workflowNext !== '' && ! str_starts_with(strtolower($workflowNext), 'no action')) {
                $nextLabel = $workflowNext;
                $nextKey = str_contains(strtolower($workflowNext), 'call') ? 'call' : 'open_repair_order';
            }
        } elseif ($openRepairOrders->isNotEmpty()) {
            $repairOrder = $openRepairOrders->first();
            $situation = $repairOrder?->communicationPostureLabel() ?? $situation;
            $nextLabel = $repairOrder?->communicationNextAction() ?? $callLabel;
            $nextKey = str_contains(strtolower($nextLabel), 'call') ? 'call' : 'open_repair_order';
        }

        $phone = PhoneNumber::normalize($customer->phone);
        $primaryRo = $primaryOpen?->repairOrder ?? $openRepairOrders->first();

        return [
            'current_situation' => $situation,
            'next_best_action' => [
                'label' => $nextLabel,
                'key' => $nextKey,
                'enabled' => $phone !== null || $primaryRo !== null,
                'params' => array_filter([
                    'phone' => $phone,
                    'customer_id' => $customer->id,
                    'repair_order_id' => $primaryRo?->repair_order_id,
                    'dial_method' => $this->dialProjection->dialMethodFor($viewer),
                ]),
            ],
        ];
    }

    private function primaryVehicleLabel(Customer $customer, ?Vehicle $preferred): ?string
    {
        if ($preferred !== null) {
            $label = trim($preferred->display_name ?? '');

            return $label !== '' ? $label : null;
        }

        $vehicle = $customer->vehicles->first();

        if ($vehicle === null) {
            return null;
        }

        $label = trim($vehicle->display_name ?? '');

        return $label !== '' ? $label : null;
    }

    /**
     * @return array<string, mixed>
     */
    private function moneySummary(RepairOrder $repairOrder): array
    {
        $repairOrder->loadMissing(['concerns.lines']);

        return $this->estimate->summary($repairOrder);
    }

    /**
     * @param  list<array<string, mixed>>  $messagePreview
     * @param  array<string, mixed>|null  $primaryRoMoney
     * @param  array<string, mixed>|null  $primarySend
     * @return list<array<string, mixed>>
     */
    private function quickActions(
        Customer $customer,
        User $viewer,
        bool $canReply,
        ?int $conversationId,
        bool $hasMessageHistory,
        ?RepairOrder $primaryRo,
        ?array $primaryRoMoney,
        ?array $primarySend,
    ): array {
        $actions = [];
        $firstName = trim((string) $customer->first_name);
        $hasPhone = filled($customer->phone);

        if ($canReply && $hasPhone) {
            $hasHistory = $conversationId !== null || $hasMessageHistory;
            $actions[] = [
                'key' => $hasHistory ? 'reply' : 'text_customer',
                'label' => $hasHistory
                    ? ($firstName !== '' ? "Reply to {$firstName}" : 'Reply to customer')
                    : ($firstName !== '' ? "Text {$firstName}" : 'Text customer'),
                'enabled' => true,
                'params' => array_filter([
                    'conversation_id' => $conversationId,
                    'customer_id' => $customer->id,
                    'repair_order_id' => $primaryRo?->repair_order_id,
                ]),
            ];
        }

        if ($this->access->canManageAppointments($viewer)) {
            $actions[] = [
                'key' => 'schedule_appointment',
                'label' => 'Schedule appointment',
                'enabled' => true,
                'params' => [
                    'customer_id' => $customer->id,
                ],
            ];
        }

        if ($primaryRo === null || $primarySend === null || $primaryRoMoney === null) {
            return $actions;
        }

        $estimateSend = $primarySend['estimate'] ?? [];
        $paymentSend = $primarySend['payment'] ?? [];
        $inspectionSend = $primarySend['inspection'] ?? [];

        if (($primaryRoMoney['has_lines'] ?? false) && ($estimateSend['can_sms'] ?? false)) {
            $actions[] = [
                'key' => 'send_estimate',
                'label' => 'Send estimate',
                'enabled' => true,
                'params' => [
                    'repair_order_id' => $primaryRo->repair_order_id,
                ],
            ];
        } elseif (($primaryRoMoney['has_lines'] ?? false) && filled($estimateSend['sms_block_reason'] ?? null)) {
            $actions[] = [
                'key' => 'send_estimate',
                'label' => 'Send estimate',
                'enabled' => false,
                'params' => [
                    'repair_order_id' => $primaryRo->repair_order_id,
                    'block_reason' => $estimateSend['sms_block_reason'],
                ],
            ];
        }

        if (($primaryRoMoney['balance_due_outstanding'] ?? false) && ($paymentSend['can_sms'] ?? false)) {
            $actions[] = [
                'key' => 'send_payment',
                'label' => 'Send payment link',
                'enabled' => true,
                'params' => [
                    'repair_order_id' => $primaryRo->repair_order_id,
                ],
            ];
        } elseif (($primaryRoMoney['balance_due_outstanding'] ?? false) && filled($paymentSend['sms_block_reason'] ?? null)) {
            $actions[] = [
                'key' => 'send_payment',
                'label' => 'Send payment link',
                'enabled' => false,
                'params' => [
                    'repair_order_id' => $primaryRo->repair_order_id,
                    'block_reason' => $paymentSend['sms_block_reason'],
                ],
            ];
        }

        if (($inspectionSend['can_sms'] ?? false)) {
            $actions[] = [
                'key' => 'send_inspection',
                'label' => 'Send inspection',
                'enabled' => true,
                'params' => [
                    'repair_order_id' => $primaryRo->repair_order_id,
                ],
            ];
        } elseif (filled($inspectionSend['send_block_reason'] ?? $inspectionSend['sms_block_reason'] ?? null)) {
            $actions[] = [
                'key' => 'send_inspection',
                'label' => 'Send inspection',
                'enabled' => false,
                'params' => [
                    'repair_order_id' => $primaryRo->repair_order_id,
                    'block_reason' => $inspectionSend['send_block_reason'] ?? $inspectionSend['sms_block_reason'],
                ],
            ];
        }

        return $actions;
    }
}
