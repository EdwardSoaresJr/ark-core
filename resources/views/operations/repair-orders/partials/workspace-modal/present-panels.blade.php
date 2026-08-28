{{-- Presentation → modal authoring panels (visit reason, narrative, scope chips, RA meta) --}}
@php
    use App\Ark\Operations\RepairOrders\RecommendationIntent;
    use App\Ark\Operations\Settings\ShopSettings;

    $shopSettingsForBilling = ShopSettings::current();
    $techniciansForMeta = $technicians ?? collect();
@endphp

<div class="ops-workspace-modal__panel" x-show="task === 'visit-reason'" x-cloak>
    <form
        method="POST"
        action="{{ route('operations.repair-orders.visit-reason.update', $repairOrder) }}"
        data-workspace-modal-form="visit-reason"
        data-refresh-scope="worksheet"
        data-saving-label="Saving…"
        @submit.prevent="submitWorksheetForm($event)"
    >
        @csrf
        @method('PATCH')
        <input type="hidden" name="opened_estimate_version" value="{{ $estimateVersion }}">
        <label class="block text-[11px] font-medium text-slate-500" for="workspace-visit-reason">
            Reason for visit
            <div
                class="ark-ro-mention mt-1"
                x-data="arkRoMention(@js(($priorVisitMentions['suggestions'] ?? [])))"
            >
                <textarea
                    id="workspace-visit-reason"
                    name="visit_reason"
                    x-ref="field"
                    rows="4"
                    placeholder="What the customer said when they brought the vehicle in. Type @RO to mention a previous visit."
                    class="w-full resize-y rounded-sm border border-slate-300 bg-white px-3 py-1.5 text-sm leading-5 text-slate-950 placeholder:text-slate-400"
                    @input="onInput()"
                    @keydown="onKeydown($event)"
                >{{ old('visit_reason', $repairOrder->visit_reason ?? '') }}</textarea>
                @include('operations.repair-orders.partials.repair-order-mention-suggest')
            </div>
        </label>
        @error('visit_reason')
            <p class="ops-field-error mt-1 text-xs font-semibold text-rose-700">{{ $message }}</p>
        @enderror
    </form>
</div>

<div
    class="ops-workspace-modal__panel"
    x-show="task === 'dragon-service-advisor-visit-reason'"
    x-cloak
    x-data="arkDragonServiceAdvisor({
        requestUrl: @js(route('operations.repair-orders.dragon-service-advisor.visit-reason', $repairOrder)),
        statusUrlTemplate: @js('/app/repair-orders/'.$repairOrder->getRouteKey().'/dragon-assist/__ASSIST__'),
        applyUrlTemplate: @js('/app/repair-orders/'.$repairOrder->getRouteKey().'/dragon-service-advisor/visit-reason/__ASSIST__/apply'),
        revertUrlTemplate: @js('/app/repair-orders/'.$repairOrder->getRouteKey().'/dragon-service-advisor/__APP__/revert'),
        estimateVersion: @js($estimateVersion),
        csrfToken: @js(csrf_token()),
        fields: @js([['value' => 'visit_reason', 'label' => 'Reason for visit']]),
        modes: @js(collect(\App\Ark\Dragon\ServiceAdvisor\ServiceAdvisorMode::cases())->map(fn ($m) => ['value' => $m->value, 'label' => $m->label()])->values()->all()),
        defaultField: 'visit_reason',
        defaultMode: 'service_advisor_rewrite',
        hideFieldPicker: true,
    })"
