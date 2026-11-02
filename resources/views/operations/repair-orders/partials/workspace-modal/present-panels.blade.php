{{-- Presentation → modal authoring panels (visit reason, narrative, scope chips, RA meta) --}}
@php
    use App\Ark\Operations\RepairOrders\RecommendationIntent;

    $techniciansForMeta = $technicians ?? collect();
    $dragonReady = class_exists(\Ark\Saas\Dragon\ServiceAdvisor\ServiceAdvisorMode::class)
        && \Illuminate\Support\Facades\Route::has('operations.repair-orders.dragon-service-advisor.visit-reason');
    $dragonModeOptions = $dragonReady
        ? collect(\Ark\Saas\Dragon\ServiceAdvisor\ServiceAdvisorMode::cases())->map(fn ($mode) => ['value' => $mode->value, 'label' => $mode->label()])->values()->all()
        : [];
@endphp

<div data-workspace-modal-panel x-show="task === 'visit-reason'" x-cloak>
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
        <label class="form-label" for="workspace-visit-reason">Reason for visit</label>
        <div
            class="position-relative"
            x-data="arkRoMention(@js(($priorVisitMentions['suggestions'] ?? [])))"
        >
            <textarea
                id="workspace-visit-reason"
                name="visit_reason"
                x-ref="field"
                rows="4"
                placeholder="What the customer said when they brought the vehicle in. Type @RO to mention a previous visit."
                @class(['form-control', 'is-invalid' => $errors->has('visit_reason')])
                @input="onInput()"
                @keydown="onKeydown($event)"
            >{{ old('visit_reason', $repairOrder->visit_reason ?? '') }}</textarea>
            @include('operations.repair-orders.partials.repair-order-mention-suggest-tabler')
        </div>
        @error('visit_reason')
            <div class="invalid-feedback d-block" data-workspace-modal-validation>{{ $message }}</div>
        @enderror
    </form>
</div>

@if ($dragonReady)
<div
    data-workspace-modal-panel
    x-show="task === 'dragon-service-advisor-visit-reason'"
    x-cloak
    x-data="arkDragonServiceAdvisor({
        requestUrl: @js($dragonReady ? route('operations.repair-orders.dragon-service-advisor.visit-reason', $repairOrder) : null),
        statusUrlTemplate: @js('/app/repair-orders/'.$repairOrder->getRouteKey().'/dragon-assist/__ASSIST__'),
        applyUrlTemplate: @js('/app/repair-orders/'.$repairOrder->getRouteKey().'/dragon-service-advisor/visit-reason/__ASSIST__/apply'),
        revertUrlTemplate: @js('/app/repair-orders/'.$repairOrder->getRouteKey().'/dragon-service-advisor/__APP__/revert'),
        estimateVersion: @js($estimateVersion),
        csrfToken: @js(csrf_token()),
        fields: @js([['value' => 'visit_reason', 'label' => 'Reason for visit']]),
        modes: @js($dragonModeOptions),
        defaultField: 'visit_reason',
        defaultMode: 'service_advisor_rewrite',
        hideFieldPicker: true,
    })"
