const DEFAULT_WORKDAYS_PER_MONTH = 22;
const DEFAULT_WORKDAY_HOURS = 8;
const DEFAULT_BILLABLE_UTILIZATION_PERCENT = 85;
const DEFAULT_CARD_PROCESSING_PERCENT = 2.9;

const OVERHEAD_LINE_ITEMS = [
    { key: 'rent', label: 'Rent / mortgage' },
    { key: 'utilities', label: 'Utilities' },
    { key: 'insurance', label: 'Insurance' },
    { key: 'software', label: 'Software & subscriptions' },
    { key: 'equipment', label: 'Equipment & tools' },
    { key: 'office_payroll', label: 'Payroll' },
    { key: 'other', label: 'Other fixed shop costs' },
];

const FIXED_COST_PERIODS = [
    { value: 'monthly', label: 'Monthly' },
    { value: 'weekly', label: 'Weekly' },
    { value: 'biweekly', label: 'Biweekly' },
    { value: 'annual', label: 'Annual' },
];

function legacyCostsToLines(costs) {
    return OVERHEAD_LINE_ITEMS.flatMap((item) => {
        const amount = String(costs?.[item.key] ?? '').trim();

        if (amount === '' || ! Number.isFinite(parseFloat(amount)) || parseFloat(amount) <= 0) {
            return [];
        }

        return [{ label: item.label, amount, period: 'monthly' }];
    });
}

function hydrateFixedCostLines(serverState) {
    const lines = Array.isArray(serverState?.fixed_cost_lines) ? serverState.fixed_cost_lines : [];

    if (lines.length > 0) {
        return lines.map((line, index) => ({
            _key: `${String(line.label ?? '').trim()}-${index}-${line.period ?? 'monthly'}`,
            label: String(line.label ?? ''),
            amount: String(line.amount ?? ''),
            period: FIXED_COST_PERIODS.some((option) => option.value === line.period)
                ? line.period
                : 'monthly',
        }));
    }

    const legacy = legacyCostsToLines(serverState?.costs ?? {});

    return legacy.length > 0 ? legacy : [{ label: '', amount: '', period: 'monthly' }];
}

function hydrateFromServerState(serverState) {
    if (! serverState || typeof serverState !== 'object') {
        return null;
    }

    return {
        fixedCostLines: hydrateFixedCostLines(serverState),
        monthlyCardVolume: String(serverState.monthly_card_volume ?? ''),
        cardProcessingPercent: String(
            serverState.card_processing_percent ?? DEFAULT_CARD_PROCESSING_PERCENT,
        ),
        merchantFinancingHoldbackPercent: String(serverState.merchant_financing_holdback_percent ?? ''),
        fixedMonthlyFinancingPayment: String(serverState.fixed_monthly_financing_payment ?? ''),
        technicianCount: String(serverState.technician_count ?? ''),
        workdaysPerMonth: String(serverState.workdays_per_month ?? DEFAULT_WORKDAYS_PER_MONTH),
        workdayHours: String(serverState.workday_hours ?? DEFAULT_WORKDAY_HOURS),
        billableUtilization: String(serverState.billable_utilization ?? DEFAULT_BILLABLE_UTILIZATION_PERCENT),
        overheadTab: String(serverState.overhead_tab ?? 'fixed-costs'),
    };
}

function publishShopOverheadRate(value) {
    window.dispatchEvent(new CustomEvent('ark-shop-overhead-updated', {
        detail: { value: value === null ? null : value.toFixed(2) },
    }));
}

