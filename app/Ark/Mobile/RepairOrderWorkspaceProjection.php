<?php

namespace App\Ark\Mobile;

use App\Ark\Operations\Conversations\Conversation;
use App\Ark\Operations\Conversations\ConversationLink;
use App\Ark\Operations\Inspections\ApplyInspectionTemplateAction;
use App\Ark\Operations\Inspections\DefaultInspectionTemplateCatalog;
use App\Ark\Operations\Inspections\EnsureInspectionAction;
use App\Ark\Operations\Inspections\InspectionFindingCardProjection;
use App\Ark\Operations\RepairOrders\RepairOrder;
use App\Ark\Operations\RepairOrders\RepairOrderLifecycleSelectProjection;
use App\Ark\Operations\RepairOrders\RepairOrderLostReason;
use App\Ark\Operations\RepairOrders\RepairOrderVisitMode;
use App\Ark\Operations\RepairOrders\Status\RepairOrderStatusCatalog;
use App\Ark\Operations\Staff\SoloShopOperations;
use App\Ark\Runtime\Authorization\ArkCapability;
use App\Ark\Runtime\Authorization\ArkRole;
use App\Models\User;

/**
 * Backend-driven RO workspace - sections and command bar per role and capability.
 *
 * Authority → projection → workspace configuration. Flutter renders; it does not decide tabs.
 */