>
    <div class="space-y-3 text-sm text-slate-800">
        <p class="text-[11px] text-slate-500" x-text="provenance"></p>
        <template x-if="phase === 'idle' || phase === 'generating' || phase === 'error'">
            <div class="space-y-3">
                <label class="block text-[11px] font-medium text-slate-500">
                    Mode
                    <select x-model="mode" class="mt-1 w-full rounded-sm border border-slate-300 bg-white px-3 py-1.5 text-sm text-slate-950">
                        <template x-for="opt in modes" :key="opt.value">
                            <option :value="opt.value" x-text="opt.label"></option>
                        </template>
                    </select>
                </label>
                <button
                    type="button"
                    class="rounded-sm bg-slate-900 px-3 py-1.5 text-xs font-semibold text-white disabled:opacity-50"
                    @click="generate()"
                    :disabled="! canGenerate"
                    x-text="phase === 'generating' ? 'Dragon drafting…' : 'Generate'"
                ></button>
                <p class="text-xs text-rose-700" x-show="errorMessage" x-text="errorMessage" x-cloak></p>
            </div>
        </template>
        <template x-if="phase === 'preview' || phase === 'applying'">
            <div class="space-y-3">
                <div>
                    <p class="text-[11px] font-semibold uppercase tracking-wide text-slate-500">Original</p>
                    <p class="mt-1 whitespace-pre-wrap rounded-sm border border-slate-200 bg-slate-50 px-3 py-2 text-sm text-slate-700" x-text="originalText"></p>
                </div>
                <div>
                    <p class="text-[11px] font-semibold uppercase tracking-wide text-slate-500">Dragon proposal</p>
                    <template x-if="!editing">
                        <p class="mt-1 whitespace-pre-wrap rounded-sm border border-slate-200 bg-white px-3 py-2 text-sm text-slate-900" x-text="assist?.proposal"></p>
                    </template>
                    <template x-if="editing">
                        <textarea x-model="editedProposal" rows="6" class="mt-1 w-full rounded-sm border border-slate-300 bg-white px-3 py-2 text-sm text-slate-900"></textarea>
                    </template>
                </div>
                <div class="flex flex-wrap gap-2">
                    <button type="button" class="rounded-sm bg-slate-900 px-3 py-1.5 text-xs font-semibold text-white disabled:opacity-50" @click="apply()" :disabled="phase === 'applying'" x-text="phase === 'applying' ? 'Applying…' : 'Apply'"></button>
                    <button type="button" class="rounded-sm border border-slate-300 bg-white px-3 py-1.5 text-xs font-semibold text-slate-800" @click="editing ? cancelEdit() : startEdit()" x-text="editing ? 'Cancel edit' : 'Edit Proposal'"></button>
                    <button type="button" class="rounded-sm border border-slate-300 bg-white px-3 py-1.5 text-xs font-semibold text-slate-800" @click="reset()">Cancel</button>
                </div>
                <p class="text-xs text-rose-700" x-show="errorMessage" x-text="errorMessage" x-cloak></p>
            </div>
        </template>
    </div>
</div>

<div
    class="ops-workspace-modal__panel"
    x-show="task === 'dragon-service-advisor-line-note'"
    x-cloak
    x-data="arkDragonServiceAdvisor({
        requestUrlTemplate: @js('/app/repair-orders/'.$repairOrder->getRouteKey().'/lines/__LINE__/dragon-service-advisor'),
        statusUrlTemplate: @js('/app/repair-orders/'.$repairOrder->getRouteKey().'/dragon-assist/__ASSIST__'),
        applyUrlTemplate: @js('/app/repair-orders/'.$repairOrder->getRouteKey().'/lines/__LINE__/dragon-service-advisor/__ASSIST__/apply'),
        revertUrlTemplate: @js('/app/repair-orders/'.$repairOrder->getRouteKey().'/dragon-service-advisor/__APP__/revert'),
        estimateVersion: @js($estimateVersion),
        csrfToken: @js(csrf_token()),
        fields: @js([['value' => 'line_note', 'label' => 'Line note']]),
        modes: @js(collect(\App\Ark\Dragon\ServiceAdvisor\ServiceAdvisorMode::cases())->map(fn ($m) => ['value' => $m->value, 'label' => $m->label()])->values()->all()),
        defaultField: 'line_note',
        defaultMode: 'service_advisor_rewrite',
        hideFieldPicker: true,
    })"
    x-init="syncFieldFromContext($el)"
>
    <div class="space-y-3 text-sm text-slate-800">
        <p class="text-[11px] text-slate-500" x-text="provenance"></p>
        <template x-if="phase === 'idle' || phase === 'generating' || phase === 'error'">
            <div class="space-y-3">
                <label class="block text-[11px] font-medium text-slate-500">
                    Mode
                    <select x-model="mode" class="mt-1 w-full rounded-sm border border-slate-300 bg-white px-3 py-1.5 text-sm text-slate-950">
                        <template x-for="opt in modes" :key="opt.value">
                            <option :value="opt.value" x-text="opt.label"></option>
                        </template>
                    </select>
                </label>
                <button
                    type="button"
                    class="rounded-sm bg-slate-900 px-3 py-1.5 text-xs font-semibold text-white disabled:opacity-50"
                    @click="generate()"
                    :disabled="! canGenerate"
                    x-text="phase === 'generating' ? 'Dragon drafting…' : 'Generate'"
                ></button>
                <p class="text-xs text-rose-700" x-show="errorMessage" x-text="errorMessage" x-cloak></p>
            </div>
        </template>
        <template x-if="phase === 'preview' || phase === 'applying'">
            <div class="space-y-3">
                <div>
                    <p class="text-[11px] font-semibold uppercase tracking-wide text-slate-500">Original</p>
                    <p class="mt-1 whitespace-pre-wrap rounded-sm border border-slate-200 bg-slate-50 px-3 py-2 text-sm text-slate-700" x-text="originalText"></p>
                </div>
                <div>
                    <p class="text-[11px] font-semibold uppercase tracking-wide text-slate-500">Dragon proposal</p>
                    <template x-if="!editing">
                        <p class="mt-1 whitespace-pre-wrap rounded-sm border border-slate-200 bg-white px-3 py-2 text-sm text-slate-900" x-text="assist?.proposal"></p>
                    </template>
                    <template x-if="editing">
                        <textarea x-model="editedProposal" rows="6" class="mt-1 w-full rounded-sm border border-slate-300 bg-white px-3 py-2 text-sm text-slate-900"></textarea>
                    </template>
                </div>
                <div class="flex flex-wrap gap-2">
                    <button type="button" class="rounded-sm bg-slate-900 px-3 py-1.5 text-xs font-semibold text-white disabled:opacity-50" @click="apply()" :disabled="phase === 'applying'" x-text="phase === 'applying' ? 'Applying…' : 'Apply'"></button>
                    <button type="button" class="rounded-sm border border-slate-300 bg-white px-3 py-1.5 text-xs font-semibold text-slate-800" @click="editing ? cancelEdit() : startEdit()" x-text="editing ? 'Cancel edit' : 'Edit Proposal'"></button>
                    <button type="button" class="rounded-sm border border-slate-300 bg-white px-3 py-1.5 text-xs font-semibold text-slate-800" @click="reset()">Cancel</button>
                </div>
                <p class="text-xs text-rose-700" x-show="errorMessage" x-text="errorMessage" x-cloak></p>
            </div>
        </template>
    </div>
