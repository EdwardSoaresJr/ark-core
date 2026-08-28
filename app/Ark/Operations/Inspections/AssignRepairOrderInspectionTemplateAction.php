<?php

namespace App\Ark\Operations\Inspections;

use App\Ark\Operations\RepairOrders\RepairOrder;
use DomainException;
use Illuminate\Support\Facades\DB;

/**
 * Advisor selects required template for the visit (Standard default / PPI replaces).
 * One Inspection per RO. Captured evidence is never destroyed — wrong-template
 * correction supersedes prior points as history and seeds the new checklist.
 */
final class AssignRepairOrderInspectionTemplateAction
{
    public const REASON_WRONG_TEMPLATE = 'wrong_template_selected';

    public function execute(
        RepairOrder $repairOrder,
        InspectionTemplate $template,
        bool $confirmCorrection = false,
        ?string $correctionReason = null,
    ): RepairOrder {
        if (! $template->enabled || $template->isArchived()) {
            throw new DomainException('That inspection template is not available.');
        }

        return DB::transaction(function () use ($repairOrder, $template, $confirmCorrection, $correctionReason): RepairOrder {
            $inspection = Inspection::query()
                ->where('repair_order_id', $repairOrder->id)
                ->first();

            $sameRequired = (int) ($repairOrder->required_inspection_template_id ?? 0) === (int) $template->id;
            $sameApplied = $inspection instanceof Inspection
                && (int) ($inspection->inspection_template_id ?? 0) === (int) $template->id;

            if ($sameRequired && ($sameApplied || ! $inspection instanceof Inspection)) {
                return $repairOrder->fresh(['requiredInspectionTemplate']);
            }

            if ($inspection instanceof Inspection
                && (int) ($inspection->inspection_template_id ?? 0) !== (int) $template->id
                && $inspection->hasCapturedEvidence()) {
                if (! $confirmCorrection) {
                    $inspection->loadMissing('template');
                    $from = $inspection->template?->name
                        ?? ResolveRequiredInspectionTemplate::for($repairOrder)?->name
                        ?? 'the current template';

                    throw new DomainException(
                        'This visit already has inspection work on '.$from
                        .'. Confirm changing the template — recorded points stay as history.',
                    );
                }

                $reason = trim((string) $correctionReason);
                if ($reason === '') {
                    $reason = self::REASON_WRONG_TEMPLATE;
                }

                if (strlen($reason) > 255) {
                    throw new DomainException('Correction reason is too long.');
                }

                $now = now();
                $inspection->items()
                    ->whereNull('superseded_at')
                    ->update(['superseded_at' => $now, 'updated_at' => $now]);

                $inspection->forceFill([
                    'previous_inspection_template_id' => $inspection->inspection_template_id,
                    'template_correction_reason' => $reason,
                    'template_corrected_at' => $now,
                    'inspection_template_id' => null,
                    'rear_axle_brake_type' => null,
                ])->save();
            }

            $repairOrder->forceFill([
                'required_inspection_template_id' => $template->id,
            ])->save();

            if (! $inspection instanceof Inspection) {
                return $repairOrder->fresh(['requiredInspectionTemplate']);
            }

            if ((int) ($inspection->fresh()->inspection_template_id ?? 0) === (int) $template->id) {
                return $repairOrder->fresh(['requiredInspectionTemplate']);
            }

            // Empty active checklist only — never delete superseded history.
            $inspection->items()
                ->whereNull('superseded_at')
                ->each(function (InspectionItem $item): void {
                    $item->measurements()->delete();
                    $item->photos()->delete();
                    $item->delete();
                });

            $inspection->forceFill([
                'inspection_template_id' => null,
                'rear_axle_brake_type' => null,
            ])->save();

            app(ApplyInspectionTemplateAction::class)->execute(
                $repairOrder->fresh(),
                $inspection->fresh(),
                $template,
            );

            return $repairOrder->fresh(['requiredInspectionTemplate']);
        });
    }
}
