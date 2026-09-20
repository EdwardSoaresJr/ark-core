const DEFAULT_BURDEN_PERCENT = 28;
const DEFAULT_BILLABLE_UTILIZATION_PERCENT = 85;

export function loadedLaborCostCalculator(config = {}) {
    return {
        targetInputId: config.targetInputId ?? null,
        payBasis: config.laborPayBasis ?? 'hourly',
        baseWage: config.flagRate ?? '',
        flagRate: config.flagRate ?? '',
        floorRate: config.floorRate ?? '',
        floorSuggestion: config.floorSuggestion ?? null,
        floorSuggestionLabel: config.floorSuggestionLabel ?? '',
        burdenPercent: config.burdenPercent ?? DEFAULT_BURDEN_PERCENT,
        overheadPerHour: config.shopOverheadPerHour !== null && config.shopOverheadPerHour !== undefined
            ? String(config.shopOverheadPerHour)
            : '',
        billableUtilization: config.billableUtilization ?? DEFAULT_BILLABLE_UTILIZATION_PERCENT,
        applied: false,

        init() {
            window.addEventListener('ark-shop-overhead-updated', (event) => {
                if (event.detail?.value) {
                    this.overheadPerHour = event.detail.value;
                }
            });

            // Estimated labor cost lives in Alpine until Apply — auto-apply on Save.
            const form = this.$el.closest('form');
            form?.addEventListener('submit', () => {
                if (this.canApply()) {
                    this.applyLoadedCost();
                }
            });
        },

        isHourlyPay() {
            return this.payBasis === 'hourly';
        },

        isFlagPay() {
            return this.payBasis === 'flag';
        },

        basePayLabel() {
            return this.isFlagPay() ? 'Flag rate' : 'Clock wage / hr';
        },

        basePayHint() {
            return this.isFlagPay()
                ? 'What the technician earns for completed flagged production.'
                : 'Straight wage for each paid clock hour — before taxes and benefits.';
        },

        floorNeedsReview() {
            if (! this.isFlagPay() || this.floorSuggestion === null || this.floorSuggestion === undefined) {
                return false;
            }

            const stored = parseFloat(this.floorRate);
            const suggestion = parseFloat(this.floorSuggestion);

            if (! Number.isFinite(stored) || ! Number.isFinite(suggestion)) {
                return false;
            }

            return Math.round(stored * 100) !== Math.round(suggestion * 100);
        },

        flagWageForCalc() {
            if (this.isFlagPay()) {
                const flag = parseFloat(this.flagRate);

                return Number.isFinite(flag) ? flag : NaN;
            }

            return parseFloat(this.baseWage);
        },

        loadedCost() {
            const breakdown = this.calculationBreakdown();

            return breakdown?.total ?? null;
        },

        formattedResult() {
            const value = this.loadedCost();

            return value === null ? '—' : `$${value.toFixed(2)}/hr`;
        },

        calculationBreakdown() {
            const wage = this.flagWageForCalc();
            const burden = parseFloat(this.burdenPercent);
            const overhead = parseFloat(this.overheadPerHour || 0);
            const utilization = parseFloat(this.billableUtilization);
            const floor = parseFloat(this.floorRate || 0);

            if (! Number.isFinite(wage) || wage <= 0) {
                return null;
            }

            const safeBurden = Number.isFinite(burden) && burden >= 0 ? burden : 0;
            const safeOverhead = Number.isFinite(overhead) && overhead >= 0 ? overhead : 0;
            const safeUtilization = Number.isFinite(utilization)
                ? Math.min(Math.max(utilization, 1), 100)
                : DEFAULT_BILLABLE_UTILIZATION_PERCENT;
            const safeFloor = Number.isFinite(floor) && floor >= 0 ? floor : 0;

            let effectiveWage = wage;
            let floorEquivalent = null;

            if (this.isFlagPay()) {
                floorEquivalent = safeFloor > 0 ? safeFloor / (safeUtilization / 100) : 0;
                effectiveWage = Math.max(wage, floorEquivalent);
            }

            const payrollLoaded = effectiveWage * (1 + (safeBurden / 100));
            const afterUtilization = this.isFlagPay()
                ? payrollLoaded
                : payrollLoaded / (safeUtilization / 100);
            const total = Math.round((afterUtilization + safeOverhead) * 100) / 100;

            return {
                effectiveWage,
                floorEquivalent,
                payrollLoaded,
                afterUtilization,
                overhead: safeOverhead,
                utilization: safeUtilization,
                usesUtilization: this.isHourlyPay(),
                showsFloorEquivalent: this.isFlagPay() && safeFloor > 0,
                total,
            };
        },

        money(value) {
            return `$${Number(value).toFixed(2)}`;
        },

        canApply() {
            return this.loadedCost() !== null && this.resolveTargetInput() !== null;
        },

        resolveTargetInput() {
            if (this.targetInputId) {
                const byId = document.getElementById(this.targetInputId);

                if (byId) {
                    return byId;
                }
            }

            return this.$el.parentElement?.querySelector('input[name="labor_cost"]') ?? null;
        },

        applyLoadedCost() {
            const value = this.loadedCost();
            const input = this.resolveTargetInput();

            if (value === null || ! input) {
                return;
            }

            input.value = value.toFixed(2);
            input.dispatchEvent(new Event('input', { bubbles: true }));
            input.dispatchEvent(new Event('change', { bubbles: true }));

            this.applied = true;
            window.setTimeout(() => {
                this.applied = false;
            }, 2000);

            input.focus({ preventScroll: true });
            input.scrollIntoView({ block: 'nearest', behavior: 'smooth' });
        },
    };
}