>
    <p class="small text-secondary mb-3" x-text="provenance"></p>
    <template x-if="phase === 'idle' || phase === 'generating' || phase === 'error'">
        <div>
            <div class="mb-3">
                <label class="form-label" for="workspace-dragon-visit-reason-mode">Mode</label>
                <select id="workspace-dragon-visit-reason-mode" x-model="mode" class="form-select">
                    <template x-for="opt in modes" :key="opt.value">
                        <option :value="opt.value" x-text="opt.label"></option>
                    </template>
                </select>
            </div>
            <button
                type="button"
                class="btn btn-primary"
                @click="generate()"
                :disabled="! canGenerate"
                x-text="phase === 'generating' ? 'Dragon drafting…' : 'Generate'"
            ></button>
            <div class="text-danger small mt-2" x-show="errorMessage" x-text="errorMessage" x-cloak></div>
        </div>
    </template>
    <template x-if="phase === 'preview' || phase === 'applying'">
        <div>
            <div class="mb-3">
                <div class="subheader mb-1">Original</div>
                <div class="border rounded px-3 py-2 text-secondary" style="white-space: pre-wrap" x-text="originalText"></div>
            </div>
            <div class="mb-3">
                <div class="subheader mb-1">Dragon proposal</div>
                <template x-if="!editing">
                    <div class="border rounded px-3 py-2" style="white-space: pre-wrap" x-text="assist?.proposal"></div>
                </template>
                <template x-if="editing">
                    <textarea x-model="editedProposal" rows="6" class="form-control" aria-label="Edited proposal"></textarea>
                </template>
            </div>
            <div class="btn-list">
                <button type="button" class="btn btn-primary" @click="apply()" :disabled="phase === 'applying'" x-text="phase === 'applying' ? 'Applying…' : 'Apply'"></button>
                <button type="button" class="btn" @click="editing ? cancelEdit() : startEdit()" x-text="editing ? 'Cancel edit' : 'Edit Proposal'"></button>
                <button type="button" class="btn" @click="reset()">Cancel</button>
            </div>
            <div class="text-danger small mt-2" x-show="errorMessage" x-text="errorMessage" x-cloak></div>
        </div>
    </template>
</div>
@endif

@if ($dragonReady)
<div
    data-workspace-modal-panel
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
        modes: @js($dragonModeOptions),
        defaultField: 'line_note',
        defaultMode: 'service_advisor_rewrite',
        hideFieldPicker: true,
    })"
    x-init="syncFieldFromContext($el)"
>
    <p class="small text-secondary mb-3" x-text="provenance"></p>
    <template x-if="phase === 'idle' || phase === 'generating' || phase === 'error'">
        <div>
            <div class="mb-3">
                <label class="form-label" for="workspace-dragon-line-note-mode">Mode</label>
                <select id="workspace-dragon-line-note-mode" x-model="mode" class="form-select">
                    <template x-for="opt in modes" :key="opt.value">
                        <option :value="opt.value" x-text="opt.label"></option>
                    </template>
                </select>
            </div>
            <button
                type="button"
                class="btn btn-primary"
                @click="generate()"
                :disabled="! canGenerate"
                x-text="phase === 'generating' ? 'Dragon drafting…' : 'Generate'"
            ></button>
            <div class="text-danger small mt-2" x-show="errorMessage" x-text="errorMessage" x-cloak></div>
        </div>
    </template>
    <template x-if="phase === 'preview' || phase === 'applying'">
        <div>
            <div class="mb-3">
                <div class="subheader mb-1">Original</div>
                <div class="border rounded px-3 py-2 text-secondary" style="white-space: pre-wrap" x-text="originalText"></div>
            </div>
            <div class="mb-3">
                <div class="subheader mb-1">Dragon proposal</div>
                <template x-if="!editing">
                    <div class="border rounded px-3 py-2" style="white-space: pre-wrap" x-text="assist?.proposal"></div>
                </template>
                <template x-if="editing">
                    <textarea x-model="editedProposal" rows="6" class="form-control" aria-label="Edited proposal"></textarea>
                </template>
            </div>
            <div class="btn-list">
                <button type="button" class="btn btn-primary" @click="apply()" :disabled="phase === 'applying'" x-text="phase === 'applying' ? 'Applying…' : 'Apply'"></button>
                <button type="button" class="btn" @click="editing ? cancelEdit() : startEdit()" x-text="editing ? 'Cancel edit' : 'Edit Proposal'"></button>
                <button type="button" class="btn" @click="reset()">Cancel</button>
            </div>
            <div class="text-danger small mt-2" x-show="errorMessage" x-text="errorMessage" x-cloak></div>
        </div>
    </template>
</div>
@endif

