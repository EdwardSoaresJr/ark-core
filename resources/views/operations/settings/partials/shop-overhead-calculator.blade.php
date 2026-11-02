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
    data-settings-overhead
>
    @csrf
    @method('PATCH')

    <div x-data="{ overheadGuideOpen: true }" class="card-body border-bottom" data-overhead-walkthrough>
        <button
            type="button"
            @click="overheadGuideOpen = ! overheadGuideOpen"
            :aria-expanded="overheadGuideOpen.toString()"
            aria-controls="overhead-walkthrough"
            class="btn btn-link p-0 w-100 d-flex align-items-center justify-content-between gap-2 text-reset text-decoration-none"
        >
            <span class="fw-medium">Setup walkthrough - start here</span>
            <span class="small text-secondary text-uppercase" x-text="overheadGuideOpen ? 'Hide' : 'Show'"></span>
        </button>
        <div id="overhead-walkthrough" x-show="overheadGuideOpen" x-cloak class="mt-3">
            <div class="alert alert-warning small mb-3" role="note">
                <h4 class="alert-title">Payroll is split on purpose - there is no single “payroll” box for everything.</h4>
                <ul class="text-secondary mb-0 ps-3" style="list-style: disc">
                    <li><strong>Technician wages</strong> - Settings → Staff → each tech → Loaded cost calculator (base pay + burden % + shop overhead / hr).</li>
                    <li><strong>Advisor, front desk, owner (non-billing)</strong> - Fixed Costs tab → <strong>Office and advisor payroll</strong> (monthly total).</li>
                    <li><strong>Break-even on Margin Health</strong> - uses this worksheet’s fixed-cost monthly total once saved (shown under Owner Targets). Technician pay is already counted there as labor cost.</li>
                </ul>
            </div>
            <ol class="small ps-3 mb-2" style="list-style: decimal">
                <li><strong>Fixed Costs</strong> - rent, utilities, insurance, software, equipment, office/advisor payroll, other. <em>Exclude technician straight wages.</em></li>
                <li><strong>Payment Processing</strong> - estimated card volume, processor %, optional merchant financing.</li>
                <li><strong>Billing Capacity</strong> - active technicians, workdays, hours, utilization → monthly billable hours.</li>
                <li>Review <strong>Shop overhead / billed hr</strong> at the top → <strong>Save shop overhead</strong>.</li>
                <li><strong>Settings → Staff</strong> - for each technician: base pay, burden %, click <strong>Use calculated loaded cost</strong>, save.</li>
            </ol>
            <p class="small text-secondary mb-0">Example: $8,700/mo overhead ÷ 299 billable hr ≈ <strong>$29.08/hr</strong> shop allocation. A tech at $30/hr base + 28% burden + $29.08 overhead ≈ <strong>$67/hr</strong> loaded cost on billed hours.</p>
        </div>
    </div>

    <div class="card-body border-bottom">
        <div class="row g-3 align-items-center">
            <div class="col-12 col-md">
                <div class="subheader">Monthly overhead pool</div>
                <p class="small text-secondary mb-0">Fixed shop costs for the whole business - not per technician. Click <strong>Save shop overhead</strong> to persist; staff loaded cost calculators pick up the saved rate, and the monthly total replaces Owner Targets monthly fixed costs.</p>
            </div>
            <div class="col-12 col-md-auto text-md-end">
                <div class="subheader">Shop overhead / billed hr</div>
                <div class="h2 mb-0" x-text="formattedOverheadPerHour()" data-overhead-rate></div>
            </div>
        </div>
    </div>

    <div class="card-header">
        <ul class="nav nav-tabs card-header-tabs" role="tablist" aria-label="Shop overhead worksheet">
            @foreach (['fixed-costs' => 'Fixed Costs', 'payments' => 'Payment Processing', 'capacity' => 'Billing Capacity'] as $overheadTabKey => $overheadTabLabel)
                <li class="nav-item" role="presentation">
                    <button
                        type="button"
                        class="nav-link"
                        role="tab"
                        id="overhead-tab-{{ $overheadTabKey }}"
                        aria-controls="overhead-panel-{{ $overheadTabKey }}"
                        :class="{ 'active': overheadTab === @js($overheadTabKey) }"
                        :aria-selected="(overheadTab === @js($overheadTabKey)).toString()"
                        @click="setOverheadTab(@js($overheadTabKey))"
                    >{{ $overheadTabLabel }}</button>
                </li>
            @endforeach
        </ul>
    </div>

    <div class="card-body" id="overhead-panel-fixed-costs" role="tabpanel" aria-labelledby="overhead-tab-fixed-costs" x-show="overheadTab === 'fixed-costs'">
        <h3 class="mb-1">Fixed shop costs</h3>
        <p class="text-secondary small">Enter each cost with how often you pay it - ARK converts to a monthly total for break-even. Payroll can be weekly; rent is usually monthly. Technician straight wages still belong under <a href="{{ route('operations.settings.shop.edit', ['section' => 'staff']) }}" @click.prevent="setActive('staff')" data-overhead-staff-link>Settings → Staff</a> when you split loaded cost.</p>

        <div class="row g-2 px-3 mb-1 d-none d-md-flex subheader" aria-hidden="true">
            <div class="col-md-4">Cost</div>
            <div class="col-md-3">Amount</div>
            <div class="col-md-2">Period</div>
            <div class="col-md-2 text-end">Monthly</div>
            <div class="col-md-1"></div>
        </div>
        <div class="list-group" data-overhead-fixed-costs>
            <template x-for="(line, index) in fixedCostLines" :key="line._key ?? index">
                <div class="list-group-item" data-overhead-fixed-cost>
                    <div class="row g-2 align-items-center">
                        <div class="col-12 col-md-4">
                            <label class="form-label d-md-none" :for="`overhead-cost-${index}-label`">Cost</label>
                            <input
                                type="text"
                                :id="`overhead-cost-${index}-label`"
                                x-model="line.label"
                                placeholder="Rent, payroll, Cintas…"
                                :aria-label="`Cost ${index + 1}`"
                                class="form-control"
                            >
                        </div>
                        <div class="col-6 col-md-3">
                            <label class="form-label d-md-none" :for="`overhead-cost-${index}-amount`">Amount</label>
                            <div class="input-group">
                                <span class="input-group-text">$</span>
                                <input
                                    type="number"
                                    min="0"
                                    step="0.01"
                                    :id="`overhead-cost-${index}-amount`"
                                    x-model="line.amount"
                                    placeholder="0"
                                    :aria-label="`Amount, cost ${index + 1}`"
                                    class="form-control"
                                >
                            </div>
                        </div>
                        <div class="col-6 col-md-2">
                            <label class="form-label d-md-none" :for="`overhead-cost-${index}-period`">Period</label>
                            <select
                                :id="`overhead-cost-${index}-period`"
                                x-model="line.period"
                                :aria-label="`Period, cost ${index + 1}`"
                                class="form-select"
                            >
                                @foreach (\App\Ark\Operations\Labor\ShopFixedCostPeriod::ALL as $period)
                                    <option value="{{ $period }}">{{ \App\Ark\Operations\Labor\ShopFixedCostPeriod::label($period) }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-8 col-md-2 text-md-end">
                            <div class="small text-secondary d-md-none">Monthly</div>
                            <span class="fw-medium" x-text="money(monthlyEquivalentForLine(line))"></span>
                        </div>
                        <div class="col-4 col-md-1 text-end">
                            <button
                                type="button"
                                @click="removeFixedCostLine(index)"
                                title="Remove row"
                                :aria-label="`Remove cost ${index + 1}`"
                                class="btn btn-sm btn-ghost-danger"
                            >×</button>
                        </div>
                    </div>
                </div>
            </template>
        </div>

        <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mt-3">
            <button type="button" @click="addFixedCostLine()" class="btn btn-sm">Add cost</button>
            <div class="small">
                <span class="text-secondary">Fixed costs monthly total</span>
                <strong class="ms-2" x-text="money(monthlyFixedOverheadTotal())"></strong>
            </div>
        </div>
        <p class="small text-secondary mt-2 mb-0">Saving shop overhead also updates <strong>Owner Targets → Monthly fixed costs</strong> for Margin Health break-even.</p>
    </div>

    <div class="card-body" id="overhead-panel-payments" role="tabpanel" aria-labelledby="overhead-tab-payments" x-show="overheadTab === 'payments'" x-cloak>
        <h3 class="mb-1">Card processing &amp; merchant financing</h3>
        <p class="text-secondary small">Square, Stripe, and similar processors charge a percentage of card volume. Merchant loans and cash advances often take a holdback from deposits <strong>after</strong> processing fees.</p>
        <div class="row g-3">
            <div class="col-12 col-xl-6">
                <label for="overhead-card-volume" class="form-label">Estimated monthly card volume</label>
                <div class="input-group">
                    <span class="input-group-text">$</span>
                    <input id="overhead-card-volume" x-model="monthlyCardVolume" type="number" min="0" step="0.01" placeholder="85000.00" class="form-control">
                </div>
                <div class="form-hint">Typical month of customer card payments - RO closeouts, deposits, and invoices paid by card.</div>
            </div>
            <div class="col-12 col-md-6 col-xl-3">
                <label for="overhead-card-processing" class="form-label">Processing fee %</label>
                <div class="input-group">
                    <input id="overhead-card-processing" x-model="cardProcessingPercent" type="number" min="0" step="0.01" class="form-control">
                    <span class="input-group-text">%</span>
                </div>
                <div class="form-hint">Effective card rate from your processor statement. Many shops see 2.6–3.3% all-in.</div>
            </div>
            <div class="col-12 col-md-6 col-xl-3">
                <label for="overhead-financing-holdback" class="form-label">Merchant loan holdback %</label>
                <div class="input-group">
                    <input id="overhead-financing-holdback" x-model="merchantFinancingHoldbackPercent" type="number" min="0" step="0.01" placeholder="0" class="form-control">
                    <span class="input-group-text">%</span>
                </div>
                <div class="form-hint">Repayment holdback on deposits after processing fees. Leave blank if no active merchant advance.</div>
            </div>
            <div class="col-12 col-xl-6">
                <label for="overhead-financing-payment" class="form-label">Or fixed monthly financing payment</label>
                <div class="input-group">
                    <span class="input-group-text">$</span>
                    <input id="overhead-financing-payment" x-model="fixedMonthlyFinancingPayment" type="number" min="0" step="0.01" placeholder="0.00" class="form-control">
                </div>
                <div class="form-hint">Use this instead of holdback % when you pay a flat monthly merchant loan or equipment finance payment.</div>
            </div>
        </div>
        <div class="small mt-3">
            <div x-show.important="monthlyProcessingCost() > 0" class="d-flex justify-content-between gap-3">
                <span class="text-secondary">Processing fees</span>
                <span class="fw-medium" x-text="money(monthlyProcessingCost())"></span>
            </div>
            <div x-show.important="monthlyFinancingCost() > 0" class="d-flex justify-content-between gap-3">
                <span class="text-secondary">Merchant financing</span>
                <span class="fw-medium" x-text="money(monthlyFinancingCost())"></span>
            </div>
            <div x-show.important="monthlyPaymentOverheadTotal() > 0" class="d-flex justify-content-between gap-3 border-top pt-1 mt-1">
                <span class="text-secondary">Payment costs subtotal</span>
                <span class="fw-medium" x-text="money(monthlyPaymentOverheadTotal())"></span>
            </div>
        </div>
        <div class="d-flex justify-content-between gap-3 border-top pt-2 mt-2">
            <strong>Monthly overhead total</strong>
            <strong x-text="money(monthlyOverheadTotal())"></strong>
        </div>
    </div>

    <div class="card-body" id="overhead-panel-capacity" role="tabpanel" aria-labelledby="overhead-tab-capacity" x-show="overheadTab === 'capacity'" x-cloak>
        <h3 class="mb-1">Shop billing capacity</h3>
        <p class="text-secondary small">Expected billed hours the whole shop produces in a typical month. Technician count is prefilled from active staff - adjust if your real capacity differs.</p>
        <div class="row g-3">
            <div class="col-12 col-sm-6 col-xl-3">
                <label for="overhead-technician-count" class="form-label">Active technicians</label>
                <input id="overhead-technician-count" x-model="technicianCount" type="number" min="1" step="1" class="form-control">
                <div class="form-hint">How many techs bill labor in a typical month.</div>
            </div>
            <div class="col-12 col-sm-6 col-xl-3">
                <label for="overhead-workdays" class="form-label">Workdays / month</label>
                <input id="overhead-workdays" x-model="workdaysPerMonth" type="number" min="1" max="31" step="0.5" class="form-control">
                <div class="form-hint">Paid shop days in a typical month. Default 22 ≈ Mon–Fri minus holidays.</div>
            </div>
            <div class="col-12 col-sm-6 col-xl-3">
                <label for="overhead-workday-hours" class="form-label">Workday hours</label>
                <input id="overhead-workday-hours" x-model="workdayHours" type="number" min="1" max="24" step="0.25" class="form-control">
                <div class="form-hint">Average clock hours per technician per workday.</div>
            </div>
            <div class="col-12 col-sm-6 col-xl-3">
                <label for="overhead-utilization" class="form-label">Billable utilization %</label>
                <input id="overhead-utilization" x-model="billableUtilization" type="number" min="1" max="100" step="1" class="form-control">
                <div class="form-hint">Share of paid time that becomes billable hours across the shop.</div>
            </div>
        </div>

        <div x-show="overheadPerBilledHour() !== null" x-cloak class="small border-top pt-3 mt-3" data-overhead-breakdown>
            <div class="subheader mb-2">How this adds up</div>
            <div class="mb-2">
                <div class="d-flex justify-content-between gap-3">
                    <span class="text-secondary">Monthly overhead pool</span>
                    <span class="fw-medium" x-text="money(monthlyOverheadTotal())"></span>
                </div>
                <div class="text-secondary">fixed costs + card processing + merchant financing</div>
            </div>
            <div class="mb-2">
                <div class="d-flex justify-content-between gap-3">
                    <span class="text-secondary">Monthly billable hours</span>
                    <span class="fw-medium" x-text="`${monthlyBillableHours()} hr`"></span>
                </div>
                <div class="text-secondary">technicians × workdays × workday hours × utilization</div>
            </div>
            <div class="border-top pt-2">
                <div class="d-flex justify-content-between gap-3">
                    <strong>Shop overhead / billed hr</strong>
                    <strong x-text="formattedOverheadPerHour()"></strong>
                </div>
                <div class="text-secondary">monthly overhead ÷ monthly billable hours</div>
            </div>
        </div>
    </div>

    <div class="card-footer">
        <div class="row g-3 align-items-center">
            <div class="col-12 col-md" data-overhead-status>
                <p x-show="dirty && ! saving && ! saved" x-cloak class="small fw-medium text-warning mb-1">Unsaved changes - save before leaving this page.</p>
                <p x-show="saved" x-cloak class="small fw-medium text-success mb-1">Shop overhead saved. Staff loaded cost calculators will pick this up.</p>
                <p x-show="saveError" x-cloak class="small fw-medium text-danger mb-1" role="alert" x-text="saveError"></p>
                <p class="small text-secondary mb-0">Next: under <a href="{{ route('operations.settings.shop.edit', ['section' => 'staff']) }}" @click.prevent="setActive('staff')" data-overhead-staff-link>Settings → Staff</a>, edit each <strong>technician</strong> - enter base pay and burden, confirm overhead / hr prefilled, click <strong>Use calculated loaded cost</strong>, then <strong>Save changes</strong>. Advisors do not use loaded labor cost - their payroll belongs in Office and advisor payroll above.</p>
            </div>
            <div class="col-12 col-md-auto">
                <button type="submit" class="btn btn-primary" :disabled="saving">
                    <span x-show="! saving">Save shop overhead</span>
                    <span x-show="saving" x-cloak>Saving…</span>
                </button>
            </div>
        </div>
    </div>
</form>
