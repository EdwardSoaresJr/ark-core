export function arkRteLaborGuide(config = {}) {
    return {
        open: false,
        loading: false,
        applying: false,
        query: '',
        error: '',
        vehicleLabel: config.vehicleLabel || '',
        vehicleEngineLabel: config.vehicleEngineLabel || '',
        modelYear: config.modelYear || null,
        carIdCode: config.defaultCarIdCode || '',
        carCandidates: config.carCandidates || [],
        engIdCode: config.engIdCode || '',
        engineOptions: config.engineOptions || [],
        vehicleMatch: config.vehicleMatch || null,
        concernId: config.defaultConcernId || (config.concerns?.[0]?.id ?? null),
        concerns: config.concerns || [],
        jobs: [],
        recommendedJob: null,
        suggestedLabor: null,
        includeAddOns: true,
        includeVehicleAgePadding: true,
        selectedOptionalDiagnosticLabIds: [],
        vehicleAgeMultiplier: 1.0,
        explanationDetailsOpen: false,
        vehicleMatchDetailsOpen: false,
        selectedLabId: '',
        selectedBasis: config.defaultHoursBasis || 'avg',
        applySuggested: false,
        selectedSearchTerm: '',
        searchUrl: config.searchUrl || '',
        applyUrl: config.applyUrl || '',
        estimateVersionField: config.estimateVersionField || '',
        estimateVersion: config.estimateVersion || '',

        openPanel() {
            if (! config.available) {
                if (config.blockedReason) {
                    window.dispatchEvent(new CustomEvent('ark:labor-guide-notice', {
                        detail: { message: config.blockedReason },
                    }));
                }

                return;
            }

            this.open = true;
            this.error = '';
            document.body.classList.add('overflow-hidden');

            if (this.jobs.length === 0) {
                this.searchJobs();
            }
        },

        closePanel() {
            this.open = false;
            this.error = '';
            document.body.classList.remove('overflow-hidden');
        },

        async searchJobs() {
            if (! this.searchUrl || ! this.carIdCode) {
                return;
            }

            this.loading = true;
            this.error = '';

            try {
                const url = new URL(this.searchUrl, window.location.origin);
                url.searchParams.set('car_id_code', this.carIdCode);

                if (this.concernId) {
                    url.searchParams.set('concern_id', String(this.concernId));
                }

                if (this.engIdCode) {
                    url.searchParams.set('eng_id_code', this.engIdCode);
                }

                if (this.query.trim() !== '') {
                    url.searchParams.set('q', this.query.trim());
                }

                const response = await fetch(url.toString(), {
                    headers: { Accept: 'application/json' },
                    credentials: 'same-origin',
                });

                const payload = await response.json().catch(() => ({}));

                if (! response.ok || payload.available === false) {
                    this.jobs = [];
                    this.recommendedJob = null;
                    this.suggestedLabor = null;
                    this.error = payload.message || 'Could not load labor times.';

                    return;
                }

                this.vehicleLabel = payload.vehicle_label || this.vehicleLabel;
                this.vehicleEngineLabel = payload.vehicle_engine_label || this.vehicleEngineLabel;
                this.modelYear = payload.model_year ?? this.modelYear;
                this.vehicleAgeMultiplier = payload.vehicle_age_multiplier ?? 1.0;
                this.carIdCode = payload.car_id_code || this.carIdCode;
                this.carCandidates = payload.car_candidates || this.carCandidates;
                this.engIdCode = payload.eng_id_code || '';
                this.engineOptions = payload.engine_options || [];
                this.vehicleMatch = payload.vehicle_match || null;
                this.recommendedJob = payload.recommended_job || null;
                this.suggestedLabor = payload.suggested_labor || null;
                this.selectedOptionalDiagnosticLabIds = [];
                this.jobs = payload.jobs || [];
                this.explanationDetailsOpen = false;
                this.vehicleMatchDetailsOpen = false;
            } catch {
                this.error = 'Could not reach ARK to search labor times.';
                this.jobs = [];
                this.recommendedJob = null;
                this.suggestedLabor = null;
            } finally {
                this.loading = false;
            }
        },

        applyJob(job, basis) {
            if (! this.applyUrl || ! this.concernId || this.applying) {
                if (! this.concernId) {
                    this.error = 'Choose which scope should receive this labor line.';
                }

                return;
            }

            this.selectedLabId = job.lab_id;
            this.selectedBasis = basis;
            this.applySuggested = false;
            this.selectedSearchTerm = '';
            this.applying = true;

            queueMicrotask(() => {
                document.getElementById('rte-labor-apply-form')?.requestSubmit();
            });
        },

        applySuggestedLabor(basis) {
            if (! this.applyUrl || ! this.concernId || this.applying || ! this.hasSuggestedLabor()) {
                return;
            }

            this.selectedLabId = this.suggestedLabor.primary_lab_id;
            this.selectedBasis = basis;
            this.applySuggested = true;
            this.selectedSearchTerm = this.suggestedLabor.search_term || this.query.trim();
            this.applying = true;

            queueMicrotask(() => {
                document.getElementById('rte-labor-apply-form')?.requestSubmit();
            });
        },

        hasSuggestedLabor() {
            return this.suggestedLabor !== null
                && Array.isArray(this.suggestedLabor.lines)
                && this.suggestedLabor.lines.length > 0
                && ! this.engineSelectionRequired();
        },

        engineSelectionRequired() {
            return Boolean(this.vehicleMatch?.engine_selection_required);
        },

        hasEngineOptions() {
            return Array.isArray(this.engineOptions) && this.engineOptions.length > 1;
        },

        selectEngine(engIdCode) {
            this.engIdCode = engIdCode;
            this.searchJobs();
        },

        vehicleMatchLabel() {
            return this.vehicleMatch?.confidence_label || null;
        },

        vehicleMatchTone() {
            const level = this.vehicleMatch?.confidence;

            if (level === 'high') {
                return 'emerald';
            }

            if (level === 'medium') {
                return 'amber';
            }

            return 'rose';
        },

        vehicleMatchFieldLabel(field) {
            const labels = {
                year: 'Year',
                make: 'Make',
                model: 'Model',
                drive_type: 'Drive',
                engine: 'Engine',
                vin: 'VIN',
            };

            return labels[field] || field;
        },

        selectedApplicationLabel() {
            const selected = (this.carCandidates || []).find(
                (candidate) => candidate.car_id_code === this.carIdCode,
            );

            if (selected) {
                return `${selected.car_desc} (${selected.lo_yr}-${selected.hi_yr})`;
            }

            return this.vehicleMatch?.application_label || null;
        },

        vehicleMatchMatchedSummary() {
            return (this.vehicleMatch?.matched || [])
                .map((field) => this.vehicleMatchFieldLabel(field))
                .join(' · ');
        },

        vehicleMatchMissingSummary() {
            return (this.vehicleMatch?.missing || [])
                .map((field) => this.vehicleMatchFieldLabel(field))
                .join(' · ');
        },

        vehicleMatchExplanationSummary() {
            return (this.vehicleMatch?.explanation || []).join(' · ');
        },

        hasLaborPackage() {
            return this.hasSuggestedLabor() && this.suggestedLabor.lines.length > 1;
        },

        effectiveHours(hours, basis) {
            if (hours === null || hours === undefined || hours === '') {
                return null;
            }

            const value = Number(hours);

            if (! this.includeVehicleAgePadding || basis === 'lo' || this.vehicleAgeMultiplier <= 1) {
                return value;
            }

            return Math.round(value * this.vehicleAgeMultiplier * 100) / 100;
        },

        hoursForJob(job, basis) {
            const totalKey = `total_${basis}_hr`;

            if (this.includeAddOns && job[totalKey] !== null && job[totalKey] !== undefined) {
                return this.effectiveHours(job[totalKey], basis);
            }

            return this.effectiveHours(job[`${basis}_hr`], basis);
        },

        hasRecommendedJob() {
            return this.recommendedJob !== null && typeof this.recommendedJob === 'object';
        },

        hasAlternateJobs() {
            return Array.isArray(this.jobs) && this.jobs.length > 0;
        },

        hasIncludedAddOns(job) {
            return Array.isArray(job.included_add_ons) && job.included_add_ons.length > 0;
        },

        hasOptionalDiagnosticOperations(source = null) {
            const operations = this.optionalDiagnosticOperations(source);

            return operations.length > 0;
        },

        optionalDiagnosticOperations(source = null) {
            if (source !== null && typeof source === 'object') {
                return Array.isArray(source.optional_diagnostic_operations)
                    ? source.optional_diagnostic_operations
                    : [];
            }

            if (this.hasSuggestedLabor() && Array.isArray(this.suggestedLabor.optional_diagnostic_operations)) {
                return this.suggestedLabor.optional_diagnostic_operations;
            }

            if (this.hasRecommendedJob() && Array.isArray(this.recommendedJob.optional_diagnostic_operations)) {
                return this.recommendedJob.optional_diagnostic_operations;
            }

            return [];
        },

        isOptionalDiagnosticSelected(labId) {
            return this.selectedOptionalDiagnosticLabIds.includes(String(labId));
        },

        toggleOptionalDiagnostic(labId) {
            const normalized = String(labId);
            const index = this.selectedOptionalDiagnosticLabIds.indexOf(normalized);

            if (index === -1) {
                this.selectedOptionalDiagnosticLabIds.push(normalized);
            } else {
                this.selectedOptionalDiagnosticLabIds.splice(index, 1);
            }
        },

        includedAddOnSummary(job) {
            if (! this.hasIncludedAddOns(job)) {
                return '';
            }

            return job.included_add_ons
                .map((addOn) => `${addOn.description} (${this.formatHours(this.effectiveHours(addOn.avg_hr, 'avg'))} hr)`)
                .join(' · ');
        },

        formatHours(value) {
            if (value === null || value === undefined || value === '') {
                return '—';
            }

            return Number(value).toFixed(2);
        },

        displayHours(line, basis = 'avg') {
            if (! line || typeof line !== 'object') {
                return null;
            }

            return this.effectiveHours(line[`${basis}_hr`] ?? line.avg_hr, basis);
        },

        packageTotalHours(basis = this.selectedBasis) {
            if (! this.hasSuggestedLabor()) {
                return null;
            }

            let total = 0;

            for (const line of this.suggestedLabor.lines) {
                const hours = this.displayHours(line, basis);

                if (hours !== null) {
                    total += hours;
                }
            }

            for (const optional of this.optionalDiagnosticOperations()) {
                if (! this.isOptionalDiagnosticSelected(optional.lab_id)) {
                    continue;
                }

                const hours = this.displayHours(optional, basis);

                if (hours !== null) {
                    total += hours;
                }
            }

            return Math.round(total * 100) / 100;
        },

        basisLabel(basis) {
            if (basis === 'lo') {
                return 'Lo';
            }

            if (basis === 'hi') {
                return 'Hi';
            }

            return 'Avg';
        },

        applyButtonLabel(basis, hours) {
            const label = this.basisLabel(basis);

            if (hours === null || hours === undefined || hours === '') {
                return label;
            }

            return `${label} · ${this.formatHours(hours)} hr`;
        },

        vehicleAgePaddingLabel() {
            if (this.vehicleAgeMultiplier <= 1) {
                return 'Older vehicle padding';
            }

            const pct = Math.round((this.vehicleAgeMultiplier - 1) * 100);

            return `Older vehicle padding (+${pct}% on Avg & Hi)`;
        },

        vehicleAgePaddingSummary() {
            if (this.vehicleAgeMultiplier <= 1) {
                return 'No extra time added for this vehicle year.';
            }

            const pct = Math.round((this.vehicleAgeMultiplier - 1) * 100);

            return `Adds ${pct}% to Avg and Hi labor for older vehicles. Uncheck to use mapped hours only.`;
        },

        packageExplanation(basis = 'avg') {
            const matrix = this.suggestedLabor?.labor_explanation;

            if (! matrix) {
                return null;
            }

            const paddingKey = this.includeVehicleAgePadding ? 'padding_on' : 'padding_off';

            return matrix[basis]?.[paddingKey] ?? null;
        },

        jobExplanation(job, basis = 'avg') {
            const matrix = job?.labor_explanation;

            if (! matrix) {
                return null;
            }

            const paddingKey = this.includeVehicleAgePadding ? 'padding_on' : 'padding_off';
            const addonsKey = this.includeAddOns ? 'addons_on' : 'addons_off';

            return matrix[basis]?.[paddingKey]?.[addonsKey] ?? null;
        },

        hasExplanationIncludes(explanation) {
            return Array.isArray(explanation?.advisor_summary?.includes)
                && explanation.advisor_summary.includes.length > 0;
        },

        packageDiagnosticOverlap() {
            return this.suggestedLabor?.labor_explanation?.diagnostic_overlap ?? null;
        },

        hasPackageDiagnosticOverlap() {
            const warning = this.packageDiagnosticOverlap()?.advisor_summary?.overlap_warning;

            return warning !== null && warning !== undefined && warning !== '';
        },

        packageMatchAttribution() {
            return this.packageExplanation('avg')?.advisor_detail?.match_attribution ?? null;
        },
    };
}