@if ($dragonReady)
<div
    data-workspace-modal-panel
    x-show="task === 'review-estimate-notes'"
    x-cloak
    x-data="arkReviewEstimateNotes({
        requestUrl: @js($dragonReady ? route('operations.repair-orders.review-estimate-notes', $repairOrder) : null),
        statusUrlTemplate: @js('/app/repair-orders/'.$repairOrder->getRouteKey().'/dragon-assist/__ASSIST__'),
        applyUrlTemplate: @js('/app/repair-orders/'.$repairOrder->getRouteKey().'/review-estimate-notes/__ASSIST__/apply'),
        estimateVersion: @js($estimateVersion),
        csrfToken: @js(csrf_token()),
    })"
    x-init="syncScopeFromContext($root)"
>
    <p class="small text-secondary mb-3" x-text="provenance"></p>

    <template x-if="phase === 'idle' || phase === 'generating' || phase === 'error'">
        <div>
            <p class="text-secondary" x-text="concernId
                ? 'Dragon reviews this concern’s narrative and note lines, then proposes rewrites. Nothing changes until you Apply a proposal.'
                : 'Dragon reads visit reason, concern narratives, and note lines, then lists gaps and optional rewrite proposals. Nothing changes until you Apply a proposal.'"></p>
            <button
                type="button"
                class="btn btn-primary"
                @click="generate()"
                :disabled="phase === 'generating'"
                x-text="phase === 'generating' ? 'Dragon reviewing…' : (concernId ? 'Review this concern' : 'Review notes')"
            ></button>
            <div class="text-danger small mt-2" x-show="errorMessage" x-text="errorMessage" x-cloak></div>
        </div>
    </template>

    <template x-if="phase === 'review'">
        <div>
            <div class="mb-3">
                <div class="subheader mb-1">Summary</div>
                <div style="white-space: pre-wrap" x-text="assist?.summary"></div>
            </div>
            <template x-if="(assist?.strengths || []).length">
                <div class="mb-3">
                    <div class="subheader mb-1">Strengths</div>
                    <ul class="mb-0 ps-3" style="list-style: disc">
                        <template x-for="item in assist.strengths" :key="item">
                            <li x-text="item"></li>
                        </template>
                    </ul>
                </div>
            </template>
            <template x-if="(assist?.gaps || []).length">
                <div class="mb-3">
                    <div class="subheader text-orange mb-1">Gaps</div>
                    <ul class="mb-0 ps-3" style="list-style: disc">
                        <template x-for="item in assist.gaps" :key="item">
                            <li x-text="item"></li>
                        </template>
                    </ul>
                </div>
            </template>
            <template x-if="(assist?.inconsistencies || []).length">
                <div class="mb-3">
                    <div class="subheader text-danger mb-1">Inconsistencies</div>
                    <ul class="mb-0 ps-3" style="list-style: disc">
                        <template x-for="item in assist.inconsistencies" :key="item">
                            <li x-text="item"></li>
                        </template>
                    </ul>
                </div>
            </template>
            <template x-if="assist?.customer_readiness">
                <div class="mb-3">
                    <div class="subheader mb-1">Customer readiness</div>
                    <div style="white-space: pre-wrap" x-text="assist.customer_readiness"></div>
                </div>
            </template>
            <template x-if="(assist?.suggested_actions || []).length">
                <div class="mb-3">
                    <div class="subheader mb-1">Suggested actions</div>
                    <ul class="mb-0 ps-3" style="list-style: disc">
                        <template x-for="item in assist.suggested_actions" :key="item">
                            <li x-text="item"></li>
                        </template>
                    </ul>
                </div>
            </template>
            <template x-if="(assist?.warnings || []).length">
                <div class="mb-3">
                    <div class="subheader mb-1">Warnings</div>
                    <ul class="mb-0 ps-3 text-secondary" style="list-style: disc">
                        <template x-for="item in assist.warnings" :key="item">
                            <li x-text="item"></li>
                        </template>
                    </ul>
                </div>
            </template>

            <template x-if="(assist?.proposals || []).length">
                <div class="border-top pt-3 mb-3">
                    <div class="subheader mb-2">Proposed rewrites</div>
                    <template x-for="p in assist.proposals" :key="proposalKey(p)">
                        <div class="card card-sm mb-2" x-show="! proposalState(p).skipped">
                            <div class="card-body">
                                <div class="small fw-bold">
                                    <span x-text="fieldLabel(p.field)"></span>
                                    <template x-if="p.field === 'visit_reason'">
                                        <span class="fw-normal text-secondary"> · repair order</span>
                                    </template>
                                    <template x-if="p.field === 'line_note'">
                                        <span class="fw-normal text-secondary">
                                            · line <span x-text="p.line_id"></span>
                                        </span>
                                    </template>
                                    <template x-if="p.field !== 'visit_reason' && p.field !== 'line_note'">
                                        <span class="fw-normal text-secondary">
                                            · concern <span x-text="p.concern_id"></span>
                                        </span>
                                    </template>
                                </div>
                                <template x-if="p.reason">
                                    <div class="small text-secondary mt-1" x-text="p.reason"></div>
                                </template>
                                <template x-if="proposalState(p).applied">
                                    <div class="small fw-bold text-success mt-2">Applied.</div>
                                </template>
                                <template x-if="! proposalState(p).applied">
                                    <div class="mt-2 small">
                                        <div class="mb-2">
                                            <div class="subheader">Original</div>
                                            <div class="text-secondary" style="white-space: pre-wrap" x-text="p.original_text"></div>
                                        </div>
                                        <div class="mb-2">
                                            <div class="subheader">Proposal</div>
                                            <template x-if="! proposalState(p).editing">
                                                <div style="white-space: pre-wrap" x-text="p.proposed_text"></div>
                                            </template>
                                            <template x-if="proposalState(p).editing">
                                                <textarea
                                                    x-model="proposalState(p).editedText"
                                                    rows="4"
                                                    class="form-control form-control-sm"
                                                    aria-label="Edited proposal"
                                                ></textarea>
                                            </template>
                                        </div>
                                        <template x-if="! p.applyable">
                                            <div class="text-orange" x-text="p.rejected_reason || 'Not applyable - critique only.'"></div>
                                        </template>
                                        <div x-show="p.applyable">
                                            <div class="btn-list">
                                                <button
                                                    type="button"
                                                    class="btn btn-sm btn-primary"
                                                    @click="applyProposal(p)"
                                                    :disabled="proposalState(p).applying"
                                                    x-text="proposalState(p).applying ? 'Applying…' : 'Apply'"
                                                ></button>
                                                <button
                                                    type="button"
                                                    class="btn btn-sm"
                                                    @click="proposalState(p).editing ? cancelEdit(p) : startEdit(p)"
                                                    x-text="proposalState(p).editing ? 'Cancel edit' : 'Edit then Apply'"
                                                ></button>
                                                <button
                                                    type="button"
                                                    class="btn btn-sm"
                                                    @click="skipProposal(p)"
                                                >Skip</button>
                                            </div>
                                        </div>
                                        <div class="text-danger mt-1" x-show="proposalState(p).error" x-text="proposalState(p).error"></div>
                                    </div>
                                </template>
                            </div>
                        </div>
                    </template>
                </div>
            </template>

            <p class="small text-secondary">Nothing changes until you Apply a proposal.</p>
            <button type="button" class="btn" @click="reset()">Review again</button>
        </div>
    </template>
