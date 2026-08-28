@if ($rteLaborGuide['available'] ?? false)
    <div
        x-show="rteLabor.open"
        x-cloak
        class="ops-workspace-modal"
        role="dialog"
        aria-modal="true"
        @keydown.escape.window="rteLabor.closePanel()"
    >
        <button
            type="button"
            class="ops-workspace-modal__backdrop"
            aria-label="Close"
            @click="rteLabor.closePanel()"
        ></button>
        <div
            class="ops-workspace-modal__dialog"
            style="width: min(96vw, 48rem); max-height: min(94dvh, 56rem); height: min(94dvh, 56rem);"
            x-show="rteLabor.open"
            x-transition:enter="ops-workspace-modal--enter"
            x-transition:enter-start="ops-workspace-modal--enter-start"
            x-transition:enter-end="ops-workspace-modal--enter-end"
            x-transition:leave="ops-workspace-modal--leave"
            x-transition:leave-start="ops-workspace-modal--leave-start"
            x-transition:leave-end="ops-workspace-modal--leave-end"
            @click.stop
        >
            {{-- Header --}}
            <header class="ops-workspace-modal__header">
                <div class="ops-workspace-modal__heading min-w-0">
                    <h2 class="ops-workspace-modal__title">{{ \App\Ark\Operations\LaborGuides\Rte\RepairTimeEngine::panelTitle() }}</h2>
                    <p class="ops-workspace-modal__helper truncate">
                        <span x-text="rteLabor.vehicleLabel"></span>
                        <span x-show="rteLabor.vehicleEngineLabel" x-text="` · ${rteLabor.vehicleEngineLabel}`"></span>
                    </p>
                </div>
                <button
                    type="button"
                    class="ops-workspace-modal__close"
                    @click="rteLabor.closePanel()"
                >
                    Close
                </button>
            </header>

            <div class="ops-workspace-modal__body flex min-h-0 flex-1 flex-col overflow-hidden !p-0">
            {{-- Compact controls (fixed) --}}
            <div class="shrink-0 space-y-2 border-b border-slate-200 px-3 py-2 sm:px-4 sm:py-2.5">
                <div class="grid gap-2 sm:grid-cols-2">
                    @if (count($rteLaborConcerns ?? []) > 1)
                        <label class="block text-[11px] font-semibold text-slate-700">
                            Scope
                            <select
                                x-model="rteLabor.concernId"
                                @change="rteLabor.searchJobs()"
                                class="mt-0.5 block w-full rounded-sm border border-slate-300 bg-white px-2 py-1.5 text-xs text-slate-950 sm:text-sm"
                            >
                                @foreach ($rteLaborConcerns as $concern)
                                    <option value="{{ $concern['id'] }}">{{ $concern['summary'] }}</option>
                                @endforeach
                            </select>
                        </label>
                    @endif

                    @if (count($rteLaborGuide['car_candidates'] ?? []) > 0)
                        <label class="block text-[11px] font-semibold text-slate-700 {{ count($rteLaborConcerns ?? []) <= 1 ? 'sm:col-span-2' : '' }}">
                            Vehicle configuration
                            <select
                                x-model="rteLabor.carIdCode"
                                @change="rteLabor.engIdCode = ''; rteLabor.searchJobs()"
                                class="mt-0.5 block w-full rounded-sm border border-slate-300 bg-white px-2 py-1.5 text-xs text-slate-950 sm:text-sm"
                            >
                                <template x-for="candidate in rteLabor.carCandidates" :key="`${candidate.car_id_code}-${candidate.lo_yr}-${candidate.hi_yr}`">
                                    <option
                                        :value="candidate.car_id_code"
                                        x-text="`${candidate.car_desc} (${candidate.lo_yr}-${candidate.hi_yr})`"
                                    ></option>
                                </template>
                            </select>
                        </label>
                    @endif
                </div>

                <div
                    x-show="rteLabor.vehicleMatch"
                    x-cloak
                    class="rounded-sm border px-2 py-1.5"
                    :class="{
                        'border-emerald-300 bg-emerald-50/80': rteLabor.vehicleMatchTone() === 'emerald',
                        'border-amber-300 bg-amber-50/80': rteLabor.vehicleMatchTone() === 'amber',
                        'border-rose-300 bg-rose-50/80': rteLabor.vehicleMatchTone() === 'rose',
                    }"
                >
                    <div class="flex flex-wrap items-center gap-x-2 gap-y-0.5 text-[11px] leading-snug">
                        <span class="font-bold text-slate-950">
                            Match <span x-text="rteLabor.vehicleMatchLabel()"></span>
                        </span>
                        <span class="text-slate-400">·</span>
                        <span class="text-slate-600" x-text="rteLabor.vehicleMatchExplanationSummary()"></span>
                    </div>
                    <p
                        x-show="rteLabor.selectedApplicationLabel()"
                        class="mt-0.5 truncate text-[11px] text-slate-700"
                    >
                        <span class="font-semibold text-slate-800">Using</span>
                        <span x-text="rteLabor.selectedApplicationLabel()"></span>
                    </p>
                    <div class="mt-1 flex flex-wrap items-center gap-x-3 gap-y-0.5 text-[11px]">
                        <span
                            x-show="rteLabor.vehicleMatchMatchedSummary()"
                            class="text-slate-700"
                            x-text="`✓ ${rteLabor.vehicleMatchMatchedSummary()}`"
                        ></span>
                        <span
                            x-show="rteLabor.vehicleMatchMissingSummary()"
                            class="text-slate-700"
                            x-text="`✗ ${rteLabor.vehicleMatchMissingSummary()}`"
                        ></span>
                        <button
                            type="button"
                            class="font-semibold underline decoration-slate-300 underline-offset-2 hover:text-slate-950"
                            @click="rteLabor.vehicleMatchDetailsOpen = ! rteLabor.vehicleMatchDetailsOpen"
                            x-text="rteLabor.vehicleMatchDetailsOpen ? 'Less' : 'More'"
                        ></button>
                    </div>
                    <div x-show="rteLabor.vehicleMatchDetailsOpen" x-cloak class="mt-1.5 space-y-1 border-t border-slate-200/80 pt-1.5 text-[11px] text-slate-600">
                        <p
                            x-show="rteLabor.vehicleMatch?.engine_assumption?.label"
                            class="font-medium text-slate-800"
                        >
                            Engine assumption:
                            <span x-text="rteLabor.vehicleMatch.engine_assumption.label"></span>
                            <span class="font-normal text-slate-600" x-text="` (${rteLabor.vehicleMatch.engine_assumption.source})`"></span>
                        </p>
                    </div>
                </div>

                <div
                    x-show="rteLabor.engineSelectionRequired() && rteLabor.hasEngineOptions()"
                    x-cloak
                    class="rounded-sm border border-amber-300 bg-amber-50 px-2 py-1.5"
                >
                    <p class="text-[11px] font-bold text-amber-950">Select engine before recommendations</p>
                    <div class="mt-1 flex flex-wrap gap-x-3 gap-y-1">
                        <template x-for="option in rteLabor.engineOptions" :key="option.eng_id_code">
                            <label class="inline-flex cursor-pointer items-center gap-1.5 text-[11px] text-slate-800">
                                <input
                                    type="radio"
                                    name="rte-engine-option"
                                    class="border-slate-300 text-slate-800 focus:ring-slate-500"
                                    :value="option.eng_id_code"
                                    :checked="rteLabor.engIdCode === option.eng_id_code"
                                    @change="rteLabor.selectEngine(option.eng_id_code)"
                                >
                                <span x-text="option.label"></span>
                            </label>
                        </template>
                    </div>
                </div>

                <form @submit.prevent="rteLabor.searchJobs()" class="flex gap-2">
                    <input
                        type="search"
                        x-model="rteLabor.query"
                        placeholder="Search jobs — brakes, water pump, A/C…"
                        class="min-w-0 flex-1 rounded-sm border border-slate-300 bg-white px-2 py-1.5 text-xs text-slate-950 placeholder:text-slate-400 sm:px-3 sm:py-2 sm:text-sm"
                    >
                    <button
                        type="submit"
                        class="shrink-0 rounded-sm bg-slate-800 px-3 py-1.5 text-xs font-semibold text-white hover:bg-slate-900"
                        :disabled="rteLabor.loading"
                    >
                        Search
                    </button>
                </form>

                <div class="flex flex-wrap gap-x-4 gap-y-1 text-[11px] text-slate-700">
                    <label class="inline-flex items-center gap-1.5">
                        <input type="checkbox" x-model="rteLabor.includeAddOns" class="rounded border-slate-300 text-slate-800 focus:ring-slate-500">
                        <span class="font-semibold text-slate-900">Related labor</span>
                    </label>
                    <label class="inline-flex items-center gap-1.5">
                        <input type="checkbox" x-model="rteLabor.includeVehicleAgePadding" class="rounded border-slate-300 text-slate-800 focus:ring-slate-500">
                        <span class="font-semibold text-slate-900" x-text="rteLabor.vehicleAgePaddingLabel()"></span>
                    </label>
                </div>

                <p x-show="rteLabor.error" x-text="rteLabor.error" class="text-xs font-semibold text-rose-700"></p>
            </div>

            {{-- Scrollable results --}}
            <div class="min-h-0 flex-1 overflow-y-auto overscroll-contain">
                <div
                    x-show="! rteLabor.loading && rteLabor.engineSelectionRequired() && rteLabor.hasEngineOptions()"
                    x-cloak
                    class="border-b border-amber-200 bg-amber-50 px-3 py-2 text-xs text-amber-950 sm:px-4"
                >
                    Select an engine above to unlock recommended labor.
                </div>

                <div
                    x-show="! rteLabor.loading && rteLabor.hasLaborPackage()"
                    class="border-b border-emerald-200 bg-emerald-50 px-3 py-2.5 sm:px-4"
                >
                    <div class="flex items-start justify-between gap-3">
                        <div class="min-w-0 flex-1">
                            <p class="text-[10px] font-semibold uppercase tracking-wide text-emerald-900">Recommended</p>
                            <p class="text-sm font-semibold text-slate-950" x-text="rteLabor.suggestedLabor.title"></p>

                            <template x-if="rteLabor.packageExplanation('avg')">
                                <div class="mt-1.5 space-y-1">
                                    <p class="text-base font-bold tabular-nums text-slate-950 sm:text-lg">
                                        <span x-text="`${rteLabor.formatHours(rteLabor.packageExplanation('avg').advisor_summary.total_hours)} hr`"></span>
                                        <span class="ml-1 text-xs font-semibold text-slate-700" x-text="rteLabor.packageExplanation('avg').advisor_summary.tier_label"></span>
                                    </p>
                                    <p
                                        x-show="rteLabor.packageExplanation('avg').advisor_summary.age_label"
                                        class="text-[11px] font-semibold text-emerald-900"
                                        x-text="rteLabor.packageExplanation('avg').advisor_summary.age_label"
                                    ></p>
                                    <p
                                        x-show="rteLabor.hasExplanationIncludes(rteLabor.packageExplanation('avg'))"
                                        class="text-[11px] text-slate-700"
                                        x-text="`Includes ${rteLabor.packageExplanation('avg').advisor_summary.includes.join(' · ')}`"
                                    ></p>
                                    <p
                                        x-show="rteLabor.hasPackageDiagnosticOverlap()"
                                        class="rounded-sm border border-amber-300 bg-amber-50 px-2 py-1 text-[11px] font-medium leading-snug text-amber-950"
                                        x-text="rteLabor.packageDiagnosticOverlap().advisor_summary.overlap_warning"
                                    ></p>
                                    <button
                                        type="button"
                                        class="text-[11px] font-semibold text-emerald-900 underline decoration-emerald-300 underline-offset-2 hover:text-emerald-950"
                                        @click="rteLabor.explanationDetailsOpen = ! rteLabor.explanationDetailsOpen"
                                        x-text="rteLabor.explanationDetailsOpen ? 'Hide details' : 'Details'"
                                    ></button>
                                    <div
                                        x-show="rteLabor.explanationDetailsOpen"
                                        x-cloak
                                        class="rounded-sm border border-emerald-200 bg-white px-2 py-1.5"
                                    >
                                        <template x-if="rteLabor.packageMatchAttribution()">
                                            <div class="space-y-1.5 border-b border-emerald-100 pb-1.5">
                                                <p class="text-[11px] font-semibold uppercase tracking-wide text-slate-700">Match attribution</p>
                                                <p
                                                    x-show="rteLabor.packageMatchAttribution().selected_application"
                                                    class="text-[11px] text-slate-600"
                                                >
                                                    <span class="font-semibold text-slate-800">Application:</span>
                                                    <span x-text="rteLabor.packageMatchAttribution().selected_application"></span>
                                                </p>
                                                <p class="text-[11px] text-slate-600">
                                                    <span class="font-semibold text-slate-800">Guide row:</span>
                                                    <span x-text="rteLabor.packageMatchAttribution().primary.guide_row"></span>
                                                    <span class="tabular-nums" x-text="` · ${rteLabor.formatHours(rteLabor.packageMatchAttribution().primary.guide_hours)} hr`"></span>
                                                </p>
                                                <ul x-show="rteLabor.packageMatchAttribution().adjustments.length > 0" class="space-y-0.5">
                                                    <template x-for="(adjustment, index) in rteLabor.packageMatchAttribution().adjustments" :key="`adj-${index}`">
                                                        <li class="text-[11px] tabular-nums text-slate-700" x-text="adjustment"></li>
                                                    </template>
                                                </ul>
                                                <ul x-show="rteLabor.packageMatchAttribution().related_operations.length > 0" class="space-y-0.5">
                                                    <template x-for="(related, index) in rteLabor.packageMatchAttribution().related_operations" :key="`rel-${index}`">
                                                        <li class="text-[11px] tabular-nums text-slate-700">
                                                            <span x-text="related.display_label"></span>
                                                            <span x-text="` +${rteLabor.formatHours(related.final_hours)}`"></span>
                                                        </li>
                                                    </template>
                                                </ul>
                                                <p
                                                    x-show="rteLabor.packageMatchAttribution().package_pooling"
                                                    class="text-[11px] text-slate-500"
                                                    x-text="rteLabor.packageMatchAttribution().package_pooling"
                                                ></p>
                                                <p class="text-[11px] font-semibold text-slate-900">
                                                    Final:
                                                    <span class="tabular-nums" x-text="rteLabor.formatHours(rteLabor.packageMatchAttribution().final_total)"></span>
                                                </p>
                                            </div>
                                        </template>
                                        <ul class="space-y-0.5" :class="rteLabor.packageMatchAttribution() ? 'mt-1.5' : ''">
                                            <template x-for="(line, index) in rteLabor.packageExplanation('avg').advisor_detail.lines" :key="`detail-${index}`">
                                                <li class="flex items-start justify-between gap-3 text-[11px] text-slate-700">
                                                    <span class="min-w-0" x-text="line.label"></span>
                                                    <span class="shrink-0 tabular-nums font-medium text-slate-900" x-text="rteLabor.formatHours(line.hours)"></span>
                                                </li>
                                            </template>
                                        </ul>
                                        <p
                                            x-show="rteLabor.packageExplanation('avg').advisor_detail.vehicle_age_years !== null"
                                            class="mt-1.5 text-[11px] text-slate-600"
                                            x-text="`Vehicle age ${rteLabor.packageExplanation('avg').advisor_detail.vehicle_age_years} yr · ${rteLabor.packageExplanation('avg').advisor_detail.tier_label}`"
                                        ></p>
                                        <div
                                            x-show="rteLabor.hasPackageDiagnosticOverlap()"
                                            class="mt-1.5 border-t border-emerald-100 pt-1.5 text-[11px] text-slate-600"
                                        >
                                            <span class="font-semibold text-amber-900">Overlap:</span>
                                            <span x-text="rteLabor.packageDiagnosticOverlap().advisor_detail.diagnostic_overlap.package_line.label"></span>
                                            vs RO
                                            <span x-text="rteLabor.packageDiagnosticOverlap().advisor_detail.diagnostic_overlap.existing_line.description"></span>
                                        </div>
                                    </div>
                                </div>
                            </template>
                            <div
                                x-show="rteLabor.hasOptionalDiagnosticOperations()"
                                x-cloak
                                class="mt-2 space-y-1.5 rounded-sm border border-slate-200 bg-white px-2 py-2"
                            >
                                <p class="text-[10px] font-semibold uppercase tracking-wide text-slate-600">Optional diagnostic operations</p>
                                <template x-for="(optional, index) in rteLabor.optionalDiagnosticOperations()" :key="`optional-diagnostic-${index}`">
                                    <label class="flex items-start gap-2 text-[11px] text-slate-700">
                                        <input
                                            type="checkbox"
                                            class="mt-0.5 rounded border-slate-300 text-emerald-700 focus:ring-emerald-500"
                                            :checked="rteLabor.isOptionalDiagnosticSelected(optional.lab_id)"
                                            @change="rteLabor.toggleOptionalDiagnostic(optional.lab_id)"
                                        >
                                        <span class="min-w-0">
                                            <span class="font-semibold text-slate-900" x-text="optional.description"></span>
                                            <span class="ml-1 tabular-nums text-slate-600" x-text="`${rteLabor.formatHours(rteLabor.displayHours(optional, 'avg'))} hr avg`"></span>
                                        </span>
                                    </label>
                                </template>
                            </div>
                        </div>
                        <div class="flex w-[8.75rem] shrink-0 flex-col gap-1">
                            <button
                                type="button"
                                class="w-full rounded-sm border border-emerald-300 bg-white px-2 py-1.5 text-center text-[11px] font-semibold tabular-nums text-emerald-900 hover:bg-emerald-100"
                                :disabled="rteLabor.applying || ! rteLabor.packageTotalHours('lo')"
                                @click="rteLabor.applySuggestedLabor('lo')"
                                x-text="rteLabor.applyButtonLabel('lo', rteLabor.packageTotalHours('lo'))"
                            ></button>
                            <button
                                type="button"
                                class="w-full rounded-sm border border-emerald-700 bg-emerald-700 px-2 py-1.5 text-center text-[11px] font-semibold tabular-nums text-white hover:bg-emerald-800"
                                :disabled="rteLabor.applying || ! rteLabor.packageTotalHours('avg')"
                                @click="rteLabor.applySuggestedLabor('avg')"
                                x-text="rteLabor.applyButtonLabel('avg', rteLabor.packageTotalHours('avg'))"
                            ></button>
                            <button
                                type="button"
                                class="w-full rounded-sm border border-emerald-300 bg-white px-2 py-1.5 text-center text-[11px] font-semibold tabular-nums text-emerald-900 hover:bg-emerald-100"
                                :disabled="rteLabor.applying || ! rteLabor.packageTotalHours('hi')"
                                @click="rteLabor.applySuggestedLabor('hi')"
                                x-text="rteLabor.applyButtonLabel('hi', rteLabor.packageTotalHours('hi'))"
                            ></button>
                        </div>
                    </div>
                </div>

                <table class="w-full table-fixed text-left text-sm">
                    <colgroup>
                        <col>
                        <col class="w-12">
                        <col class="w-12">
                        <col class="w-12">
                        <col class="w-[8.75rem]">
                    </colgroup>
                    <thead class="sticky top-0 z-[1] border-b border-slate-200 bg-slate-50 text-xs font-semibold uppercase tracking-wide text-slate-600">
                        <tr>
                            <th class="px-3 py-1.5 sm:px-4 sm:py-2">Job</th>
                            <th class="px-1 py-1.5 text-right sm:py-2">Lo</th>
                            <th class="px-1 py-1.5 text-right sm:py-2">Avg</th>
                            <th class="px-1 py-1.5 text-right sm:py-2">Hi</th>
                            <th class="px-3 py-1.5 text-right sm:py-2">Add</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr x-show="rteLabor.loading">
                            <td colspan="5" class="px-4 py-6 text-center text-slate-500">Loading labor times…</td>
                        </tr>
                        <tr x-show="! rteLabor.loading && ! rteLabor.hasRecommendedJob() && ! rteLabor.hasAlternateJobs()">
                            <td colspan="5" class="px-4 py-6 text-center text-slate-500">No labor times matched this vehicle.</td>
                        </tr>

                        <tr x-show="! rteLabor.loading && rteLabor.hasRecommendedJob() && ! rteLabor.hasLaborPackage()">
                            <td colspan="5" class="border-b border-sky-200 bg-sky-50 px-3 py-1.5 text-[10px] font-semibold uppercase tracking-wide text-sky-900 sm:px-4">
                                Recommended
                            </td>
                        </tr>
                        <tr
                            x-show="! rteLabor.loading && rteLabor.hasRecommendedJob() && ! rteLabor.hasLaborPackage()"
                            class="border-b border-sky-200 bg-sky-50/70 align-middle"
                        >
                            <td class="px-3 py-2 sm:px-4 sm:py-2.5">
                                <p class="font-semibold text-slate-900" x-text="rteLabor.recommendedJob.job_desc || rteLabor.recommendedJob.lab_id"></p>
                                <p
                                    class="mt-0.5 text-[11px] text-slate-600"
                                    x-show="rteLabor.recommendedJob.variant_label"
                                    x-text="rteLabor.recommendedJob.variant_label"
                                ></p>
                            </td>
                            <td class="px-1 py-2 text-right tabular-nums text-slate-700 sm:py-2.5">
                                <span x-text="rteLabor.formatHours(rteLabor.hoursForJob(rteLabor.recommendedJob, 'lo'))"></span>
                            </td>
                            <td class="px-1 py-2 text-right tabular-nums font-semibold text-slate-900 sm:py-2.5">
                                <span x-text="rteLabor.formatHours(rteLabor.hoursForJob(rteLabor.recommendedJob, 'avg'))"></span>
                            </td>
                            <td class="px-1 py-2 text-right tabular-nums text-slate-700 sm:py-2.5">
                                <span x-text="rteLabor.formatHours(rteLabor.hoursForJob(rteLabor.recommendedJob, 'hi'))"></span>
                            </td>
                            <td class="px-3 py-2 sm:py-2.5">
                                <div class="flex flex-col gap-1">
                                    <button
                                        type="button"
                                        class="w-full rounded-sm border border-slate-300 px-2 py-1 text-center text-[11px] font-semibold tabular-nums text-slate-700 hover:bg-slate-100"
                                        :disabled="rteLabor.applying || ! rteLabor.hoursForJob(rteLabor.recommendedJob, 'lo')"
                                        @click="rteLabor.applyJob(rteLabor.recommendedJob, 'lo')"
                                        x-text="rteLabor.applyButtonLabel('lo', rteLabor.hoursForJob(rteLabor.recommendedJob, 'lo'))"
                                    ></button>
                                    <button
                                        type="button"
                                        class="w-full rounded-sm border border-sky-700 bg-sky-700 px-2 py-1 text-center text-[11px] font-semibold tabular-nums text-white hover:bg-sky-800"
                                        :disabled="rteLabor.applying || ! rteLabor.hoursForJob(rteLabor.recommendedJob, 'avg')"
                                        @click="rteLabor.applyJob(rteLabor.recommendedJob, 'avg')"
                                        x-text="rteLabor.applyButtonLabel('avg', rteLabor.hoursForJob(rteLabor.recommendedJob, 'avg'))"
                                    ></button>
                                    <button
                                        type="button"
                                        class="w-full rounded-sm border border-slate-300 px-2 py-1 text-center text-[11px] font-semibold tabular-nums text-slate-700 hover:bg-slate-100"
                                        :disabled="rteLabor.applying || ! rteLabor.hoursForJob(rteLabor.recommendedJob, 'hi')"
                                        @click="rteLabor.applyJob(rteLabor.recommendedJob, 'hi')"
                                        x-text="rteLabor.applyButtonLabel('hi', rteLabor.hoursForJob(rteLabor.recommendedJob, 'hi'))"
                                    ></button>
                                </div>
                            </td>
                        </tr>

                        <tr x-show="! rteLabor.loading && rteLabor.hasAlternateJobs()">
                            <td colspan="5" class="border-b border-slate-200 bg-slate-100 px-3 py-1.5 text-[10px] font-semibold uppercase tracking-wide text-slate-600 sm:px-4">
                                Other options
                            </td>
                        </tr>

                        <template x-for="job in rteLabor.jobs" :key="job.lab_id">
                            <tr class="border-b border-slate-100 align-middle hover:bg-slate-50/80">
                                <td class="px-3 py-2 sm:px-4 sm:py-2.5">
                                    <p class="font-semibold text-slate-900" x-text="job.job_desc || job.lab_id"></p>
                                    <p
                                        class="mt-0.5 text-[11px] text-slate-600"
                                        x-show="job.variant_label"
                                        x-text="job.variant_label"
                                    ></p>
                                </td>
                                <td class="px-1 py-2 text-right tabular-nums text-slate-700 sm:py-2.5">
                                    <span x-text="rteLabor.formatHours(rteLabor.hoursForJob(job, 'lo'))"></span>
                                </td>
                                <td class="px-1 py-2 text-right tabular-nums font-semibold text-slate-900 sm:py-2.5">
                                    <span x-text="rteLabor.formatHours(rteLabor.hoursForJob(job, 'avg'))"></span>
                                </td>
                                <td class="px-1 py-2 text-right tabular-nums text-slate-700 sm:py-2.5">
                                    <span x-text="rteLabor.formatHours(rteLabor.hoursForJob(job, 'hi'))"></span>
                                </td>
                                <td class="px-3 py-2 sm:py-2.5">
                                    <div class="flex flex-col gap-1">
                                        <button
                                            type="button"
                                            class="w-full rounded-sm border border-slate-300 px-2 py-1 text-center text-[11px] font-semibold tabular-nums text-slate-700 hover:bg-slate-100"
                                            :disabled="rteLabor.applying || ! rteLabor.hoursForJob(job, 'lo')"
                                            @click="rteLabor.applyJob(job, 'lo')"
                                            x-text="rteLabor.applyButtonLabel('lo', rteLabor.hoursForJob(job, 'lo'))"
                                        ></button>
                                        <button
                                            type="button"
                                            class="w-full rounded-sm border border-slate-300 px-2 py-1 text-center text-[11px] font-semibold tabular-nums text-slate-700 hover:bg-slate-100"
                                            :disabled="rteLabor.applying || ! rteLabor.hoursForJob(job, 'avg')"
                                            @click="rteLabor.applyJob(job, 'avg')"
                                            x-text="rteLabor.applyButtonLabel('avg', rteLabor.hoursForJob(job, 'avg'))"
                                        ></button>
                                        <button
                                            type="button"
                                            class="w-full rounded-sm border border-slate-300 px-2 py-1 text-center text-[11px] font-semibold tabular-nums text-slate-700 hover:bg-slate-100"
                                            :disabled="rteLabor.applying || ! rteLabor.hoursForJob(job, 'hi')"
                                            @click="rteLabor.applyJob(job, 'hi')"
                                            x-text="rteLabor.applyButtonLabel('hi', rteLabor.hoursForJob(job, 'hi'))"
                                        ></button>
                                    </div>
                                </td>
                            </tr>
                        </template>
                    </tbody>
                </table>
            </div>

            <form
                id="rte-labor-apply-form"
                method="POST"
                :action="rteLabor.applyUrl"
                class="hidden"
            >
                @csrf
                <input type="hidden" :name="rteLabor.estimateVersionField" :value="rteLabor.estimateVersion">
                <input type="hidden" name="repair_order_concern_id" :value="rteLabor.concernId">
                <input type="hidden" name="car_id_code" :value="rteLabor.carIdCode">
                <input type="hidden" name="eng_id_code" :value="rteLabor.engIdCode">
                <input type="hidden" name="lab_id" :value="rteLabor.selectedLabId">
                <input type="hidden" name="hours_basis" :value="rteLabor.selectedBasis">
                <input type="hidden" name="include_add_ons" :value="rteLabor.includeAddOns ? 1 : 0">
                <input type="hidden" name="apply_vehicle_age_padding" :value="rteLabor.includeVehicleAgePadding ? 1 : 0">
                <input type="hidden" name="apply_suggested" :value="rteLabor.applySuggested ? 1 : 0">
                <input type="hidden" name="search_term" :value="rteLabor.selectedSearchTerm">
                <template x-for="labId in rteLabor.selectedOptionalDiagnosticLabIds" :key="`optional-diagnostic-${labId}`">
                    <input type="hidden" name="optional_diagnostic_lab_ids[]" :value="labId">
                </template>
            </form>
            </div>
        </div>
    </div>
@endif