</div>

<div
    class="ops-workspace-modal__panel"
    x-show="task === 'review-estimate-notes'"
    x-cloak
    x-data="arkReviewEstimateNotes({
        requestUrl: @js(route('operations.repair-orders.review-estimate-notes', $repairOrder)),
        statusUrlTemplate: @js('/app/repair-orders/'.$repairOrder->getRouteKey().'/dragon-assist/__ASSIST__'),
        applyUrlTemplate: @js('/app/repair-orders/'.$repairOrder->getRouteKey().'/review-estimate-notes/__ASSIST__/apply'),
        estimateVersion: @js($estimateVersion),
        csrfToken: @js(csrf_token()),
    })"
    x-init="syncScopeFromContext($root)"
>
    <div class="space-y-3 text-sm text-slate-800">
        <p class="text-[11px] text-slate-500" x-text="provenance"></p>

        <template x-if="phase === 'idle' || phase === 'generating' || phase === 'error'">
            <div class="space-y-3">
                <p class="text-sm text-slate-600" x-text="concernId
                    ? 'Dragon reviews this concern’s narrative and note lines, then proposes rewrites. Nothing changes until you Apply a proposal.'
                    : 'Dragon reads visit reason, concern narratives, and note lines, then lists gaps and optional rewrite proposals. Nothing changes until you Apply a proposal.'"></p>
                <button
                    type="button"
                    class="rounded-sm bg-slate-900 px-3 py-1.5 text-xs font-semibold text-white disabled:opacity-50"
                    @click="generate()"
                    :disabled="phase === 'generating'"
                    x-text="phase === 'generating' ? 'Dragon reviewing…' : (concernId ? 'Review this concern' : 'Review notes')"
                ></button>
                <p class="text-xs text-rose-700" x-show="errorMessage" x-text="errorMessage" x-cloak></p>
            </div>
        </template>

        <template x-if="phase === 'review'">
            <div class="space-y-3">
                <div>
                    <p class="text-[11px] font-semibold uppercase tracking-wide text-slate-500">Summary</p>
                    <p class="mt-1 whitespace-pre-wrap text-sm text-slate-900" x-text="assist?.summary"></p>
                </div>
                <template x-if="(assist?.strengths || []).length">
                    <div>
                        <p class="text-[11px] font-semibold uppercase tracking-wide text-slate-500">Strengths</p>
                        <ul class="mt-1 list-disc space-y-1 pl-4 text-sm text-slate-700">
                            <template x-for="item in assist.strengths" :key="item">
                                <li x-text="item"></li>
                            </template>
                        </ul>
                    </div>
                </template>
                <template x-if="(assist?.gaps || []).length">
                    <div>
                        <p class="text-[11px] font-semibold uppercase tracking-wide text-amber-700">Gaps</p>
                        <ul class="mt-1 list-disc space-y-1 pl-4 text-sm text-slate-800">
                            <template x-for="item in assist.gaps" :key="item">
                                <li x-text="item"></li>
                            </template>
                        </ul>
                    </div>
                </template>
                <template x-if="(assist?.inconsistencies || []).length">
                    <div>
                        <p class="text-[11px] font-semibold uppercase tracking-wide text-rose-700">Inconsistencies</p>
                        <ul class="mt-1 list-disc space-y-1 pl-4 text-sm text-slate-800">
                            <template x-for="item in assist.inconsistencies" :key="item">
                                <li x-text="item"></li>
                            </template>
                        </ul>
                    </div>
                </template>
                <template x-if="assist?.customer_readiness">
                    <div>
                        <p class="text-[11px] font-semibold uppercase tracking-wide text-slate-500">Customer readiness</p>
                        <p class="mt-1 whitespace-pre-wrap text-sm text-slate-800" x-text="assist.customer_readiness"></p>
                    </div>
                </template>
                <template x-if="(assist?.suggested_actions || []).length">
                    <div>
                        <p class="text-[11px] font-semibold uppercase tracking-wide text-slate-500">Suggested actions</p>
                        <ul class="mt-1 list-disc space-y-1 pl-4 text-sm text-slate-700">
                            <template x-for="item in assist.suggested_actions" :key="item">
                                <li x-text="item"></li>
                            </template>
                        </ul>
                    </div>
                </template>
                <template x-if="(assist?.warnings || []).length">
                    <div>
                        <p class="text-[11px] font-semibold uppercase tracking-wide text-slate-500">Warnings</p>
                        <ul class="mt-1 list-disc space-y-1 pl-4 text-sm text-slate-600">
                            <template x-for="item in assist.warnings" :key="item">
                                <li x-text="item"></li>
                            </template>
                        </ul>
                    </div>
                </template>

                <template x-if="(assist?.proposals || []).length">
                    <div class="space-y-3 border-t border-slate-200 pt-3">
                        <p class="text-[11px] font-semibold uppercase tracking-wide text-slate-500">Proposed rewrites</p>
                        <template x-for="p in assist.proposals" :key="proposalKey(p)">
                            <div
                                class="rounded-sm border border-slate-200 bg-white px-3 py-2"
                                x-show="! proposalState(p).skipped"
                            >
                                <p class="text-[11px] font-semibold text-slate-700">
                                    <span x-text="fieldLabel(p.field)"></span>
                                    <template x-if="p.field === 'visit_reason'">
                                        <span class="font-normal text-slate-500"> · repair order</span>
                                    </template>
                                    <template x-if="p.field === 'line_note'">
                                        <span class="font-normal text-slate-500">
                                            · line <span x-text="p.line_id"></span>
                                        </span>
                                    </template>
                                    <template x-if="p.field !== 'visit_reason' && p.field !== 'line_note'">
                                        <span class="font-normal text-slate-500">
                                            · concern <span x-text="p.concern_id"></span>
                                        </span>
                                    </template>
                                </p>
                                <template x-if="p.reason">
                                    <p class="mt-1 text-xs text-slate-600" x-text="p.reason"></p>
                                </template>
                                <template x-if="proposalState(p).applied">
                                    <p class="mt-2 text-xs font-semibold text-emerald-700">Applied.</p>
                                </template>
                                <template x-if="! proposalState(p).applied">
                                    <div class="mt-2 space-y-2">
                                        <div>
                                            <p class="text-[10px] font-semibold uppercase tracking-wide text-slate-400">Original</p>
                                            <p class="mt-0.5 whitespace-pre-wrap text-xs text-slate-600" x-text="p.original_text"></p>
                                        </div>
                                        <div>
                                            <p class="text-[10px] font-semibold uppercase tracking-wide text-slate-400">Proposal</p>
                                            <template x-if="! proposalState(p).editing">
                                                <p class="mt-0.5 whitespace-pre-wrap text-xs text-slate-900" x-text="p.proposed_text"></p>
                                            </template>
                                            <template x-if="proposalState(p).editing">
                                                <textarea
                                                    x-model="proposalState(p).editedText"
                                                    rows="4"
                                                    class="mt-0.5 w-full rounded-sm border border-slate-300 bg-white px-2 py-1.5 text-xs text-slate-900"
                                                ></textarea>
                                            </template>
                                        </div>
                                        <template x-if="! p.applyable">
                                            <p class="text-xs text-amber-800" x-text="p.rejected_reason || 'Not applyable — critique only.'"></p>
                                        </template>
                                        <div class="flex flex-wrap gap-2" x-show="p.applyable">
                                            <button
                                                type="button"
                                                class="rounded-sm bg-slate-900 px-2.5 py-1 text-[11px] font-semibold text-white disabled:opacity-50"
                                                @click="applyProposal(p)"
                                                :disabled="proposalState(p).applying"
                                                x-text="proposalState(p).applying ? 'Applying…' : 'Apply'"
                                            ></button>
                                            <button
                                                type="button"
                                                class="rounded-sm border border-slate-300 bg-white px-2.5 py-1 text-[11px] font-semibold text-slate-800"
                                                @click="proposalState(p).editing ? cancelEdit(p) : startEdit(p)"
                                                x-text="proposalState(p).editing ? 'Cancel edit' : 'Edit then Apply'"
                                            ></button>
                                            <button
                                                type="button"
                                                class="rounded-sm border border-slate-300 bg-white px-2.5 py-1 text-[11px] font-semibold text-slate-800"
                                                @click="skipProposal(p)"
                                            >Skip</button>
                                        </div>
                                        <p class="text-xs text-rose-700" x-show="proposalState(p).error" x-text="proposalState(p).error"></p>
                                    </div>
                                </template>
                            </div>
                        </template>
                    </div>
                </template>

                <p class="text-[11px] text-slate-500">Nothing changes until you Apply a proposal.</p>
                <button type="button" class="rounded-sm border border-slate-300 bg-white px-3 py-1.5 text-xs font-semibold text-slate-800" @click="reset()">Review again</button>
            </div>
        </template>
    </div>