</div>
@endif

@foreach ($repairOrder->concerns as $concern)

    <div
        data-workspace-modal-panel
        x-show="task === 'concern-narrative' && String(context.concernId) === '{{ $concern->id }}'"
        x-cloak
    >
        <form
            id="workspace-concern-narrative-{{ $concern->id }}"
            method="POST"
            action="{{ route('operations.repair-orders.concerns.update', [$repairOrder, $concern]) }}"
            data-workspace-modal-form="concern-narrative"
            data-worksheet-surface="concern-narrative-{{ $concern->id }}"
            data-refresh-scope="worksheet"
            data-saving-label="Saving…"
            @submit.prevent="submitWorksheetForm($event)"
        >
            @csrf
            @method('PATCH')
            <input type="hidden" name="{{ App\Ark\Operations\RepairOrders\RepairOrderConcurrency::FIELD }}" value="{{ $estimateVersion }}">
            <div
                class="row g-3"
                x-data="arkRoMention(@js(($priorVisitMentions['suggestions'] ?? [])))"
            >
                <div class="col-12 position-relative">
                    <label class="form-label" for="workspace-concern-summary-{{ $concern->id }}">Concern</label>
                    <input
                        id="workspace-concern-summary-{{ $concern->id }}"
                        name="summary"
                        x-ref="field"
                        value="{{ old('summary', $concern->summary) }}"
                        required
                        placeholder="The problem - e.g. Overheating, brake noise. Type @RO for a previous visit."
                        class="form-control"
                        @input="onInput()"
                        @keydown="onKeydown($event)"
                    >
                    @include('operations.repair-orders.partials.repair-order-mention-suggest-tabler')
                </div>
                <div class="col-12 col-md-6">
                    <label class="form-label" for="workspace-concern-priority-{{ $concern->id }}">Priority</label>
                    @include('operations.repair-orders.partials.recommendation-priority-select', [
                        'selectedPriority' => old('recommendation_posture', $concern->recommendationPriority()?->value),
                        'informationOnly' => (bool) old('information_only', $concern->isInformationOnly()),
                        'inputId' => 'workspace-concern-priority-'.$concern->id,
                        'selectClass' => 'form-select',
                    ])
                </div>
                <div class="col-12 col-md-6">
                    <label class="form-label" for="workspace-concern-intent-{{ $concern->id }}">Work type</label>
                    @include('operations.repair-orders.partials.recommendation-intent-select', [
                        'selected' => old('recommendation_intent', $concern->recommendationIntent()?->value),
                        'inputId' => 'workspace-concern-intent-'.$concern->id,
                        'selectClass' => 'form-select',
                    ])
                </div>
                <div class="col-12 col-md-6">
                    <label class="form-label" for="workspace-concern-customer-states-{{ $concern->id }}">Customer states</label>
                    <textarea id="workspace-concern-customer-states-{{ $concern->id }}" name="customer_states" rows="3" class="form-control" placeholder="What the customer reported…">{{ old('customer_states', $concern->customer_states) }}</textarea>
                </div>
                <div class="col-12 col-md-6">
                    <label class="form-label" for="workspace-concern-verified-findings-{{ $concern->id }}">Verified findings</label>
                    <textarea id="workspace-concern-verified-findings-{{ $concern->id }}" name="verified_findings" rows="3" class="form-control" placeholder="What was verified…">{{ old('verified_findings', $concern->verified_findings) }}</textarea>
                    @include('operations.repair-orders.partials.finding-from-verified-findings-nudge', [
                        'repairOrder' => $repairOrder,
                        'concern' => $concern,
                        'ui' => 'tabler',
                    ])
                </div>
                <div class="col-12 col-md-6">
                    <label class="form-label" for="workspace-concern-dtcs-{{ $concern->id }}">DTCs, if present</label>
                    <textarea id="workspace-concern-dtcs-{{ $concern->id }}" name="dtcs_summary" rows="2" class="form-control" placeholder="e.g. P0303 current">{{ old('dtcs_summary', $concern->dtcs_summary) }}</textarea>
                </div>
                <div class="col-12 col-md-6">
                    <label class="form-label" for="workspace-concern-recommendation-{{ $concern->id }}">Recommendation</label>
                    <textarea id="workspace-concern-recommendation-{{ $concern->id }}" name="recommendation" rows="2" class="form-control" placeholder="Recommended repair…">{{ old('recommendation', $concern->recommendation) }}</textarea>
                </div>
                <div class="col-12">
                    @include('operations.repair-orders.partials.repair-order-vehicle-memory', [
                        'visitInsert' => 'insertChip',
                    ])
                </div>
            </div>
        </form>
    </div>


    @php
        $dragonFieldOptions = [];
        $defaultDragonField = 'summary';
        if ($dragonReady) {
            $dragonFieldOptions = collect(\Ark\Saas\Dragon\ServiceAdvisor\ServiceAdvisorField::cases())
                ->filter(fn (\Ark\Saas\Dragon\ServiceAdvisor\ServiceAdvisorField $f) => $f->isConcernNarrative())
                ->map(fn (\Ark\Saas\Dragon\ServiceAdvisor\ServiceAdvisorField $f) => [
                    'value' => $f->value,
                    'label' => $f->label(),
                    'has_text' => filled(match ($f) {
                        \Ark\Saas\Dragon\ServiceAdvisor\ServiceAdvisorField::Summary => $concern->summary,
                        \Ark\Saas\Dragon\ServiceAdvisor\ServiceAdvisorField::CustomerStates => $concern->customer_states,
                        \Ark\Saas\Dragon\ServiceAdvisor\ServiceAdvisorField::VerifiedFindings => $concern->verified_findings,
                        \Ark\Saas\Dragon\ServiceAdvisor\ServiceAdvisorField::DtcsSummary => $concern->dtcs_summary,
                        \Ark\Saas\Dragon\ServiceAdvisor\ServiceAdvisorField::Recommendation => $concern->recommendation,
                    }),
                ])
                ->values()
                ->all();
            $defaultDragonField = collect($dragonFieldOptions)->firstWhere('value', 'verified_findings')['value']
                ?? ($dragonFieldOptions[0]['value'] ?? 'summary');
            $dragonModeOptions = collect(\Ark\Saas\Dragon\ServiceAdvisor\ServiceAdvisorMode::cases())
                ->map(fn ($mode) => ['value' => $mode->value, 'label' => $mode->label()])
                ->values()
                ->all();
        }
    @endphp

    @if ($dragonReady)
    <div
        data-workspace-modal-panel
        x-show="task === 'dragon-service-advisor' && String(context.concernId) === '{{ $concern->id }}'"
        x-cloak
        x-data="arkDragonServiceAdvisor({
            requestUrl: @js($dragonReady ? route('operations.repair-orders.concerns.dragon-service-advisor', [$repairOrder, $concern]) : null),
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
        <p class="small text-secondary mb-3" x-text="provenance"></p>

        <template x-if="phase === 'idle' || phase === 'generating' || phase === 'error'">
            <div>
                <div class="mb-3">
                    <label class="form-label" for="workspace-dragon-field-{{ $concern->id }}">Field</label>
                    <select id="workspace-dragon-field-{{ $concern->id }}" x-model="field" class="form-select">
                        <template x-for="opt in fields" :key="opt.value">
                            <option :value="opt.value" x-text="opt.label"></option>
                        </template>
                    </select>
                </div>
                <div class="mb-3">
                    <label class="form-label" for="workspace-dragon-mode-{{ $concern->id }}">Mode</label>
                    <select id="workspace-dragon-mode-{{ $concern->id }}" x-model="mode" class="form-select">
                        <template x-for="opt in modes" :key="opt.value">
                            <option :value="opt.value" x-text="opt.label"></option>
                        </template>
                    </select>
                </div>
                <button
                    type="button"
                    class="btn btn-primary"
                    @click="generate()"
                    :disabled="! canGenerate"
                    x-text="phase === 'generating' ? 'Dragon drafting…' : 'Generate'"
                ></button>
                <div class="text-danger small mt-2" x-show="errorMessage" x-text="errorMessage" x-cloak></div>
            </div>
        </template>

        <template x-if="phase === 'preview' || phase === 'applying'">
            <div>
                <div class="mb-3">
                    <div class="subheader mb-1">Original</div>
                    <div class="border rounded px-3 py-2 text-secondary" style="white-space: pre-wrap" x-text="originalText"></div>
                </div>
                <div class="mb-3">
                    <div class="subheader mb-1">Dragon proposal</div>
                    <template x-if="!editing">
                        <div class="border rounded px-3 py-2" style="white-space: pre-wrap" x-text="assist?.proposal"></div>
                    </template>
                    <template x-if="editing">
                        <textarea x-model="editedProposal" rows="6" class="form-control" aria-label="Edited proposal"></textarea>
                    </template>
                </div>
                <div class="btn-list">
                    <button type="button" class="btn btn-primary" @click="apply()" :disabled="phase === 'applying'" x-text="phase === 'applying' ? 'Applying…' : 'Apply'"></button>
                    <button type="button" class="btn" @click="editing ? cancelEdit() : startEdit()" x-text="editing ? 'Cancel edit' : 'Edit Proposal'"></button>
                    <button type="button" class="btn" @click="reset()">Cancel</button>
                </div>
                <div class="text-danger small mt-2" x-show="errorMessage" x-text="errorMessage" x-cloak></div>
            </div>
        </template>
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
            data-workspace-modal-panel
            x-show="task === 'repair-action-meta' && String(context.workGroupId) === '{{ $workGroup->id }}'"
            x-cloak
        >
            @if ($techniciansForMeta->isNotEmpty())
                <form
                    method="POST"
                    action="{{ route('operations.repair-orders.work-groups.owner.update', [$repairOrder, $workGroup]) }}"
                    data-workspace-modal-form="repair-action-meta"
                    data-workspace-modal-bundle="repair-action-meta"
                    data-refresh-scope="worksheet"
                    data-saving-label="Saving…"
                    @submit.prevent="submitWorksheetForm($event)"
                    class="mb-3"
                >
                    @csrf
                    @method('PATCH')
                    <input type="hidden" name="{{ App\Ark\Operations\RepairOrders\RepairOrderConcurrency::FIELD }}" value="{{ $estimateVersion }}">
                    <label class="form-label" for="workspace-repair-action-owner-{{ $workGroup->id }}">Owner</label>
                    <select
                        id="workspace-repair-action-owner-{{ $workGroup->id }}"
                        name="owner_user_id"
                        required
                        class="form-select"
                    >
                        <option value="" @selected($workGroup->owner_user_id === null) disabled>Unassigned</option>
                        @foreach ($techniciansForMeta as $technician)
                            <option value="{{ $technician->id }}" @selected((int) $workGroup->owner_user_id === (int) $technician->id)>{{ $technician->name }}</option>
                        @endforeach
                    </select>
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
            >
                @csrf
                @method('PATCH')
                <input type="hidden" name="{{ App\Ark\Operations\RepairOrders\RepairOrderConcurrency::FIELD }}" value="{{ $estimateVersion }}">
                <div class="mb-3">
                    <label class="form-label" for="workspace-repair-action-status-{{ $workGroup->id }}">Status</label>
                    <select
                        id="workspace-repair-action-status-{{ $workGroup->id }}"
                        name="status"
                        class="form-select"
                    >
                        @foreach ($actionStatuses as $statusOption)
                            <option value="{{ $statusOption->value }}" @selected($actionStatus === $statusOption)>{{ $statusOption->label() }}</option>
                        @endforeach
                    </select>
                </div>
                <label class="form-label" for="workspace-repair-action-update-{{ $workGroup->id }}">Update</label>
                <textarea
                    id="workspace-repair-action-update-{{ $workGroup->id }}"
                    name="latest_update"
                    rows="3"
                    maxlength="2000"
                    placeholder="Add an update…"
                    class="form-control"
                >{{ old('latest_update', $workGroup->latest_update) }}</textarea>
            </form>
        </div>
    @endforeach
@endforeach