final class RepairOrderWorkspaceProjection
{
    public function __construct(
        private readonly MobileStaffAccess $access,
        private readonly MobileUserPresenter $userPresenter,
        private readonly RepairOrderWorkspaceIntelligenceProjection $intelligence,
        private readonly EnsureInspectionAction $ensureInspection,
        private readonly ApplyInspectionTemplateAction $applyInspectionTemplate,
        private readonly MobileEstimateProjection $estimate,
        private readonly MobilePaymentCaptureProjection $paymentCapture,
        private readonly MobileManualPaymentProjection $manualPayment,
        private readonly MobilePaymentCaptureDepositProjection $paymentCaptureDeposit,
        private readonly MobileManualDepositProjection $manualDeposit,
        private readonly MobileManualRefundProjection $manualRefund,
        private readonly MobileLedgerProjection $ledger,
        private readonly RepairOrderStatusCatalog $statusCatalog,
        private readonly SoloShopOperations $soloShop,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function forRepairOrder(RepairOrder $repairOrder, User $viewer): array
    {
        $repairOrder->loadMissing(['customer', 'vehicle', 'assignedTechnician:id,name']);

        $profile = $this->workspaceProfile($viewer);
        $showMoney = $profile !== 'technician';
        $estimateSummary = $showMoney ? $this->estimate->summary($repairOrder) : null;
        $sections = $this->sections($repairOrder, $viewer, $profile);
        $hasConversation = $this->linkedConversationId($repairOrder) !== null;
        $this->ensureInspectionReady($repairOrder, $viewer);
        $intelligence = $this->intelligence->forRepairOrder($repairOrder, $viewer, $profile);
        $next = $intelligence['next'] ?? null;
        $technicianAssignment = $this->technicianAssignmentControl($repairOrder, $viewer, $profile);
        $canManageConcerns = $profile !== 'technician'
            && $this->access->canSetConcernDisposition($viewer, $repairOrder)
            && ! $repairOrder->isTerminal();
        $canEditEstimate = $canManageConcerns;

        $paymentCapture = $showMoney
            ? $this->paymentCapture->control($repairOrder, $viewer, $profile)
            : null;

        $manualPayment = $showMoney
            ? $this->manualPayment->control($repairOrder, $viewer, $profile)
            : null;

        $paymentCaptureDeposit = $showMoney
            ? $this->paymentCaptureDeposit->control($repairOrder, $viewer, $profile)
            : null;

        $manualDeposit = $showMoney
            ? $this->manualDeposit->control($repairOrder, $viewer, $profile)
            : null;

        $manualRefund = $showMoney
            ? $this->manualRefund->control($repairOrder, $viewer, $profile)
            : null;

        $ledger = $showMoney
            ? $this->ledger->control($repairOrder, $viewer, $profile)
            : null;

        return [
            'profile' => $profile,
            'question' => $this->workspaceQuestion($profile),
            'sections' => $sections,
            'default_section' => $this->defaultSection($sections, $profile, $next),
            'command_bar' => $this->commandBar(
                $repairOrder,
                $viewer,
                $profile,
                $hasConversation,
                $next,
                $estimateSummary,
                $technicianAssignment,
                $canManageConcerns,
                $canEditEstimate,
                $paymentCapture,
                $manualPayment,
                $paymentCaptureDeposit,
                $manualDeposit,
                $manualRefund,
                $ledger,
            ),
            'lifecycle' => $this->lifecycleControl($repairOrder, $viewer, $profile),
            'technician_assignment' => $technicianAssignment,
            'payment_capture' => $paymentCapture,
            'payment_capture_deposit' => $paymentCaptureDeposit,
            'square_payment' => $paymentCapture,
            'manual_payment' => $manualPayment,
            'square_deposit' => $paymentCaptureDeposit,
            'manual_deposit' => $manualDeposit,
            'manual_refund' => $manualRefund,
            'ledger' => $ledger,
            'header' => $this->header($repairOrder, $intelligence['health'] ?? [], $estimateSummary),
            'health' => $intelligence['health'] ?? [],
            'next' => $next,
            'recommendations_queue' => $intelligence['recommendations_queue'] ?? [],
            'alerts' => $intelligence['alerts'] ?? [],
            'timeline' => $intelligence['timeline'] ?? [],
            'confidence' => $intelligence['confidence'] ?? [],
            'conversation_id' => $hasConversation ? $this->linkedConversationId($repairOrder) : null,
            'inspection' => [
                'mode' => 'flow',
                'entry' => 'next_item',
                'initial_item_id' => is_array($next)
                    ? ($next['action']['item_id'] ?? null)
                    : null,
            ],
            'navigation' => [
                'style' => 'workspace',
                'exit_label' => 'My Work',
            ],
        ];
    }

    private function workspaceProfile(User $viewer): string
    {
        return $this->userPresenter->repairOrderWorkspaceProfile($viewer);
    }

    private function workspaceQuestion(string $profile): string
    {
        return match ($profile) {
            'technician' => 'What do I inspect next?',
            'advisor' => 'What do I need to communicate?',
            'staff' => 'What is happening on this repair?',
            default => 'What is happening?',
        };
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function sections(RepairOrder $repairOrder, User $viewer, string $profile): array
    {
        $sections = [];

        $sections[] = $this->section('overview', 'Overview', 'Summary and approved work');

        if ($viewer->can(ArkCapability::RepairOrdersView->value)) {
            $sections[] = $this->section('concerns', 'Concerns', 'Customer concerns and production');
        }

        // Estimate lines and totals are for advisors/owners, not technicians.
        if ($profile !== 'technician' && $viewer->can(ArkCapability::RepairOrdersView->value)) {
            $sections[] = $this->section('estimate', 'Estimate', 'Line items, pricing, and totals');
        }

        if ($this->access->canRecordFinding($viewer, $repairOrder)
            || $repairOrder->inspection !== null) {
            $sections[] = $this->section('inspection', 'Inspection', 'Check the vehicle item by item');
        }

        if ($this->canShowConversations($viewer, $repairOrder)) {
            $sections[] = $this->section('conversations', 'Conversations', 'Customer activity on this RO');
        }

        $sections[] = $this->section('history', 'History', 'Findings and activity');

        return $sections;
    }

    /**
     * @param  list<array<string, mixed>>  $sections
     * @param  array<string, mixed>|null  $next
     */
    private function defaultSection(array $sections, string $profile, ?array $next): string
    {
        $keys = collect($sections)
            ->filter(fn (array $section): bool => ($section['enabled'] ?? true) === true)
            ->pluck('key')
            ->all();

        if ($profile === 'technician' && in_array('inspection', $keys, true)) {
            return 'inspection';
        }

        if (in_array('overview', $keys, true)) {
            return 'overview';
        }

        return $keys[0] ?? 'overview';
    }

    /**
     * @return array<string, mixed>
     */
    private function section(string $key, string $label, string $description, bool $enabled = true): array
    {
        return [
            'key' => $key,
            'label' => $label,
            'description' => $description,
            'enabled' => $enabled,
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    /**
     * @param  array<string, mixed>|null  $next
     * @return list<array<string, mixed>>
     */
    private function commandBar(
        RepairOrder $repairOrder,
        User $viewer,
        string $profile,
        bool $hasConversation,
        ?array $next,
        ?array $estimateSummary,
        ?array $technicianAssignment,
        bool $canManageConcerns,
        bool $canEditEstimate,
        ?array $paymentCapture,
        ?array $manualPayment,
        ?array $paymentCaptureDeposit,
        ?array $manualDeposit,
        ?array $manualRefund,
        ?array $ledger,
    ): array {
        $canFindings = $this->access->canRecordFinding($viewer, $repairOrder);
        $canComms = $this->canShowConversations($viewer, $repairOrder);
        $nextItemId = is_array($next) ? ($next['action']['item_id'] ?? null) : null;

        $photo = $this->command('photo', 'Photo', 'capture', $canFindings, [
            'target' => 'inspection_flow',
            'attach' => 'photo',
            'item_id' => $nextItemId,
        ]);
        $finding = $this->command('finding', 'Finding', 'capture', $canFindings, [
            'target' => 'inspection_flow',
            'item_id' => $nextItemId,
        ]);
        $completeItem = $this->command('complete_item', 'Complete Item', 'action', $canFindings, [
            'target' => 'inspection_flow',
            'action' => 'save_and_next',
            'item_id' => $nextItemId,
        ]);
        $conversation = $this->command(
            'conversation',
            $hasConversation ? 'Message' : 'Text customer',
            'navigate',
            $canComms,
            ['target' => 'section', 'section' => 'conversations'],
        );

        // Technicians get production verbs; advisors/owners get the money verbs
        // that otherwise force a desktop trip. Both keep conversation.
        if ($profile === 'technician') {
            return array_values(array_filter([
                $photo,
                $finding,
                $completeItem,
                $canComms ? $conversation : null,
            ]));
        }

        $hasLines = (bool) ($estimateSummary['has_lines'] ?? false);
        $balanceOutstanding = (bool) ($estimateSummary['balance_due_outstanding'] ?? false);
        $hasInspectionFindings = InspectionFindingCardProjection::recordedCountForRepairOrder($repairOrder) > 0;
        $canPreviewInspection = $hasInspectionFindings
            && $viewer->can(ArkCapability::RepairOrdersManage->value);

        $assignTechnician = null;
        if ($technicianAssignment !== null && ($technicianAssignment['can_update'] ?? false)) {
            $hasTechnicians = count($technicianAssignment['technicians'] ?? []) > 0;
            $hasAssignment = filled($technicianAssignment['assigned_technician_id'] ?? null);
            $assignTechnician = $this->command(
                'assign_technician',
                $hasAssignment ? 'Reassign tech' : 'Assign tech',
                'action',
                $hasTechnicians || $hasAssignment,
                ['action' => 'assign_technician'],
            );
        }

        return array_values(array_filter([
            $canComms ? $conversation : null,
            $assignTechnician,
            $this->command('add_concern', 'Add concern', 'action', $canManageConcerns, [
                'action' => 'add_concern',
            ]),
            $this->command('add_estimate_line', 'Add line', 'action', $canEditEstimate, [
                'action' => 'add_estimate_line',
            ]),
            $this->command('send_estimate', 'Send estimate', 'action', $hasLines, [
                'action' => 'send_estimate',
            ]),
            $this->command('send_inspection', 'Send inspection', 'action', $hasInspectionFindings, [
                'action' => 'send_inspection',
            ]),
            $this->command('preview_inspection', 'Preview inspection', 'action', $canPreviewInspection, [
                'action' => 'preview_inspection',
            ]),
            ($paymentCaptureDeposit['can_charge_terminal'] ?? false)
                ? $this->command('charge_deposit_terminal', 'Collect deposit', 'action', true, [
                    'action' => 'charge_deposit_terminal',
                ])
                : null,
            ($manualDeposit['can_record'] ?? false)
                ? $this->command('record_deposit', 'Record deposit', 'action', true, [
                    'action' => 'record_deposit',
                ])
                : null,
            $this->command('send_payment', 'Send payment', 'action', $balanceOutstanding, [
                'action' => 'send_payment',
            ]),
            ($paymentCapture['can_charge_terminal'] ?? false)
                ? $this->command('charge_terminal', 'Charge reader', 'action', true, [
                    'action' => 'charge_terminal',
                ])
                : null,
            ($manualPayment['can_record'] ?? false)
                ? $this->command('record_payment', 'Record payment', 'action', true, [
                    'action' => 'record_payment',
                ])
                : null,
            ($manualRefund['can_record'] ?? false)
                ? $this->command('record_refund', 'Record refund', 'action', true, [
                    'action' => 'record_refund',
                ])
                : null,
            ($ledger['entries'] ?? []) !== []
                ? $this->command('payment_history', 'Payments', 'action', true, [
                    'action' => 'payment_history',
                ])
                : null,
            $canFindings ? $photo : null,
            $canFindings ? $finding : null,
        ]));
    }

    /**
     * @param  array<string, mixed>  $params
     * @return array<string, mixed>
     */
    private function command(
        string $key,
        string $label,
        string $type,
        bool $enabled,
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
     * Advisor/owner technician assignment - pick who performs the work from the
     * RO command bar. Same assignable list and validation as desktop intake and
     * the Work list; technicians never see this control.
     *
     * @return array<string, mixed>|null
     */
    private function technicianAssignmentControl(RepairOrder $repairOrder, User $viewer, string $profile): ?array
    {
        if ($profile === 'technician' || ! $this->access->canPerformIntake($viewer)) {
            return null;
        }

        $technicians = $this->soloShop->assignableTechnicians();

        return [
            'can_update' => ! $repairOrder->isTerminal(),
            'assigned_technician_id' => $repairOrder->assigned_technician_id,
            'assigned_technician' => $repairOrder->assignedTechnician?->name,
            'requires_assignment' => $this->soloShop->requiresTechnicianAssignment(),
            'technicians' => $technicians
                ->map(fn (User $user): array => [
                    'id' => $user->id,
                    'name' => $user->name,
                ])
                ->values()
                ->all(),
        ];
    }

    /**
     * Advisor/owner RO lifecycle control - move status forward/back and close
     * the repair order from the phone. Built from the same lifecycle authority
     * (allowed transitions, blocking reasons, close variants) the desktop
     * toolbar uses, so the phone never offers a move the desktop would reject.
     * Returns null for technicians (lifecycle is not their authority).
     *
     * @return array<string, mixed>|null
     */
    private function lifecycleControl(RepairOrder $repairOrder, User $viewer, string $profile): ?array
    {
        if ($profile === 'technician' || ! $this->access->canChangeRepairOrderLifecycle($viewer, $repairOrder)) {
            return null;
        }

        $isTerminal = $repairOrder->isTerminal();

        $statusSlugs = collect($this->statusCatalog->allowedTargetSlugs($repairOrder->status->value, $viewer));
        $closeVariants = $this->statusCatalog->allowedCloseVariants($repairOrder->status, $viewer);

        $select = RepairOrderLifecycleSelectProjection::forRepairOrder(
            $repairOrder,
            $statusSlugs,
            $closeVariants,
            $viewer,
        );

        $options = [];

        foreach ($select->statusOptions as $option) {
            $options[] = [
                'value' => $option['value'],
                'label' => $option['label'],
                'blocked_reason' => $option['blockedReason'],
                'disabled' => $option['disabled'],
                'kind' => 'status',
            ];
        }

        foreach ($select->closeOptions as $option) {
            $options[] = [
                'value' => $option['value'],
                'label' => $option['label'],
                'blocked_reason' => $option['blockedReason'],
                'disabled' => $option['blockedReason'] !== null,
                'kind' => 'close',
            ];
        }

        if ($select->showLostCloseOption) {
            $options[] = [
                'value' => 'closed:lost',
                'label' => 'Closed - Lost',
                'blocked_reason' => null,
                'disabled' => false,
                'kind' => 'close_lost',
            ];
        }

        return [
            'can_update' => ! $isTerminal && $options !== [],
            'is_terminal' => $isTerminal,
            'current' => [
                'value' => $repairOrder->status->value,
                'label' => $repairOrder->statusDisplayLabel(),
                'tone' => MobileRepairOrderStatusTone::forStatus($repairOrder->status),
            ],
            'options' => $options,
            'lost_reasons' => array_map(
                static fn (RepairOrderLostReason $reason): array => [
                    'value' => $reason->value,
                    'label' => $reason->label(),
                    'requires_note' => $reason->requiresNote(),
                ],
                RepairOrderLostReason::cases(),
            ),
        ];
    }

    /**
     * @param  array<string, mixed>  $health
     * @return array<string, mixed>
     */
    /**
     * @param  array<string, mixed>  $health
     * @param  array<string, mixed>|null  $estimateSummary
     * @return array<string, mixed>
     */
    private function header(RepairOrder $repairOrder, array $health, ?array $estimateSummary): array
    {
        $vehicle = $repairOrder->vehicle;
        $visitMode = RepairOrderVisitMode::fromRepairOrder($repairOrder);
        $inspection = $health['inspection'] ?? [];

        return [
            'estimate_total_label' => $estimateSummary['estimate_total_label'] ?? null,
            'estimate_total_cents' => $estimateSummary['estimate_total_cents'] ?? null,
            'balance_due_label' => $estimateSummary['balance_due_label'] ?? null,
            'balance_due_outstanding' => $estimateSummary['balance_due_outstanding'] ?? false,
            'repair_order_id' => $repairOrder->repair_order_id,
            'number_label' => '#'.$repairOrder->repair_order_id,
            'customer_id' => $repairOrder->customer?->id,
            'customer_name' => $repairOrder->customer?->name,
            'customer_display_phone' => $repairOrder->customer?->display_phone,
            'vehicle_label' => $vehicle?->display_name ?? 'Vehicle',
            'vehicle_id' => $vehicle?->id,
            'vehicle_plate' => $vehicle?->plate,
            'vehicle_vin' => $vehicle?->vin,
            'status_label' => $repairOrder->statusDisplayLabel(),
            'status_tone' => MobileRepairOrderStatusTone::forStatus($repairOrder->status),
            'concern_summary' => $repairOrder->concern_summary,
            'visit_mode_label' => $visitMode?->label(),
            'vehicle_location_label' => match ($visitMode) {
                RepairOrderVisitMode::WaitingHere => 'Vehicle in shop',
                RepairOrderVisitMode::DropOff => 'Drop off',
                RepairOrderVisitMode::NeedsShuttle => 'Needs shuttle',
                RepairOrderVisitMode::TowIncoming => 'Tow incoming',
                default => null,
            },
            'assigned_technician' => $repairOrder->assignedTechnician?->name,
            'concern_count' => $health['concern_count'] ?? $repairOrder->concerns->count(),
            'inspection_total' => $inspection['total'] ?? 0,
            'inspection_complete' => $inspection['complete'] ?? 0,
            'inspection_remaining' => $inspection['remaining'] ?? 0,
            'inspection_progress_fraction' => $inspection['progress_fraction'] ?? 0.0,
            'inspection_next_label' => $inspection['next_item']['label'] ?? null,
            'recommendations_ready_count' => $health['recommendations_ready_count'] ?? 0,
            'customer_posture_label' => $health['customer_posture_label'] ?? null,
            'last_activity_label' => $health['last_activity']['label'] ?? null,
        ];
    }

    private function canShowConversations(User $viewer, RepairOrder $repairOrder): bool
    {
        if ($this->linkedConversationId($repairOrder) === null) {
            return $this->access->canAccessShopCommunications($viewer);
        }

        return $this->access->canAccessShopCommunications($viewer)
            || ($viewer->hasRole(ArkRole::Technician->value)
                && $this->access->canViewRepairOrder($viewer, $repairOrder));
    }

    private function linkedConversationId(RepairOrder $repairOrder): ?int
    {
        $conversationId = ConversationLink::query()
            ->where('linkable_type', $repairOrder->getMorphClass())
            ->where('linkable_id', $repairOrder->id)
            ->value('conversation_id');

        if ($conversationId === null) {
            return null;
        }

        return Conversation::query()->whereKey($conversationId)->exists()
            ? (int) $conversationId
            : null;
    }

    private function ensureInspectionReady(RepairOrder $repairOrder, User $viewer): void
    {
        if (! $this->access->canRecordFinding($viewer, $repairOrder)) {
            return;
        }

        DefaultInspectionTemplateCatalog::seedIfMissing();

        $inspection = $this->ensureInspection->execute($repairOrder, $viewer);

        $hasChecklistItems = $inspection->items()
            ->whereNotNull('inspection_template_item_id')
            ->exists();

        if (! $hasChecklistItems) {
            $this->applyInspectionTemplate->execute($repairOrder, $inspection, actor: $viewer);
        }

        $repairOrder->load('inspection.items.measurements', 'inspection.items.photos');
    }
}
