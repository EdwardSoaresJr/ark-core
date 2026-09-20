@props([
    'technicianCount' => 1,
    'workdayHours' => 8,
    'billableUtilization' => \App\Ark\Operations\Labor\ShopOverheadCalculator::DEFAULT_BILLABLE_UTILIZATION_PERCENT,
    'initialState' => null,
    'saveUrl' => null,
])

<form
    method="POST"
    action="{{ $saveUrl }}"
    @submit.prevent="saveOverhead()"
    x-data="shopOverheadCalculator({
        technicianCount: {{ max(1, (int) $technicianCount) }},
        workdayHours: {{ (float) $workdayHours }},
        billableUtilization: {{ (float) $billableUtilization }},
        cardProcessingPercent: {{ \App\Ark\Operations\Labor\ShopPaymentProcessingOverhead::DEFAULT_CARD_PROCESSING_PERCENT }},
        workdaysPerMonth: {{ \App\Ark\Operations\Labor\ShopOverheadCalculator::DEFAULT_WORKDAYS_PER_MONTH }},
        saveUrl: @js($saveUrl),
        initialState: @js($initialState),
    })"
    class="ops-shop-overhead w-full border border-slate-200 bg-white text-xs text-slate-700"
>
    @csrf
    @method('PATCH')

    <div
        x-data="{ overheadGuideOpen: true }"
        class="border-b border-slate-200 bg-slate-50/60 px-3 py-2 text-[11px] leading-5 text-slate-600"
    >
        <button
            type="button"
            @click="overheadGuideOpen = ! overheadGuideOpen"
            class="flex w-full items-center justify-between gap-2 text-left"
        >
            <span class="font-semibold text-slate-800">Setup walkthrough — start here</span>
            <span class="shrink-0 text-[10px] font-bold uppercase tracking-wide text-slate-400" x-text="overheadGuideOpen ? 'Hide' : 'Show'"></span>
        </button>
        <div x-show="overheadGuideOpen" x-cloak class="mt-2 space-y-3 border-t border-slate-200 pt-2">
            <div class="rounded-sm border border-amber-200 bg-amber-50 px-3 py-2 text-amber-950">
                <p class="font-semibold">Payroll is split on purpose — there is no single “payroll” box for everything.</p>
                <ul class="mt-1 list-disc space-y-1 pl-4">
                    <li><strong class="font-semibold">Technician wages</strong> — Settings → Staff → each tech → Loaded cost calculator (base pay + burden % + shop overhead / hr).</li>
                    <li><strong class="font-semibold">Advisor, front desk, owner (non-billing)</strong> — Fixed Costs tab → <strong class="font-semibold">Office and advisor payroll</strong> (monthly total).</li>
                    <li><strong class="font-semibold">Break-even on Margin Health</strong> — Owner Targets → Monthly fixed costs (often ≈ this worksheet’s monthly total + all tech payroll from your P&amp;L).</li>
                </ul>
            </div>
            <ol class="list-decimal space-y-1.5 pl-4">
                <li><strong class="font-semibold text-slate-800">Fixed Costs</strong> — rent, utilities, insurance, software, equipment, office/advisor payroll, other. <em>Exclude technician straight wages.</em></li>
                <li><strong class="font-semibold text-slate-800">Payment Processing</strong> — estimated card volume, processor %, optional merchant financing.</li>
                <li><strong class="font-semibold text-slate-800">Billing Capacity</strong> — active technicians, workdays, hours, utilization → monthly billable hours.</li>
                <li>Review <strong class="font-semibold text-slate-800">Shop overhead / billed hr</strong> at the top → <strong class="font-semibold text-slate-800">Save shop overhead</strong>.</li>
                <li><strong class="font-semibold text-slate-800">Settings → Staff</strong> — for each technician: base pay, burden %, click <strong class="font-semibold">Use calculated loaded cost</strong>, save.</li>
            </ol>
            <p class="text-slate-500">Example: $8,700/mo overhead ÷ 299 billable hr ≈ <strong class="font-semibold text-slate-700">$29.08/hr</strong> shop allocation. A tech at $30/hr base + 28% burden + $29.08 overhead ≈ <strong class="font-semibold text-slate-700">$67/hr</strong> loaded cost on billed hours.</p>
        </div>
    </div>

    <div class="flex items-start justify-between gap-3 border-b border-slate-200 px-3 py-3">
        <div>
            <p class="font-bold uppercase tracking-[0.08em] text-slate-500">Monthly overhead pool</p>
            <p class="mt-0.5 text-[11px] leading-4 text-slate-500">Fixed shop costs for the whole business — not per technician. Click <strong class="font-semibold text-slate-700">Save shop overhead</strong> to persist; staff loaded cost calculators pick up the saved rate.</p>
        </div>
        <p class="shrink-0 text-right">
            <span class="block text-[10px] font-bold uppercase tracking-[0.08em] text-slate-400">Shop overhead / billed hr</span>
            <span class="text-lg font-black tabular-nums text-slate-950" x-text="formattedOverheadPerHour()"></span>
        </p>
    </div>

    <div class="grid gap-px border-b border-slate-300 bg-slate-300 text-sm sm:grid-cols-3">
        <button type="button" @click="setOverheadTab('fixed-costs')" :class="overheadTab === 'fixed-costs' ? 'bg-slate-950 text-white' : 'bg-white text-slate-600 hover:bg-slate-50 hover:text-slate-950'" class="px-3 py-2 text-left font-semibold">Fixed Costs</button>
        <button type="button" @click="setOverheadTab('payments')" :class="overheadTab === 'payments' ? 'bg-slate-950 text-white' : 'bg-white text-slate-600 hover:bg-slate-50 hover:text-slate-950'" class="px-3 py-2 text-left font-semibold">Payment Processing</button>
        <button type="button" @click="setOverheadTab('capacity')" :class="overheadTab === 'capacity' ? 'bg-slate-950 text-white' : 'bg-white text-slate-600 hover:bg-slate-50 hover:text-slate-950'" class="px-3 py-2 text-left font-semibold">Billing Capacity</button>
    </div>

    <div class="px-3 py-3">
        <div x-show="overheadTab === 'fixed-costs'">
            <p class="font-semibold text-slate-700">Fixed shop costs</p>
            <p class="mt-0.5 text-[11px] leading-4 text-slate-500">Enter each cost with how often you pay it — ARK converts to a monthly total for break-even. Payroll can be weekly; rent is usually monthly. Technician straight wages still belong under <button type="button" class="font-semibold text-slate-700 underline decoration-slate-300 underline-offset-2 hover:text-slate-950" @click="$parent.setActive('staff')">Settings → Staff</button> when you split loaded cost.</p>

            <div class="ops-shop-overhead-fixed-costs mt-2 overflow-hidden rounded-sm border border-slate-200">
                <div class="ops-shop-overhead-fixed-costs__head" aria-hidden="true">
                    <span>Cost</span>
                    <span>Amount</span>
                    <span>Period</span>
                    <span class="text-right">Monthly</span>
                    <span></span>
                </div>

                <template x-for="(line, index) in fixedCostLines" :key="line._key ?? index">
                    <div class="ops-shop-overhead-fixed-costs__row">
                        <div class="ops-shop-overhead-fixed-costs__field">
                            <span class="ops-shop-overhead-fixed-costs__label">Cost</span>
                            <input
                                type="text"
                                x-model="line.label"
                                placeholder="Rent, payroll, Cintas…"
                                class="min-h-9 w-full rounded-sm border border-slate-300 px-2 py-1.5 text-sm text-slate-950"
                            >
                        </div>
                        <div class="ops-shop-overhead-fixed-costs__field">
                            <span class="ops-shop-overhead-fixed-costs__label">Amount</span>
                            <div class="relative">
                                <span class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-2 text-slate-400">$</span>
                                <input
                                    type="number"
                                    min="0"
                                    step="0.01"
                                    x-model="line.amount"
                                    placeholder="0"
                                    class="min-h-9 w-full rounded-sm border border-slate-300 py-1.5 pl-5 pr-1 text-sm tabular-nums text-slate-950"
                                >
                            </div>
                        </div>
                        <div class="ops-shop-overhead-fixed-costs__field">
                            <span class="ops-shop-overhead-fixed-costs__label">Period</span>
                            <select
                                x-model="line.period"
                                class="min-h-9 w-full rounded-sm border border-slate-300 px-1 py-1.5 text-sm text-slate-950"
                            >
                                @foreach (\App\Ark\Operations\Labor\ShopFixedCostPeriod::ALL as $period)
                                    <option value="{{ $period }}">{{ \App\Ark\Operations\Labor\ShopFixedCostPeriod::label($period) }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="ops-shop-overhead-fixed-costs__field ops-shop-overhead-fixed-costs__field--monthly">
                            <span class="ops-shop-overhead-fixed-costs__label">Monthly</span>
                            <span class="text-right text-sm font-semibold tabular-nums text-slate-700" x-text="money(monthlyEquivalentForLine(line))"></span>
                        </div>
                        <div class="ops-shop-overhead-fixed-costs__field ops-shop-overhead-fixed-costs__field--remove">
                            <button
                                type="button"
                                @click="removeFixedCostLine(index)"
                                class="text-[11px] font-semibold text-slate-500 hover:text-rose-700"
                                title="Remove row"
                            >×</button>
                        </div>
                    </div>
                </template>
            </div>

            <div class="mt-2 flex flex-wrap items-center justify-between gap-2">
                <button
                    type="button"
                    @click="addFixedCostLine()"
                    class="inline-flex h-8 items-center rounded-sm border border-slate-300 bg-white px-3 text-xs font-semibold text-slate-800 hover:bg-slate-50"
                >
                    Add cost
                </button>
                <p class="text-[11px] tabular-nums text-slate-600">
                    <span class="text-slate-500">Fixed costs monthly total</span>
                    <span class="ml-2 font-semibold text-slate-800" x-text="money(monthlyFixedOverheadTotal())"></span>
                </p>
            </div>
            <p class="mt-1 text-[11px] leading-4 text-slate-500">Saving shop overhead also updates <strong class="font-semibold text-slate-600">Owner Targets → Monthly fixed costs</strong> for Margin Health break-even.</p>
        </div>

        <div x-show="overheadTab === 'payments'" x-cloak class="ops-shop-overhead-payments">
            <p class="font-semibold text-slate-700">Card processing & merchant financing</p>
            <p class="mt-0.5 text-[11px] leading-4 text-slate-500">Square, Stripe, and similar processors charge a percentage of card volume. Merchant loans and cash advances often take a holdback from deposits <strong class="font-semibold text-slate-700">after</strong> processing fees.</p>
            <div class="mt-2 grid gap-2 sm:grid-cols-2 xl:grid-cols-4">
                <label class="block sm:col-span-2">
                    <span class="font-semibold text-slate-600">Estimated monthly card volume</span>
                    <span class="mt-0.5 block text-[11px] leading-4 text-slate-500">Typical month of customer card payments — RO closeouts, deposits, and invoices paid by card.</span>
                    <div class="relative mt-1.5">
                        <span class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-2 text-slate-400">$</span>
                        <input x-model="monthlyCardVolume" type="number" min="0" step="0.01" placeholder="85000.00" class="w-full rounded-sm border border-slate-300 py-1.5 pl-5 pr-2 text-sm text-slate-950">
                    </div>
                </label>
                <label class="block">
                    <span class="font-semibold text-slate-600">Processing fee %</span>
                    <span class="mt-0.5 block text-[11px] leading-4 text-slate-500">Effective card rate from your processor statement. Many shops see 2.6–3.3% all-in.</span>
                    <div class="relative mt-1.5">
                        <input x-model="cardProcessingPercent" type="number" min="0" step="0.01" class="w-full rounded-sm border border-slate-300 py-1.5 pl-2 pr-7 text-sm text-slate-950">
                        <span class="pointer-events-none absolute inset-y-0 right-0 flex items-center pr-2 text-slate-400">%</span>
                    </div>
                </label>
                <label class="block">
                    <span class="font-semibold text-slate-600">Merchant loan holdback %</span>
                    <span class="mt-0.5 block text-[11px] leading-4 text-slate-500">Repayment holdback on deposits after processing fees. Leave blank if no active merchant advance.</span>
                    <div class="relative mt-1.5">
                        <input x-model="merchantFinancingHoldbackPercent" type="number" min="0" step="0.01" placeholder="0" class="w-full rounded-sm border border-slate-300 py-1.5 pl-2 pr-7 text-sm text-slate-950">
                        <span class="pointer-events-none absolute inset-y-0 right-0 flex items-center pr-2 text-slate-400">%</span>
                    </div>
                </label>
                <label class="block sm:col-span-2">
                    <span class="font-semibold text-slate-600">Or fixed monthly financing payment</span>
                    <span class="mt-0.5 block text-[11px] leading-4 text-slate-500">Use this instead of holdback % when you pay a flat monthly merchant loan or equipment finance payment.</span>
                    <div class="relative mt-1.5">
                        <span class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-2 text-slate-400">$</span>
                        <input x-model="fixedMonthlyFinancingPayment" type="number" min="0" step="0.01" placeholder="0.00" class="w-full rounded-sm border border-slate-300 py-1.5 pl-5 pr-2 text-sm text-slate-950">
                    </div>
                </label>
            </div>
            <div class="mt-2 space-y-0.5 text-[11px] tabular-nums text-slate-600">
                <p x-show="monthlyProcessingCost() > 0">
                    <span class="text-slate-500">Processing fees</span>
                    <span class="float-right font-semibold text-slate-800" x-text="money(monthlyProcessingCost())"></span>
                </p>
                <p x-show="monthlyFinancingCost() > 0">
                    <span class="text-slate-500">Merchant financing</span>
                    <span class="float-right font-semibold text-slate-800" x-text="money(monthlyFinancingCost())"></span>
                </p>
                <p x-show="monthlyPaymentOverheadTotal() > 0" class="border-t border-slate-200 pt-1">
                    <span class="text-slate-500">Payment costs subtotal</span>
                    <span class="float-right font-semibold text-slate-800" x-text="money(monthlyPaymentOverheadTotal())"></span>
                </p>
            </div>
            <p class="mt-2 text-[11px] tabular-nums text-slate-600">
                <span class="font-semibold text-slate-700">Monthly overhead total</span>
                <span class="float-right font-black text-slate-950" x-text="money(monthlyOverheadTotal())"></span>
            </p>
        </div>

        <div x-show="overheadTab === 'capacity'" x-cloak>
            <p class="font-semibold text-slate-700">Shop billing capacity</p>
            <p class="mt-0.5 text-[11px] leading-4 text-slate-500">Expected billed hours the whole shop produces in a typical month. Technician count is prefilled from active staff — adjust if your real capacity differs.</p>
            <div class="mt-2 grid gap-2 sm:grid-cols-2 xl:grid-cols-4">
                <label class="block">
                    <span class="font-semibold text-slate-600">Active technicians</span>
                    <span class="mt-0.5 block text-[11px] leading-4 text-slate-500">How many techs bill labor in a typical month.</span>
                    <input x-model="technicianCount" type="number" min="1" step="1" class="mt-1.5 w-full rounded-sm border border-slate-300 px-2 py-1.5 text-sm text-slate-950">
                </label>
                <label class="block">
                    <span class="font-semibold text-slate-600">Workdays / month</span>
                    <span class="mt-0.5 block text-[11px] leading-4 text-slate-500">Paid shop days in a typical month. Default 22 ≈ Mon–Fri minus holidays.</span>
                    <input x-model="workdaysPerMonth" type="number" min="1" max="31" step="0.5" class="mt-1.5 w-full rounded-sm border border-slate-300 px-2 py-1.5 text-sm text-slate-950">
                </label>
                <label class="block">
                    <span class="font-semibold text-slate-600">Workday hours</span>
                    <span class="mt-0.5 block text-[11px] leading-4 text-slate-500">Average clock hours per technician per workday.</span>
                    <input x-model="workdayHours" type="number" min="1" max="24" step="0.25" class="mt-1.5 w-full rounded-sm border border-slate-300 px-2 py-1.5 text-sm text-slate-950">
                </label>
                <label class="block">
                    <span class="font-semibold text-slate-600">Billable utilization %</span>
                    <span class="mt-0.5 block text-[11px] leading-4 text-slate-500">Share of paid time that becomes billable hours across the shop.</span>
                    <input x-model="billableUtilization" type="number" min="1" max="100" step="1" class="mt-1.5 w-full rounded-sm border border-slate-300 px-2 py-1.5 text-sm text-slate-950">
                </label>
            </div>

            <div x-show="overheadPerBilledHour() !== null" x-cloak class="mt-3 space-y-1 border-t border-slate-200 pt-3 text-[11px] leading-4 text-slate-600">
                <p class="font-bold uppercase tracking-[0.08em] text-slate-400">How this adds up</p>
                <p>
                    <span class="text-slate-500">Monthly overhead pool</span>
                    <span class="float-right font-semibold text-slate-800" x-text="money(monthlyOverheadTotal())"></span>
                    <span class="block text-slate-400">fixed costs + card processing + merchant financing</span>
                </p>
                <p>
                    <span class="text-slate-500">Monthly billable hours</span>
                    <span class="float-right font-semibold text-slate-800" x-text="`${monthlyBillableHours()} hr`"></span>
                    <span class="block text-slate-400">technicians × workdays × workday hours × utilization</span>
                </p>
                <p class="border-t border-slate-200 pt-1">
                    <span class="font-semibold text-slate-700">Shop overhead / billed hr</span>
                    <span class="float-right font-black text-slate-950" x-text="formattedOverheadPerHour()"></span>
                    <span class="block text-slate-400">monthly overhead ÷ monthly billable hours</span>
                </p>
            </div>
        </div>
    </div>

    <div class="border-t border-slate-200 px-3 py-3">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div class="min-w-0 space-y-1">
                <p x-show="dirty && ! saving && ! saved" x-cloak class="text-[11px] font-semibold text-amber-700">Unsaved changes — save before leaving this page.</p>
                <p x-show="saved" x-cloak class="text-[11px] font-semibold text-emerald-700">Shop overhead saved. Staff loaded cost calculators will pick this up.</p>
                <p x-show="saveError" x-cloak class="text-[11px] font-semibold text-rose-700" x-text="saveError"></p>
                <p class="text-[11px] leading-4 text-slate-500">Next: under <button type="button" class="font-semibold text-slate-700 underline decoration-slate-300 underline-offset-2 hover:text-slate-950" @click="$parent.setActive('staff')">Settings → Staff</button>, edit each <strong class="font-semibold text-slate-700">technician</strong> — enter base pay and burden, confirm overhead / hr prefilled, click <strong class="font-semibold text-slate-700">Use calculated loaded cost</strong>, then <strong class="font-semibold text-slate-700">Save changes</strong>. Advisors do not use loaded labor cost — their payroll belongs in Office and advisor payroll above.</p>
            </div>
            <button
                type="submit"
                class="ops-index-btn ops-index-btn--primary shrink-0 disabled:cursor-not-allowed disabled:opacity-50"
                :disabled="saving"
            >
                <span x-show="! saving">Save shop overhead</span>
                <span x-show="saving" x-cloak>Saving…</span>
            </button>
        </div>
    </div>
</form>
