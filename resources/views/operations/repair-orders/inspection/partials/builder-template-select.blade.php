@php
    use App\Ark\Operations\Inspections\AssignRepairOrderInspectionTemplateAction;
    use App\Ark\Operations\Inspections\DefaultInspectionTemplateCatalog;
    use App\Ark\Operations\Inspections\InspectionCoverageProjection;
    use App\Ark\Operations\Inspections\InspectionTemplateSlugs;
    use App\Ark\Operations\Inspections\ResolveRequiredInspectionTemplate;

    $standard = DefaultInspectionTemplateCatalog::standardTemplate();
    $ppi = DefaultInspectionTemplateCatalog::ppiTemplate();
    $required = ResolveRequiredInspectionTemplate::for($repairOrder);
    $isPpi = $required?->slug === InspectionTemplateSlugs::PPI;
    $coverage = $inspectionCoverage ?? InspectionCoverageProjection::for($repairOrder, auth()->user());
    $hasEvidence = (bool) ($coverage['has_captured_evidence'] ?? false);
    $retainedCount = (int) ($coverage['retained_history_count'] ?? 0);
    $previousTemplateName = $coverage['previous_template_name'] ?? null;
    $currentTemplateId = $required?->id;
    $currentTemplateName = $required?->name ?? ($isPpi ? 'Pre-Purchase' : 'Standard');
@endphp

@if ($standard && $ppi)
    <div
        style="min-width: 0"
        data-inspection-template-select
        x-data="{
            currentId: {{ (int) $currentTemplateId }},
            pendingId: null,
            hasEvidence: {{ $hasEvidence ? 'true' : 'false' }},
            confirmOpen: false,
            reason: @js(AssignRepairOrderInspectionTemplateAction::REASON_WRONG_TEMPLATE),
            select(id) {
                if (Number(id) === Number(this.currentId)) {
                    return;
                }
                if (this.hasEvidence) {
                    this.pendingId = Number(id);
                    this.confirmOpen = true;
                    return;
                }
                this.$refs.quickTemplateId.value = id;
                this.$refs.assignForm.requestSubmit();
            },
            cancel() {
                this.confirmOpen = false;
                this.pendingId = null;
                this.$el.querySelectorAll('input[name=inspection_template_choice]').forEach((radio) => {
                    radio.checked = Number(radio.value) === Number(this.currentId);
                });
            }
        }"
    >
        <form
            method="post"
            action="{{ route('operations.repair-orders.inspection.template.assign', $repairOrder) }}"
            x-ref="assignForm"
        >
            @csrf
            <input type="hidden" name="inspection_template_id" x-ref="quickTemplateId" value="{{ $currentTemplateId }}">
            <input type="hidden" name="confirm_template_change" :value="confirmOpen ? '1' : '0'">
            <input type="hidden" name="template_correction_reason" :value="reason">

            <div class="d-flex flex-wrap gap-3">
                <label class="form-check form-check-inline mb-0" title="Included every vehicle">
                    <input
                        type="radio"
                        name="inspection_template_choice"
                        value="{{ $standard->id }}"
                        class="form-check-input"
                        @checked(! $isPpi)
                        @change="select({{ (int) $standard->id }})"
                    >
                    <span class="form-check-label">Standard</span>
                </label>
                <label class="form-check form-check-inline mb-0" title="Sold / requested this visit - replaces Standard">
                    <input
                        type="radio"
                        name="inspection_template_choice"
                        value="{{ $ppi->id }}"
                        class="form-check-input"
                        @checked($isPpi)
                        @change="select({{ (int) $ppi->id }})"
                    >
                    <span class="form-check-label">Pre-Purchase</span>
                </label>
            </div>

            <div x-show="confirmOpen" x-cloak class="alert alert-warning mt-2 mb-0" role="alert" data-inspection-template-confirm>
                <h4 class="alert-title">Change inspection from {{ $currentTemplateName }}?</h4>
                <div class="text-secondary">Recorded points stay on this repair order as history. The new checklist starts fresh.</div>
                <label class="form-label mt-2" for="inspection-template-reason-{{ $repairOrder->id }}">Reason</label>
                <select id="inspection-template-reason-{{ $repairOrder->id }}" x-model="reason" class="form-select form-select-sm" style="max-width: 24rem">
                    <option value="{{ AssignRepairOrderInspectionTemplateAction::REASON_WRONG_TEMPLATE }}">Wrong inspection chosen</option>
                </select>
                <div class="btn-list mt-2">
                    <button
                        type="submit"
                        class="btn btn-sm btn-primary"
                        @click=" $refs.quickTemplateId.value = pendingId "
                    >Change template</button>
                    <button
                        type="button"
                        class="btn btn-sm"
                        @click="cancel()"
                    >Keep {{ $currentTemplateName }}</button>
                </div>
            </div>
        </form>

        @if ($retainedCount > 0)
            <p class="small text-secondary mt-1 mb-0" data-inspection-retained-history>
                {{ $retainedCount }} {{ $retainedCount === 1 ? 'point' : 'points' }} kept as history
                @if ($previousTemplateName)
                    from {{ $previousTemplateName }}
                @endif
            </p>
        @endif

        @error('inspection_template_id')
            <div class="invalid-feedback d-block">{{ $message }}</div>
        @enderror
    </div>
@endif