export function shopOverheadCalculator(config = {}) {
    const resolved = hydrateFromServerState(config.initialState) ?? {};

    return {
        fixedCostPeriods: FIXED_COST_PERIODS,
        saveUrl: config.saveUrl ?? null,
        fixedCostLines: resolved.fixedCostLines ?? [{ label: '', amount: '', period: 'monthly' }],
        monthlyCardVolume: resolved.monthlyCardVolume ?? '',
        cardProcessingPercent: resolved.cardProcessingPercent
            ?? String(config.cardProcessingPercent ?? DEFAULT_CARD_PROCESSING_PERCENT),
        merchantFinancingHoldbackPercent: resolved.merchantFinancingHoldbackPercent ?? '',
        fixedMonthlyFinancingPayment: resolved.fixedMonthlyFinancingPayment ?? '',
        technicianCount: String(resolved.technicianCount || config.technicianCount || 1),
        workdaysPerMonth: String(resolved.workdaysPerMonth ?? config.workdaysPerMonth ?? DEFAULT_WORKDAYS_PER_MONTH),
        workdayHours: String(resolved.workdayHours ?? config.workdayHours ?? DEFAULT_WORKDAY_HOURS),
        billableUtilization: String(resolved.billableUtilization ?? config.billableUtilization ?? DEFAULT_BILLABLE_UTILIZATION_PERCENT),
        overheadTab: resolved.overheadTab ?? 'fixed-costs',
        dirty: false,
        saving: false,
        saved: false,
        saveError: null,

        setOverheadTab(tab) {
            this.overheadTab = tab;
            this.markDirty();
            this.publishPreviewRate();
        },

        init() {
            this.$watch('technicianCount', () => this.onFieldChange());
            this.$watch('workdaysPerMonth', () => this.onFieldChange());
            this.$watch('workdayHours', () => this.onFieldChange());
            this.$watch('billableUtilization', () => this.onFieldChange());
            this.$watch('monthlyCardVolume', () => this.onFieldChange());
            this.$watch('cardProcessingPercent', () => this.onFieldChange());
            this.$watch('merchantFinancingHoldbackPercent', () => this.onFieldChange());
            this.$watch('fixedMonthlyFinancingPayment', () => this.onFieldChange());
            this.$watch('fixedCostLines', () => this.onFieldChange(), { deep: true });
            this.publishPreviewRate();
        },

        onFieldChange() {
            this.markDirty();
            this.publishPreviewRate();
        },

        markDirty() {
            this.dirty = true;
            this.saved = false;
            this.saveError = null;
        },

        addFixedCostLine() {
            this.fixedCostLines.push({ label: '', amount: '', period: 'monthly' });
            this.markDirty();
        },

        removeFixedCostLine(index) {
            this.fixedCostLines.splice(index, 1);

            if (this.fixedCostLines.length === 0) {
                this.fixedCostLines.push({ label: '', amount: '', period: 'monthly' });
            }

            this.markDirty();
        },

        parseAmount(value) {
            const parsed = parseFloat(value);

            return Number.isFinite(parsed) && parsed > 0 ? parsed : 0;
        },

        parseNonNegativePercent(value, fallback = 0) {
            const parsed = parseFloat(value);

            return Number.isFinite(parsed) && parsed >= 0 ? parsed : fallback;
        },

        monthlyEquivalentForLine(line) {
            const amount = this.parseAmount(line.amount);

            if (amount <= 0) {
                return 0;
            }

            switch (line.period) {
                case 'weekly':
                    return Math.round((amount * 52 / 12) * 100) / 100;
                case 'biweekly':
                    return Math.round((amount * 26 / 12) * 100) / 100;
                case 'annual':
                    return Math.round((amount / 12) * 100) / 100;
                default:
                    return amount;
            }
        },

        monthlyFixedOverheadTotal() {
            return Math.round(this.fixedCostLines.reduce(
                (total, line) => total + this.monthlyEquivalentForLine(line),
                0,
            ) * 100) / 100;
        },

        monthlyProcessingCost() {
            const volume = this.parseAmount(this.monthlyCardVolume);
            const rate = this.parseNonNegativePercent(this.cardProcessingPercent, DEFAULT_CARD_PROCESSING_PERCENT);

            if (volume <= 0) {
                return 0;
            }

            return Math.round(volume * (rate / 100) * 100) / 100;
        },

        monthlyFinancingCost() {
            const fixed = this.parseAmount(this.fixedMonthlyFinancingPayment);

            if (fixed > 0) {
                return fixed;
            }

            const volume = this.parseAmount(this.monthlyCardVolume);
            const processingRate = this.parseNonNegativePercent(this.cardProcessingPercent, DEFAULT_CARD_PROCESSING_PERCENT);
            const holdbackRate = this.parseNonNegativePercent(this.merchantFinancingHoldbackPercent);

            if (volume <= 0 || holdbackRate <= 0) {
                return 0;
            }

            const netAfterProcessing = volume - this.monthlyProcessingCost();

            return Math.round(Math.max(netAfterProcessing, 0) * (holdbackRate / 100) * 100) / 100;
        },

        monthlyPaymentOverheadTotal() {
            return Math.round((this.monthlyProcessingCost() + this.monthlyFinancingCost()) * 100) / 100;
        },

        monthlyOverheadTotal() {
            return Math.round((this.monthlyFixedOverheadTotal() + this.monthlyPaymentOverheadTotal()) * 100) / 100;
        },

        parsedTechnicianCount() {
            const count = parseInt(this.technicianCount, 10);

            return Number.isFinite(count) && count > 0 ? count : 0;
        },

        parsedWorkdaysPerMonth() {
            const days = parseFloat(this.workdaysPerMonth);

            return Number.isFinite(days) && days > 0 ? days : DEFAULT_WORKDAYS_PER_MONTH;
        },

        parsedWorkdayHours() {
            const hours = parseFloat(this.workdayHours);

            return Number.isFinite(hours) && hours > 0 ? hours : DEFAULT_WORKDAY_HOURS;
        },

        parsedBillableUtilization() {
            const utilization = parseFloat(this.billableUtilization);

            return Number.isFinite(utilization)
                ? Math.min(Math.max(utilization, 1), 100)
                : DEFAULT_BILLABLE_UTILIZATION_PERCENT;
        },

        monthlyBillableHours() {
            const technicians = this.parsedTechnicianCount();

            if (technicians === 0) {
                return null;
            }

            const hours = technicians
                * this.parsedWorkdaysPerMonth()
                * this.parsedWorkdayHours()
                * (this.parsedBillableUtilization() / 100);

            return Math.round(hours * 10) / 10;
        },

        overheadPerBilledHour() {
            const total = this.monthlyOverheadTotal();
            const billableHours = this.monthlyBillableHours();

            if (total <= 0 || billableHours === null || billableHours <= 0) {
                return null;
            }

            return Math.round((total / billableHours) * 100) / 100;
        },

        formattedOverheadPerHour() {
            const value = this.overheadPerBilledHour();

            return value === null ? '—' : `$${value.toFixed(2)}/hr`;
        },

        money(value) {
            return `$${Number(value).toFixed(2)}`;
        },

        serializeStateForServer() {
            return {
                fixed_cost_lines: this.fixedCostLines
                    .filter((line) => String(line.label ?? '').trim() !== '' && this.parseAmount(line.amount) > 0)
                    .map((line) => ({
                        label: String(line.label).trim(),
                        amount: String(line.amount),
                        period: line.period || 'monthly',
                    })),
                monthly_card_volume: this.monthlyCardVolume,
                card_processing_percent: this.cardProcessingPercent,
                merchant_financing_holdback_percent: this.merchantFinancingHoldbackPercent,
                fixed_monthly_financing_payment: this.fixedMonthlyFinancingPayment,
                technician_count: this.technicianCount,
                workdays_per_month: this.workdaysPerMonth,
                workday_hours: this.workdayHours,
                billable_utilization: this.billableUtilization,
                overhead_tab: this.overheadTab,
            };
        },

        publishPreviewRate() {
            publishShopOverheadRate(this.overheadPerBilledHour());
        },

        async saveOverhead() {
            if (! this.saveUrl || this.saving) {
                return;
            }

            this.saving = true;
            this.saveError = null;

            try {
                const response = await fetch(this.saveUrl, {
                    method: 'PATCH',
                    headers: {
                        'Content-Type': 'application/json',
                        Accept: 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]')?.content || '',
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                    body: JSON.stringify(this.serializeStateForServer()),
                });

                const payload = await response.json().catch(() => ({}));

                if (! response.ok) {
                    const message = payload.message
                        || (payload.errors ? Object.values(payload.errors).flat().join(' ') : null)
                        || 'Could not save shop overhead.';

                    throw new Error(message);
                }

                const hydrated = hydrateFromServerState(payload.state);

                if (hydrated?.fixedCostLines) {
                    this.fixedCostLines = hydrated.fixedCostLines;
                }

                this.dirty = false;
                this.saved = true;
                window.clearTimeout(this._savedTimer);
                this._savedTimer = window.setTimeout(() => {
                    this.saved = false;
                }, 3000);
            } catch (error) {
                this.saveError = error instanceof Error ? error.message : 'Could not save shop overhead.';
            } finally {
                this.saving = false;
            }
        },
    };
}
