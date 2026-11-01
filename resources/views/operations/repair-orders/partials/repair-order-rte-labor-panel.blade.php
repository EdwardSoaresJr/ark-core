@if ($rteLaborGuide['available'] ?? false)
    <div x-show="rteLabor.open" x-cloak data-rte-labor-panel>
        <div class="modal-backdrop show"></div>
        <div
            class="modal d-block"
            role="dialog"
            aria-modal="true"
            aria-labelledby="rte-labor-panel-title"
            @keydown.escape.window="rteLabor.closePanel()"
            @click.self="rteLabor.closePanel()"
        >
            <div class="modal-dialog modal-lg modal-dialog-scrollable modal-fullscreen-sm-down" @click.self="rteLabor.closePanel()">
                <div class="modal-content">
                    <div class="modal-header">
                        <div class="overflow-hidden">
                            <h2 class="modal-title" id="rte-labor-panel-title">{{ \App\Ark\Operations\LaborGuides\Rte\RepairTimeEngine::panelTitle() }}</h2>
                            <div class="small text-secondary text-truncate">
                                <span x-text="rteLabor.vehicleLabel"></span>
                                <span x-show="rteLabor.vehicleEngineLabel" x-text="` · ${rteLabor.vehicleEngineLabel}`"></span>
                            </div>
                        </div>
                        <button type="button" class="btn-close" aria-label="Close" @click="rteLabor.closePanel()"></button>
                    </div>

                    {{-- Compact controls stay above the scrolling results. --}}
                    <div class="modal-body flex-grow-0 flex-shrink-0 border-bottom py-2">
                        <div class="row g-2 mb-2">
                            @if (count($rteLaborConcerns ?? []) > 1)
                                <div class="col-sm-6">
                                    <label class="form-label small mb-1" for="rte-labor-scope">Scope</label>
                                    <select
                                        id="rte-labor-scope"
                                        x-model="rteLabor.concernId"
                                        @change="rteLabor.searchJobs()"
                                        class="form-select form-select-sm"
                                    >
                                        @foreach ($rteLaborConcerns as $concern)
                                            <option value="{{ $concern['id'] }}">{{ $concern['summary'] }}</option>
                                        @endforeach
                                    </select>
                                </div>
                            @endif

                            @if (count($rteLaborGuide['car_candidates'] ?? []) > 0)
                                <div class="{{ count($rteLaborConcerns ?? []) <= 1 ? 'col-12' : 'col-sm-6' }}">
                                    <label class="form-label small mb-1" for="rte-labor-vehicle">Vehicle configuration</label>
                                    <select
                                        id="rte-labor-vehicle"
                                        x-model="rteLabor.carIdCode"
                                        @change="rteLabor.engIdCode = ''; rteLabor.searchJobs()"
                                        class="form-select form-select-sm"
                                    >
                                        <template x-for="candidate in rteLabor.carCandidates" :key="`${candidate.car_id_code}-${candidate.lo_yr}-${candidate.hi_yr}`">
                                            <option
                                                :value="candidate.car_id_code"
                                                x-text="`${candidate.car_desc} (${candidate.lo_yr}-${candidate.hi_yr})`"
                                            ></option>
                                        </template>
                                    </select>
                                </div>
                            @endif
                        </div>

                        <div x-show="rteLabor.vehicleMatch" x-cloak>
                            <div
                                class="alert small py-2 mb-2"
                                :class="{
                                    'alert-success': rteLabor.vehicleMatchTone() === 'emerald',
                                    'alert-warning': rteLabor.vehicleMatchTone() === 'amber',
                                    'alert-danger': rteLabor.vehicleMatchTone() === 'rose',
                                }"
                            >
                                <div class="d-flex flex-wrap align-items-center gap-2">
                                    <span class="fw-bold">
                                        Match <span x-text="rteLabor.vehicleMatchLabel()"></span>
                                    </span>
                                    <span class="text-secondary" aria-hidden="true">·</span>
                                    <span class="text-secondary" x-text="rteLabor.vehicleMatchExplanationSummary()"></span>
                                </div>
                                <div x-show="rteLabor.selectedApplicationLabel()" class="text-truncate mt-1">
                                    <span class="fw-medium">Using</span>
                                    <span x-text="rteLabor.selectedApplicationLabel()"></span>
                                </div>
                                <div class="d-flex flex-wrap align-items-center gap-3 mt-1">
                                    <span
                                        x-show="rteLabor.vehicleMatchMatchedSummary()"
                                        x-text="`✓ ${rteLabor.vehicleMatchMatchedSummary()}`"
                                    ></span>
                                    <span
                                        x-show="rteLabor.vehicleMatchMissingSummary()"
                                        x-text="`✗ ${rteLabor.vehicleMatchMissingSummary()}`"
                                    ></span>
                                    <button
                                        type="button"
                                        class="btn btn-link btn-sm p-0"
                                        @click="rteLabor.vehicleMatchDetailsOpen = ! rteLabor.vehicleMatchDetailsOpen"
                                        :aria-expanded="rteLabor.vehicleMatchDetailsOpen"
                                        x-text="rteLabor.vehicleMatchDetailsOpen ? 'Less' : 'More'"
                                    ></button>
                                </div>
                                <div x-show="rteLabor.vehicleMatchDetailsOpen" x-cloak class="border-top mt-2 pt-2">
                                    <p x-show="rteLabor.vehicleMatch?.engine_assumption?.label" class="fw-medium mb-0">
                                        Engine assumption:
                                        <span x-text="rteLabor.vehicleMatch?.engine_assumption?.label"></span>
                                        <span class="fw-normal text-secondary" x-text="rteLabor.vehicleMatch?.engine_assumption?.source ? ` (${rteLabor.vehicleMatch.engine_assumption.source})` : ''"></span>
                                    </p>
                                </div>
                            </div>
                        </div>

                        <div x-show="rteLabor.engineSelectionRequired() && rteLabor.hasEngineOptions()" x-cloak>
                            <fieldset class="alert alert-warning small py-2 mb-2">
                                <legend class="fw-bold fs-5 mb-1">Select engine before recommendations</legend>
                                <div class="d-flex flex-wrap gap-3">
                                    <template x-for="option in rteLabor.engineOptions" :key="option.eng_id_code">
                                        <label class="form-check form-check-inline mb-0">
                                            <input
                                                type="radio"
                                                name="rte-engine-option"
                                                class="form-check-input"
                                                :value="option.eng_id_code"
                                                :checked="rteLabor.engIdCode === option.eng_id_code"
                                                @change="rteLabor.selectEngine(option.eng_id_code)"
                                            >
                                            <span class="form-check-label" x-text="option.label"></span>
                                        </label>
                                    </template>
                                </div>
                            </fieldset>
                        </div>

                        <form @submit.prevent="rteLabor.searchJobs()" class="input-group mb-2" role="search">
                            <input
                                type="search"
                                x-model="rteLabor.query"
                                placeholder="Search jobs - brakes, water pump, A/C…"
                                aria-label="Search labor jobs"
                                class="form-control"
                            >
                            <button
                                type="submit"
                                class="btn btn-primary"
                                :disabled="rteLabor.loading"
                            >
                                Search
                            </button>
                        </form>

                        <div class="d-flex flex-wrap gap-3 small">
                            <label class="form-check mb-0">
                                <input type="checkbox" x-model="rteLabor.includeAddOns" class="form-check-input">
                                <span class="form-check-label fw-medium">Related labor</span>
                            </label>
                            <label class="form-check mb-0">
                                <input type="checkbox" x-model="rteLabor.includeVehicleAgePadding" class="form-check-input">
                                <span class="form-check-label fw-medium" x-text="rteLabor.vehicleAgePaddingLabel()"></span>
                            </label>
                        </div>

                        <p x-show="rteLabor.error" x-text="rteLabor.error" class="small text-danger fw-medium mt-2 mb-0" role="alert"></p>
                    </div>

                    {{-- Scrollable results --}}
                    <div class="modal-body p-0">
                        <div x-show="! rteLabor.loading && rteLabor.engineSelectionRequired() && rteLabor.hasEngineOptions()" x-cloak>
                            <div class="alert alert-warning rounded-0 border-0 border-bottom small mb-0">
                                Select an engine above to unlock recommended labor.
                            </div>
                        </div>

                        <div x-show="! rteLabor.loading && rteLabor.hasLaborPackage()">
                            <div class="card card-sm rounded-0 border-0 border-bottom bg-green-lt">
                                <div class="card-body">
                                    <div class="row g-3">
                                        <div class="col min-w-0">
                                            <div class="subheader text-green">Recommended</div>
                                            <div class="fw-bold" x-text="rteLabor.suggestedLabor?.title"></div>

                                            <template x-if="rteLabor.packageExplanation('avg')">
                                                <div class="mt-2">
                                                    <p class="h3 mb-1 ark-money">
                                                        <span x-text="`${rteLabor.formatHours(rteLabor.packageExplanation('avg').advisor_summary.total_hours)} hr`"></span>
                                                        <span class="ms-1 small fw-medium text-secondary" x-text="rteLabor.packageExplanation('avg').advisor_summary.tier_label"></span>
                                                    </p>
                                                    <p
                                                        x-show="rteLabor.packageExplanation('avg').advisor_summary.age_label"
                                                        class="small fw-medium text-green mb-1"
                                                        x-text="rteLabor.packageExplanation('avg').advisor_summary.age_label"
                                                    ></p>
                                                    <p
                                                        x-show="rteLabor.hasExplanationIncludes(rteLabor.packageExplanation('avg'))"
                                                        class="small text-secondary mb-1"
                                                        x-text="`Includes ${rteLabor.packageExplanation('avg').advisor_summary.includes.join(' · ')}`"
                                                    ></p>
                                                    <div x-show="rteLabor.hasPackageDiagnosticOverlap()">
                                                        <p
                                                            class="alert alert-warning small py-1 px-2 mb-1"
                                                            x-text="rteLabor.packageDiagnosticOverlap().advisor_summary.overlap_warning"
                                                        ></p>
                                                    </div>
                                                    <button
                                                        type="button"
                                                        class="btn btn-link btn-sm p-0"
                                                        @click="rteLabor.explanationDetailsOpen = ! rteLabor.explanationDetailsOpen"
                                                        :aria-expanded="rteLabor.explanationDetailsOpen"
                                                        x-text="rteLabor.explanationDetailsOpen ? 'Hide details' : 'Details'"
                                                    ></button>
                                                    <div x-show="rteLabor.explanationDetailsOpen" x-cloak>
                                                        <div class="card card-sm mt-2">
                                                            <div class="card-body small">
                                                                <template x-if="rteLabor.packageMatchAttribution()">
                                                                    <div class="border-bottom pb-2 mb-2">
                                                                        <div class="subheader mb-1">Match attribution</div>
                                                                        <p x-show="rteLabor.packageMatchAttribution().selected_application" class="text-secondary mb-1">
                                                                            <span class="fw-medium text-body">Application:</span>
                                                                            <span x-text="rteLabor.packageMatchAttribution().selected_application"></span>
                                                                        </p>
                                                                        <p class="text-secondary mb-1">
                                                                            <span class="fw-medium text-body">Guide row:</span>
                                                                            <span x-text="rteLabor.packageMatchAttribution().primary.guide_row"></span>
                                                                            <span class="ark-money" x-text="` · ${rteLabor.formatHours(rteLabor.packageMatchAttribution().primary.guide_hours)} hr`"></span>
                                                                        </p>
                                                                        <ul x-show="rteLabor.packageMatchAttribution().adjustments.length > 0" class="list-unstyled mb-1">
                                                                            <template x-for="(adjustment, index) in rteLabor.packageMatchAttribution().adjustments" :key="`adj-${index}`">
                                                                                <li class="ark-money" x-text="adjustment"></li>
                                                                            </template>
                                                                        </ul>
                                                                        <ul x-show="rteLabor.packageMatchAttribution().related_operations.length > 0" class="list-unstyled mb-1">
                                                                            <template x-for="(related, index) in rteLabor.packageMatchAttribution().related_operations" :key="`rel-${index}`">
                                                                                <li class="ark-money">
                                                                                    <span x-text="related.display_label"></span>
                                                                                    <span x-text="` +${rteLabor.formatHours(related.final_hours)}`"></span>
                                                                                </li>
                                                                            </template>
                                                                        </ul>
                                                                        <p
                                                                            x-show="rteLabor.packageMatchAttribution().package_pooling"
                                                                            class="text-secondary mb-1"
                                                                            x-text="rteLabor.packageMatchAttribution().package_pooling"
                                                                        ></p>
                                                                        <p class="fw-bold mb-0">
                                                                            Final:
                                                                            <span class="ark-money" x-text="rteLabor.formatHours(rteLabor.packageMatchAttribution().final_total)"></span>
                                                                        </p>
                                                                    </div>
                                                                </template>
                                                                <ul class="list-unstyled mb-0">
                                                                    <template x-for="(line, index) in rteLabor.packageExplanation('avg').advisor_detail.lines" :key="`detail-${index}`">
                                                                        <li class="d-flex align-items-start justify-content-between gap-3">
                                                                            <span class="min-w-0" x-text="line.label"></span>
                                                                            <span class="flex-shrink-0 fw-medium ark-money" x-text="rteLabor.formatHours(line.hours)"></span>
                                                                        </li>
                                                                    </template>
                                                                </ul>
                                                                <p
                                                                    x-show="rteLabor.packageExplanation('avg').advisor_detail.vehicle_age_years !== null"
                                                                    class="text-secondary mt-2 mb-0"
                                                                    x-text="`Vehicle age ${rteLabor.packageExplanation('avg').advisor_detail.vehicle_age_years} yr · ${rteLabor.packageExplanation('avg').advisor_detail.tier_label}`"
                                                                ></p>
                                                                <div x-show="rteLabor.hasPackageDiagnosticOverlap()" class="text-secondary border-top mt-2 pt-2">
                                                                    <span class="fw-bold text-warning">Overlap:</span>
                                                                    <span x-text="rteLabor.packageDiagnosticOverlap().advisor_detail.diagnostic_overlap.package_line.label"></span>
                                                                    vs RO
                                                                    <span x-text="rteLabor.packageDiagnosticOverlap().advisor_detail.diagnostic_overlap.existing_line.description"></span>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                            </template>
                                            <div x-show="rteLabor.hasOptionalDiagnosticOperations()" x-cloak>
                                                <fieldset class="card card-sm mt-2">
                                                    <div class="card-body small">
                                                        <legend class="subheader mb-1">Optional diagnostic operations</legend>
                                                        <template x-for="(optional, index) in rteLabor.optionalDiagnosticOperations()" :key="`optional-diagnostic-${index}`">
                                                            <label class="form-check mb-1">
                                                                <input
                                                                    type="checkbox"
                                                                    class="form-check-input"
                                                                    :checked="rteLabor.isOptionalDiagnosticSelected(optional.lab_id)"
                                                                    @change="rteLabor.toggleOptionalDiagnostic(optional.lab_id)"
                                                                >
                                                                <span class="form-check-label">
                                                                    <span class="fw-medium" x-text="optional.description"></span>
                                                                    <span class="ms-1 text-secondary ark-money" x-text="`${rteLabor.formatHours(rteLabor.displayHours(optional, 'avg'))} hr avg`"></span>
                                                                </span>
                                                            </label>
                                                        </template>
                                                    </div>
                                                </fieldset>
                                            </div>
                                        </div>
                                        <div class="col-12 col-sm-auto">
                                            <div class="d-grid gap-1" style="min-width: 8.75rem">
                                                <button
                                                    type="button"
                                                    class="btn btn-sm btn-outline-green ark-money"
                                                    :disabled="rteLabor.applying || ! rteLabor.packageTotalHours('lo')"
                                                    @click="rteLabor.applySuggestedLabor('lo')"
                                                    x-text="rteLabor.applyButtonLabel('lo', rteLabor.packageTotalHours('lo'))"
                                                ></button>
                                                <button
                                                    type="button"
                                                    class="btn btn-sm btn-green ark-money"
                                                    :disabled="rteLabor.applying || ! rteLabor.packageTotalHours('avg')"
                                                    @click="rteLabor.applySuggestedLabor('avg')"
                                                    x-text="rteLabor.applyButtonLabel('avg', rteLabor.packageTotalHours('avg'))"
                                                ></button>
                                                <button
                                                    type="button"
                                                    class="btn btn-sm btn-outline-green ark-money"
                                                    :disabled="rteLabor.applying || ! rteLabor.packageTotalHours('hi')"
                                                    @click="rteLabor.applySuggestedLabor('hi')"
                                                    x-text="rteLabor.applyButtonLabel('hi', rteLabor.packageTotalHours('hi'))"
                                                ></button>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <table class="table table-vcenter card-table mb-0">
                            <thead class="sticky-top">
                                <tr>
                                    <th>Job</th>
                                    <th class="text-end">Lo</th>
                                    <th class="text-end">Avg</th>
                                    <th class="text-end">Hi</th>
                                    <th class="text-end" style="width: 8.75rem">Add</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr x-show="rteLabor.loading">
                                    <td colspan="5" class="text-center text-secondary py-4">Loading labor times…</td>
                                </tr>
                                <tr x-show="! rteLabor.loading && ! rteLabor.hasRecommendedJob() && ! rteLabor.hasAlternateJobs()">
                                    <td colspan="5" class="text-center text-secondary py-4">No labor times matched this vehicle.</td>
                                </tr>

                                <tr x-show="! rteLabor.loading && rteLabor.hasRecommendedJob() && ! rteLabor.hasLaborPackage()">
                                    <td colspan="5" class="subheader bg-azure-lt">Recommended</td>
                                </tr>
                                <tr x-show="! rteLabor.loading && rteLabor.hasRecommendedJob() && ! rteLabor.hasLaborPackage()" class="bg-azure-lt">
                                    <td>
                                        <div class="fw-medium" x-text="rteLabor.recommendedJob?.job_desc || rteLabor.recommendedJob?.lab_id"></div>
                                        <div
                                            class="small text-secondary"
                                            x-show="rteLabor.recommendedJob?.variant_label"
                                            x-text="rteLabor.recommendedJob?.variant_label"
                                        ></div>
                                    </td>
                                    <td class="text-end ark-money text-secondary">
                                        <span x-text="rteLabor.recommendedJob ? rteLabor.formatHours(rteLabor.hoursForJob(rteLabor.recommendedJob, 'lo')) : ''"></span>
                                    </td>
                                    <td class="text-end ark-money fw-bold">
                                        <span x-text="rteLabor.recommendedJob ? rteLabor.formatHours(rteLabor.hoursForJob(rteLabor.recommendedJob, 'avg')) : ''"></span>
                                    </td>
                                    <td class="text-end ark-money text-secondary">
                                        <span x-text="rteLabor.recommendedJob ? rteLabor.formatHours(rteLabor.hoursForJob(rteLabor.recommendedJob, 'hi')) : ''"></span>
                                    </td>
                                    <td>
                                        <div class="d-grid gap-1">
                                            <button
                                                type="button"
                                                class="btn btn-sm ark-money"
                                                :disabled="rteLabor.applying || ! rteLabor.recommendedJob || ! rteLabor.hoursForJob(rteLabor.recommendedJob, 'lo')"
                                                @click="rteLabor.applyJob(rteLabor.recommendedJob, 'lo')"
                                                x-text="rteLabor.applyButtonLabel('lo', rteLabor.recommendedJob ? rteLabor.hoursForJob(rteLabor.recommendedJob, 'lo') : null)"
                                            ></button>
                                            <button
                                                type="button"
                                                class="btn btn-sm btn-azure ark-money"
                                                :disabled="rteLabor.applying || ! rteLabor.recommendedJob || ! rteLabor.hoursForJob(rteLabor.recommendedJob, 'avg')"
                                                @click="rteLabor.applyJob(rteLabor.recommendedJob, 'avg')"
                                                x-text="rteLabor.applyButtonLabel('avg', rteLabor.recommendedJob ? rteLabor.hoursForJob(rteLabor.recommendedJob, 'avg') : null)"
                                            ></button>
                                            <button
                                                type="button"
                                                class="btn btn-sm ark-money"
                                                :disabled="rteLabor.applying || ! rteLabor.recommendedJob || ! rteLabor.hoursForJob(rteLabor.recommendedJob, 'hi')"
                                                @click="rteLabor.applyJob(rteLabor.recommendedJob, 'hi')"
                                                x-text="rteLabor.applyButtonLabel('hi', rteLabor.recommendedJob ? rteLabor.hoursForJob(rteLabor.recommendedJob, 'hi') : null)"
                                            ></button>
                                        </div>
                                    </td>
                                </tr>

                                <tr x-show="! rteLabor.loading && rteLabor.hasAlternateJobs()">
                                    <td colspan="5" class="subheader bg-secondary-lt">Other options</td>
                                </tr>

                                <template x-for="job in rteLabor.jobs" :key="job.lab_id">
                                    <tr>
                                        <td>
                                            <div class="fw-medium" x-text="job.job_desc || job.lab_id"></div>
                                            <div
                                                class="small text-secondary"
                                                x-show="job.variant_label"
                                                x-text="job.variant_label"
                                            ></div>
                                        </td>
                                        <td class="text-end ark-money text-secondary">
                                            <span x-text="rteLabor.formatHours(rteLabor.hoursForJob(job, 'lo'))"></span>
                                        </td>
                                        <td class="text-end ark-money fw-bold">
                                            <span x-text="rteLabor.formatHours(rteLabor.hoursForJob(job, 'avg'))"></span>
                                        </td>
                                        <td class="text-end ark-money text-secondary">
                                            <span x-text="rteLabor.formatHours(rteLabor.hoursForJob(job, 'hi'))"></span>
                                        </td>
                                        <td>
                                            <div class="d-grid gap-1">
                                                <button
                                                    type="button"
                                                    class="btn btn-sm ark-money"
                                                    :disabled="rteLabor.applying || ! rteLabor.hoursForJob(job, 'lo')"
                                                    @click="rteLabor.applyJob(job, 'lo')"
                                                    x-text="rteLabor.applyButtonLabel('lo', rteLabor.hoursForJob(job, 'lo'))"
                                                ></button>
                                                <button
                                                    type="button"
                                                    class="btn btn-sm ark-money"
                                                    :disabled="rteLabor.applying || ! rteLabor.hoursForJob(job, 'avg')"
                                                    @click="rteLabor.applyJob(job, 'avg')"
                                                    x-text="rteLabor.applyButtonLabel('avg', rteLabor.hoursForJob(job, 'avg'))"
                                                ></button>
                                                <button
                                                    type="button"
                                                    class="btn btn-sm ark-money"
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
                        class="d-none"
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
    </div>
@endif