</div>

@foreach ($repairOrder->concerns as $concern)
    @php
        $concernDispositions = App\Ark\Operations\RepairOrders\RepairOrderConcernDisposition::cases();
        $productionStatuses = App\Ark\Operations\RepairOrders\ScopeProductionStatus::cases();
    @endphp

    <div
        class="ops-workspace-modal__panel"
        x-show="task === 'concern-narrative' && String(context.concernId) === '{{ $concern->id }}'"
        x-cloak
    >
        <form
            method="POST"
            action="{{ route('operations.repair-orders.concerns.update', [$repairOrder, $concern]) }}"
            data-workspace-modal-form="concern-narrative"
            data-refresh-scope="worksheet"
            data-saving-label="Saving…"
            @submit.prevent="submitWorksheetForm($event)"
            class="space-y-3"
        >
            @csrf
            @method('PATCH')
            <input type="hidden" name="{{ App\Ark\Operations\RepairOrders\RepairOrderConcurrency::FIELD }}" value="{{ $estimateVersion }}">
            <label class="block text-[11px] font-medium text-slate-500">
                Concern
                <div
                    class="ark-ro-mention mt-1"
                    x-data="arkRoMention(@js(($priorVisitMentions['suggestions'] ?? [])))"
                >
                    <input
                        name="summary"
                        x-ref="field"
                        value="{{ old('summary', $concern->summary) }}"
                        required
                        placeholder="The problem — e.g. Overheating, brake noise. Type @RO for a previous visit."
                        class="w-full rounded-sm border border-slate-300 bg-white px-3 py-1.5 text-sm text-slate-950 placeholder:text-slate-400"
                        @input="onInput()"
                        @keydown="onKeydown($event)"
                    >
                    @include('operations.repair-orders.partials.repair-order-mention-suggest')
                </div>
            </label>
            <label class="block text-[11px] font-medium text-slate-500">
                Recommendation intent
                @include('operations.repair-orders.partials.recommendation-intent-select', [
                    'selected' => old('recommendation_intent', $concern->recommendationIntent()->value),
                    'inputId' => 'workspace-concern-intent-'.$concern->id,
                    'selectClass' => 'mt-1 w-full rounded-sm border border-slate-300 bg-white px-3 py-1.5 text-sm text-slate-950',
                ])
            </label>
            <div class="grid gap-3 md:grid-cols-2">
                <label class="block text-[11px] font-medium text-slate-500">
                    Customer states
                    <textarea name="customer_states" rows="3" class="mt-1 w-full rounded-sm border border-slate-300 bg-white px-3 py-1.5 text-sm text-slate-950" placeholder="What the customer reported…">{{ old('customer_states', $concern->customer_states) }}</textarea>
                </label>
                <label class="block text-[11px] font-medium text-slate-500">
                    Verified findings
                    <textarea name="verified_findings" rows="3" class="mt-1 w-full rounded-sm border border-slate-300 bg-white px-3 py-1.5 text-sm text-slate-950" placeholder="What was verified…">{{ old('verified_findings', $concern->verified_findings) }}</textarea>
                </label>
                <label class="block text-[11px] font-medium text-slate-500">
                    DTCs, if present
                    <textarea name="dtcs_summary" rows="2" class="mt-1 w-full rounded-sm border border-slate-300 bg-white px-3 py-1.5 text-sm text-slate-950" placeholder="e.g. P0303 current">{{ old('dtcs_summary', $concern->dtcs_summary) }}</textarea>
                </label>
                <label class="block text-[11px] font-medium text-slate-500">
                    Recommendation
                    <textarea name="recommendation" rows="2" class="mt-1 w-full rounded-sm border border-slate-300 bg-white px-3 py-1.5 text-sm text-slate-950" placeholder="Recommended repair…">{{ old('recommendation', $concern->recommendation) }}</textarea>
                </label>
            </div>
        </form>
    </div>

    <div
        class="ops-workspace-modal__panel"
        x-show="task === 'concern-disposition' && String(context.concernId) === '{{ $concern->id }}'"
        x-cloak
    >
        <form
            method="POST"
            action="{{ route('operations.repair-orders.concerns.disposition', [$repairOrder, $concern]) }}"
            data-workspace-modal-form="concern-disposition"
            data-refresh-scope="worksheet"
            data-saving-label="Saving…"
            @submit.prevent="submitWorksheetForm($event)"
        >
            @csrf
            @method('PATCH')
            <input type="hidden" name="{{ App\Ark\Operations\RepairOrders\RepairOrderConcurrency::FIELD }}" value="{{ $estimateVersion }}">
            <label class="block text-[11px] font-medium text-slate-500" for="workspace-disposition-{{ $concern->id }}">
                Customer decision
                <select
                    id="workspace-disposition-{{ $concern->id }}"
                    name="disposition"
                    class="mt-1 w-full rounded-sm border border-slate-300 bg-white px-3 py-1.5 text-sm text-slate-950"
                >
                    @foreach ($concernDispositions as $disposition)
                        <option
                            value="{{ $disposition->value }}"
                            title="{{ $disposition->helpText() }}"
                            @selected($concern->disposition === $disposition)
                        >{{ $disposition->label() }}</option>
                    @endforeach
                </select>
            </label>
        </form>
    </div>

    @php
        $dragonFieldOptions = collect(\App\Ark\Dragon\ServiceAdvisor\ServiceAdvisorField::cases())
            ->filter(fn (\App\Ark\Dragon\ServiceAdvisor\ServiceAdvisorField $f) => $f->isConcernNarrative())
            ->map(fn (\App\Ark\Dragon\ServiceAdvisor\ServiceAdvisorField $f) => [
                'value' => $f->value,
                'label' => $f->label(),
                'has_text' => filled(match ($f) {
                    \App\Ark\Dragon\ServiceAdvisor\ServiceAdvisorField::Summary => $concern->summary,
                    \App\Ark\Dragon\ServiceAdvisor\ServiceAdvisorField::CustomerStates => $concern->customer_states,
                    \App\Ark\Dragon\ServiceAdvisor\ServiceAdvisorField::VerifiedFindings => $concern->verified_findings,
                    \App\Ark\Dragon\ServiceAdvisor\ServiceAdvisorField::DtcsSummary => $concern->dtcs_summary,
                    \App\Ark\Dragon\ServiceAdvisor\ServiceAdvisorField::Recommendation => $concern->recommendation,
                }),
            ])
            ->values()
            ->all();
        $defaultDragonField = collect($dragonFieldOptions)->firstWhere('value', 'verified_findings')['value']
            ?? ($dragonFieldOptions[0]['value'] ?? 'summary');
        $dragonModeOptions = collect(\App\Ark\Dragon\ServiceAdvisor\ServiceAdvisorMode::cases())
            ->map(fn ($m) => ['value' => $m->value, 'label' => $m->label()])
            ->values()
            ->all();
    @endphp

    <div
        class="ops-workspace-modal__panel"
        x-show="task === 'dragon-service-advisor' && String(context.concernId) === '{{ $concern->id }}'"
        x-cloak
        x-data="arkDragonServiceAdvisor({
            requestUrl: @js(route('operations.repair-orders.concerns.dragon-service-advisor', [$repairOrder, $concern])),
            statusUrlTemplate: @js('/app/repair-orders/'.$repairOrder->getRouteKey().'/dragon-assist/__ASSIST__'),
            applyUrlTemplate: @js('/app/repair-orders/'.$repairOrder->getRouteKey().'/concerns/'.$concern->id.'/dragon-service-advisor/__ASSIST__/apply'),
            revertUrlTemplate: @js('/app/repair-orders/'.$repairOrder->getRouteKey().'/dragon-service-advisor/__APP__/revert'),
            estimateVersion: @js($estimateVersion),
            csrfToken: @js(csrf_token()),
            fields: @js($dragonFieldOptions),
            modes: @js($dragonModeOptions),
            defaultField: @js($defaultDragonField),
            defaultMode: 'service_advisor_rewrite',
        })"
        x-init="syncFieldFromContext($el)"
    >
        <div class="space-y-3 text-sm text-slate-800">
            <p class="text-[11px] text-slate-500" x-text="provenance"></p>

            <template x-if="phase === 'idle' || phase === 'generating' || phase === 'error'">
                <div class="space-y-3">
                    <label class="block text-[11px] font-medium text-slate-500">
                        Field
                        <select x-model="field" class="mt-1 w-full rounded-sm border border-slate-300 bg-white px-3 py-1.5 text-sm text-slate-950">
                            <template x-for="opt in fields" :key="opt.value">
                                <option :value="opt.value" x-text="opt.label"></option>
                            </template>
                        </select>
                    </label>
                    <label class="block text-[11px] font-medium text-slate-500">
                        Mode
                        <select x-model="mode" class="mt-1 w-full rounded-sm border border-slate-300 bg-white px-3 py-1.5 text-sm text-slate-950">
                            <template x-for="opt in modes" :key="opt.value">
                                <option :value="opt.value" x-text="opt.label"></option>
                            </template>
                        </select>
                    </label>
                    <button
                        type="button"
                        class="rounded-sm bg-slate-900 px-3 py-1.5 text-xs font-semibold text-white disabled:opacity-50"
                        @click="generate()"
                        :disabled="! canGenerate"
                        x-text="phase === 'generating' ? 'Dragon drafting…' : 'Generate'"
                    ></button>
                    <p class="text-xs text-rose-700" x-show="errorMessage" x-text="errorMessage" x-cloak></p>
                </div>
            </template>

            <template x-if="phase === 'preview' || phase === 'applying'">
                <div class="space-y-3">
                    <div>
                        <p class="text-[11px] font-semibold uppercase tracking-wide text-slate-500">Original</p>
                        <p class="mt-1 whitespace-pre-wrap rounded-sm border border-slate-200 bg-slate-50 px-3 py-2 text-sm text-slate-700" x-text="originalText"></p>
                    </div>
                    <div>
                        <p class="text-[11px] font-semibold uppercase tracking-wide text-slate-500">Dragon proposal</p>
                        <template x-if="!editing">
                            <p class="mt-1 whitespace-pre-wrap rounded-sm border border-slate-200 bg-white px-3 py-2 text-sm text-slate-900" x-text="assist?.proposal"></p>
                        </template>
                        <template x-if="editing">
                            <textarea x-model="editedProposal" rows="6" class="mt-1 w-full rounded-sm border border-slate-300 bg-white px-3 py-2 text-sm text-slate-900"></textarea>
                        </template>
                    </div>
                    <div class="flex flex-wrap gap-2">
                        <button type="button" class="rounded-sm bg-slate-900 px-3 py-1.5 text-xs font-semibold text-white disabled:opacity-50" @click="apply()" :disabled="phase === 'applying'" x-text="phase === 'applying' ? 'Applying…' : 'Apply'"></button>
                        <button type="button" class="rounded-sm border border-slate-300 bg-white px-3 py-1.5 text-xs font-semibold text-slate-800" @click="editing ? cancelEdit() : startEdit()" x-text="editing ? 'Cancel edit' : 'Edit Proposal'"></button>
                        <button type="button" class="rounded-sm border border-slate-300 bg-white px-3 py-1.5 text-xs font-semibold text-slate-800" @click="reset()">Cancel</button>
                    </div>
                    <p class="text-xs text-rose-700" x-show="errorMessage" x-text="errorMessage" x-cloak></p>
                </div>
            </template>
        </div>
    </div>

    <div
        class="ops-workspace-modal__panel"
        x-show="task === 'concern-billing' && String(context.concernId) === '{{ $concern->id }}'"
        x-cloak
    >
        <form
            method="POST"
            action="{{ route('operations.repair-orders.concerns.billing-posture', [$repairOrder, $concern]) }}"
            data-workspace-modal-form="concern-billing"
            data-refresh-scope="worksheet"
            data-saving-label="Saving…"
            @submit.prevent="submitWorksheetForm($event)"
        >
            @csrf
            @method('PATCH')
            <input type="hidden" name="{{ App\Ark\Operations\RepairOrders\RepairOrderConcurrency::FIELD }}" value="{{ $estimateVersion }}">
            <label class="block text-[11px] font-medium text-slate-500" for="workspace-billing-{{ $concern->id }}">
                Billing
                <select
                    id="workspace-billing-{{ $concern->id }}"
                    name="billing_posture"
                    class="mt-1 w-full rounded-sm border border-slate-300 bg-white px-3 py-1.5 text-sm text-slate-950"
                >
                    @foreach (App\Ark\Operations\RepairOrders\ConcernBillingPosture::advisorSelectableCases() as $option)
                        @php
                            $billingOption = $shopSettingsForBilling->billingPostureOptionPresentation($option);
                        @endphp
                        <option
                            value="{{ $option->value }}"
                            title="{{ $billingOption['title'] }}"
                            @selected($concern->billing_posture === $option)
                        >{{ $billingOption['label'] }}</option>
                    @endforeach
                </select>
            </label>
        </form>
    </div>

    <div
        class="ops-workspace-modal__panel"
        x-show="task === 'concern-intent' && String(context.concernId) === '{{ $concern->id }}'"
        x-cloak
    >
        <form
            method="POST"
            action="{{ route('operations.repair-orders.concerns.recommendation-intent', [$repairOrder, $concern]) }}"
            data-workspace-modal-form="concern-intent"
            data-refresh-scope="worksheet"
            data-saving-label="Saving…"
            @submit.prevent="submitWorksheetForm($event)"
        >
            @csrf
            @method('PATCH')
            <input type="hidden" name="{{ App\Ark\Operations\RepairOrders\RepairOrderConcurrency::FIELD }}" value="{{ $estimateVersion }}">
            <label class="block text-[11px] font-medium text-slate-500" for="workspace-intent-only-{{ $concern->id }}">
                Recommendation status
                <select
                    id="workspace-intent-only-{{ $concern->id }}"
                    name="recommendation_intent"
                    class="mt-1 w-full rounded-sm border border-slate-300 bg-white px-3 py-1.5 text-sm text-slate-950"
                >
                    @foreach (RecommendationIntent::cases() as $option)
                        <option
                            value="{{ $option->value }}"
                            title="{{ $option->helpText() }} Customer PDF: {{ $option->customerLabel() }}."
                            @selected($concern->recommendationIntent() === $option)
                        >{{ $option->staffLabel() }}</option>
                    @endforeach
                </select>
            </label>
        </form>
    </div>

    @if ($concern->tracksProduction())
        <div
            class="ops-workspace-modal__panel"
            x-show="task === 'concern-production' && String(context.concernId) === '{{ $concern->id }}'"
            x-cloak
        >
            <form
                method="POST"
                action="{{ route('operations.repair-orders.concerns.production-status', [$repairOrder, $concern]) }}"
                data-workspace-modal-form="concern-production"
                data-refresh-scope="worksheet"
                data-saving-label="Saving…"
                @submit.prevent="submitWorksheetForm($event)"
            >
                @csrf
                @method('PATCH')
                <input type="hidden" name="{{ App\Ark\Operations\RepairOrders\RepairOrderConcurrency::FIELD }}" value="{{ $estimateVersion }}">
                <label class="block text-[11px] font-medium text-slate-500" for="workspace-production-{{ $concern->id }}">
                    Production status
                    <select
                        id="workspace-production-{{ $concern->id }}"
                        name="production_status"
                        class="mt-1 w-full rounded-sm border border-slate-300 bg-white px-3 py-1.5 text-sm text-slate-950"
                    >
                        @foreach ($productionStatuses as $status)
                            <option
                                value="{{ $status->value }}"
                                title="{{ $status->helpText() }}"
                                @selected($concern->productionStatus() === $status)
                            >{{ $status->label() }}</option>
                        @endforeach
                    </select>
                </label>
            </form>
        </div>
    @endif

    @foreach ($concern->workGroups as $workGroup)
        @php
            $actionStatus = $workGroup->status instanceof \App\Ark\Operations\RepairOrders\RepairActionStatus
                ? $workGroup->status
                : \App\Ark\Operations\RepairOrders\RepairActionStatus::Pending;
            $actionStatuses = \App\Ark\Operations\RepairOrders\RepairActionStatus::cases();
        @endphp
        <div
            class="ops-workspace-modal__panel"
            x-show="task === 'repair-action-meta' && String(context.workGroupId) === '{{ $workGroup->id }}'"
            x-cloak
        >
            <div class="space-y-4">
                @if ($techniciansForMeta->isNotEmpty())
                    <form
                        method="POST"
                        action="{{ route('operations.repair-orders.work-groups.owner.update', [$repairOrder, $workGroup]) }}"
                        data-workspace-modal-form="repair-action-meta"
                        data-workspace-modal-bundle="repair-action-meta"
                        data-refresh-scope="worksheet"
                        data-saving-label="Saving…"
                        @submit.prevent="submitWorksheetForm($event)"
                    >
                        @csrf
                        @method('PATCH')
                        <input type="hidden" name="{{ App\Ark\Operations\RepairOrders\RepairOrderConcurrency::FIELD }}" value="{{ $estimateVersion }}">
                        <label class="block text-[11px] font-medium text-slate-500">
                            Owner
                            <select
                                name="owner_user_id"
                                required
                                class="mt-1 w-full rounded-sm border border-slate-300 bg-white px-3 py-1.5 text-sm text-slate-950"
                            >
                                <option value="" @selected($workGroup->owner_user_id === null) disabled>Unassigned</option>
                                @foreach ($techniciansForMeta as $technician)
                                    <option value="{{ $technician->id }}" @selected((int) $workGroup->owner_user_id === (int) $technician->id)>{{ $technician->name }}</option>
                                @endforeach
                            </select>
                        </label>
                    </form>
                @endif

                <form
                    method="POST"
                    action="{{ route('operations.repair-orders.work-groups.communication.update', [$repairOrder, $workGroup]) }}"
                    data-workspace-modal-form="repair-action-meta"
                    data-workspace-modal-bundle="repair-action-meta"
                    data-refresh-scope="worksheet"
                    data-saving-label="Saving…"
                    @submit.prevent="submitWorksheetForm($event)"
                    class="space-y-3"
                >
                    @csrf
                    @method('PATCH')
                    <input type="hidden" name="{{ App\Ark\Operations\RepairOrders\RepairOrderConcurrency::FIELD }}" value="{{ $estimateVersion }}">
                    <label class="block text-[11px] font-medium text-slate-500">
                        Status
                        <select
                            name="status"
                            class="mt-1 w-full rounded-sm border border-slate-300 bg-white px-3 py-1.5 text-sm text-slate-950"
                        >
                            @foreach ($actionStatuses as $statusOption)
                                <option value="{{ $statusOption->value }}" @selected($actionStatus === $statusOption)>{{ $statusOption->label() }}</option>
                            @endforeach
                        </select>
                    </label>
                    <label class="block text-[11px] font-medium text-slate-500">
                        Update
                        <textarea
                            name="latest_update"
                            rows="3"
                            maxlength="2000"
                            placeholder="Add an update…"
                            class="mt-1 w-full rounded-sm border border-slate-300 bg-white px-3 py-1.5 text-sm text-slate-950 placeholder:text-slate-400"
                        >{{ old('latest_update', $workGroup->latest_update) }}</textarea>
                    </label>
                </form>
            </div>
        </div>
    @endforeach
@endforeach
