<?php

namespace App\Ark\Operations\Inspections;

use App\Ark\Operations\RepairOrders\RepairOrder;
use App\Ark\Runtime\Authorization\ArkCapability;
use DomainException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

final class RepairOrderInspectionTemplateAssignController
{
    public function __invoke(
        Request $request,
        RepairOrder $repairOrder,
        AssignRepairOrderInspectionTemplateAction $assign,
    ): RedirectResponse {
        abort_unless($request->user()?->can(ArkCapability::RepairOrdersManage->value), 403);

        $repairOrder->ensureOpenForEditing();
        DefaultInspectionTemplateCatalog::seedIfMissing();

        $data = $request->validate([
            'inspection_template_id' => [
                'required',
                'integer',
                Rule::exists('inspection_templates', 'id')->where(fn ($query) => $query
                    ->where('enabled', true)
                    ->whereNull('archived_at')),
            ],
            'confirm_template_change' => ['nullable', 'boolean'],
            'template_correction_reason' => ['nullable', 'string', 'max:255'],
        ]);

        $template = InspectionTemplate::query()->findOrFail($data['inspection_template_id']);
        $confirm = $request->boolean('confirm_template_change');
        $reason = isset($data['template_correction_reason'])
            ? trim((string) $data['template_correction_reason'])
            : null;

        try {
            $assign->execute(
                $repairOrder,
                $template,
                confirmCorrection: $confirm,
                correctionReason: $reason !== '' ? $reason : null,
            );
        } catch (DomainException $e) {
            return back()->withErrors(['inspection_template_id' => $e->getMessage()]);
        }

        $status = $confirm
            ? 'Inspection changed to '.$template->name.'. Previous points kept as history.'
            : 'Inspection for this visit: '.$template->name;

        return back()->with('status', $status);
    }
}
