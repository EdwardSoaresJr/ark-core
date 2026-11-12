<?php

namespace App\Ark\Operations\RepairOrders;

use App\Ark\Operations\Documents\Document;
use App\Ark\Operations\Inspections\InspectionCaptureLinks;
use App\Ark\Operations\Inspections\InspectionCoverageProjection;
use App\Ark\Runtime\Authorization\ArkCapability;
use App\Models\User;

/**
 * Disposable footer projection - contextual next actions for the Repair Order.
 *
 * Presentation is permanent. Authoring is temporary. This footer is not a toolbar.
 *
 * Rebuild from RO posture anytime. Delete when unused.
 */
final readonly class RepairOrderFooterProjection
{
    /**
     * @param  list<RepairOrderFooterAction>  $present
     * @param  list<RepairOrderFooterAction>  $utilities
     */
    public function __construct(
        public RepairOrderFooterAction $workflow,
        public array $present,
        public array $utilities,
    ) {}

    public static function for(RepairOrder $repairOrder, ?User $actor = null): self
    {
        $repairOrder->loadMissing(['customer', 'vehicle', 'lines']);

        $canManage = $actor?->can(ArkCapability::RepairOrdersManage->value) ?? false;
        $canView = $actor?->can(ArkCapability::RepairOrdersView->value) ?? false;
        $canRecordFindings = InspectionCaptureLinks::canRecord($actor, $repairOrder);

        return new self(
            workflow: self::workflowAction($repairOrder, $canManage, $canView, $canRecordFindings, $actor),
            present: self::presentActions($repairOrder, $canManage, $canView),
            utilities: self::utilityActions($repairOrder, $canView, $actor),
        );
    }

    private static function workflowAction(
        RepairOrder $repairOrder,
        bool $canManage,
        bool $canView,
        bool $canRecordFindings,
        ?User $actor,
    ): RepairOrderFooterAction {
        if ($repairOrder->isTerminal()) {
            return $canView
                ? RepairOrderFooterAction::link(
                    key: 'view_estimate_pdf',
                    label: 'Estimate PDF',
                    href: route('operations.repair-orders.estimate.pdf', $repairOrder),
                    opensInNewTab: true,
                    title: 'Open the customer estimate PDF',
                )
                : RepairOrderFooterAction::none();
        }

        if ($canManage) {
            return RepairOrderFooterAction::modal(
                key: 'add_work',
                label: '+ Add Work',
                title: 'Add concern, oil service, or testing package',
            );
        }

        if ($canRecordFindings) {
            $coverage = InspectionCoverageProjection::for($repairOrder, $actor);

            return RepairOrderFooterAction::link(
                key: 'open_inspection',
                label: (string) $coverage['cta_label'],
                href: $coverage['capture_url'],
                opensInNewTab: true,
                title: 'Continue the vehicle inspection',
            );
        }

        return RepairOrderFooterAction::none();
    }

    /**
     * Present group - customer-facing projections of the Repair Order only.
     *
     * Customer Display = kiosk-style front-counter monitor for this RO.
     * Tablet = Flutter customer presentation + signature (when that surface exists).
     *
     * Do not put InspectionCaptureLinks::tabletUrl here - that is the technician
     * bay inspection walk, a different user and purpose.
     *
     * @return list<RepairOrderFooterAction>
     */
    private static function presentActions(RepairOrder $repairOrder, bool $canManage, bool $canView): array
    {
        if (! $canView) {
            return [];
        }

        $actions = [];
        $paperworkCount = Document::query()
            ->where('repair_order_id', $repairOrder->id)
            ->whereNull('deleted_at')
            ->count();

        // Paperwork sits with Present / PRINT - not buried in the estimate body.
        if ($canManage && ! $repairOrder->isTerminal()) {
            $actions[] = RepairOrderFooterAction::modal(
                key: 'paperwork',
                label: $paperworkCount > 0 ? 'Paperwork ('.$paperworkCount.')' : '+ Add Document',
                title: 'Scan, upload, or attach paperwork for this visit',
                modalTask: 'document',
            );
        } elseif ($paperworkCount > 0) {
            $actions[] = RepairOrderFooterAction::modal(
                key: 'paperwork',
                label: 'Paperwork ('.$paperworkCount.')',
                title: 'Open paperwork for this visit',
                modalTask: 'document',
            );
        }

        if ($canManage && $repairOrder->lines->isNotEmpty() && ! $repairOrder->isTerminal()) {
            $actions[] = RepairOrderFooterAction::link(
                key: 'customer_display',
                label: 'Customer Display',
                href: route('operations.repair-orders.customer-display', $repairOrder),
                opensInNewTab: true,
                title: 'Present this estimate on the front-counter customer display',
            );
        }

        // Tablet intentionally omitted until a Flutter customer-presentation /
        // signature deep link exists. Hiding is correct; substituting the
        // inspection tablet is not.
        //
        // Earn gate: customer tablet URL or companion deep link ships → add
        // RepairOrderFooterAction::link(key: 'tablet', label: 'Tablet', …).

        return $actions;
    }

    /**
     * Print utilities — same authoritative list as the header Print menu.
     *
     * @param  array<string, mixed>|null  $financial
     * @return list<RepairOrderFooterAction>
     */
    private static function utilityActions(RepairOrder $repairOrder, bool $canView, ?User $actor = null, ?array $financial = null): array
    {
        if (! $canView) {
            return [];
        }

        return array_map(
            static function (array $item): RepairOrderFooterAction {
                return RepairOrderFooterAction::link(
                    key: $item['key'],
                    label: $item['label'],
                    href: (string) ($item['href'] ?? '#'),
                    opensInNewTab: (bool) ($item['opens_in_new_tab'] ?? false),
                    title: $item['blocked_reason'] ?? null,
                    isPrint: (bool) ($item['is_print'] ?? false),
                    printDocument: $item['print_document'] ?? null,
                    blockedReason: $item['blocked_reason'] ?? null,
                );
            },
            RepairOrderPrintMenuProjection::items($repairOrder, $financial, $actor),
        );
    }
}
