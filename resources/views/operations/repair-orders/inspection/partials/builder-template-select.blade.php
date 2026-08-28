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
        class="min-w-0"
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
            class="ops-inspection-entry__template-form"
            x-ref="assignForm"
        >
            @csrf
            <input type="hidden" name="inspection_template_id" x-ref="quickTemplateId" value="{{ $currentTemplateId }}">
            <input type="hidden" name="confirm_template_change" :value="confirmOpen ? '1' : '0'">
            <input type="hidden" name="template_correction_reason" :value="reason">

            <label class="ops-inspection-entry__radio" title="Included every vehicle">
                <input
                    type="radio"
                    name="inspection_template_choice"
                    value="{{ $standard->id }}"
                    class="ops-inspection-entry__radio-input"
                    @checked(! $isPpi)
                    @change="select({{ (int) $standard->id }})"
                >
                <span>Standard</span>
            </label>
            <label class="ops-inspection-entry__radio" title="Sold / requested this visit — replaces Standard">
                <input
                    type="radio"
                    name="inspection_template_choice"
                    value="{{ $ppi->id }}"
                    class="ops-inspection-entry__radio-input"
                    @checked($isPpi)
                    @change="select({{ (int) $ppi->id }})"
                >
                <span>Pre-Purchase</span>
            </label>

            <div
                x-show="confirmOpen"
                x-cloak
                class="basis-full mt-1 max-w-xl rounded-sm border border-amber-300 bg-amber-50 px-3 py-2 text-xs text-amber-950"
                data-inspection-template-confirm
            >
                <p class="font-semibold">
                    Change inspection from {{ $currentTemplateName }}?
                </p>
                <p class="mt-1 font-medium text-amber-900/90">
                    Recorded points stay on this repair order as history. The new checklist starts fresh.
                </p>
                <label class="mt-2 block font-semibold text-amber-950">
                    Reason
                    <select x-model="reason" class="mt-1 block w-full max-w-sm rounded-sm border border-amber-300 bg-white px-2 py-1 text-xs font-semibold text-slate-900">
                        <option value="{{ AssignRepairOrderInspectionTemplateAction::REASON_WRONG_TEMPLATE }}">Wrong inspection chosen</option>
                    </select>
                </label>
                <div class="mt-2 flex flex-wrap gap-2">
                    <button
                        type="submit"
                        class="rounded-sm bg-slate-950 px-2.5 py-1 text-xs font-bold text-white hover:bg-slate-800"
                        @click=" $refs.quickTemplateId.value = pendingId "
                    >Change template</button>
                    <button
                        type="button"
                        class="rounded-sm border border-slate-300 bg-white px-2.5 py-1 text-xs font-bold text-slate-800 hover:bg-slate-50"
                        @click="cancel()"
                    >Keep {{ $currentTemplateName }}</button>
                </div>
            </div>
        </form>

        @if ($retainedCount > 0)
            <p class="mt-1 text-[11px] font-medium text-slate-600" data-inspection-retained-history>
                {{ $retainedCount }} {{ $retainedCount === 1 ? 'point' : 'points' }} kept as history
                @if ($previousTemplateName)
                    from {{ $previousTemplateName }}
                @endif
            </p>
        @endif

        @error('inspection_template_id')
            <p class="mt-1 text-xs font-semibold text-rose-700">{{ $message }}</p>
        @enderror
    </div>
@endif
