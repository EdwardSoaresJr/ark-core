<section x-show="active === 'excellence'" x-cloak>
    <div class="border-b border-slate-200 pb-2">
        <p class="text-[11px] font-bold uppercase tracking-wide text-slate-400">Owner Targets &amp; Reporting</p>
        <h2 class="text-base font-black text-slate-950">Shop excellence bands</h2>
        <p class="mt-0.5 text-xs text-slate-500">Three lenses: posted sales truth, margin KPIs, and owner rhythm. Drives green/amber hints on Operational Report and Margin Health.</p>
    </div>

    <div class="mt-4 max-w-3xl border border-slate-200 bg-slate-50 px-3 py-2">
        <p class="text-[11px] font-bold uppercase tracking-[0.08em] text-slate-500">Posted sales</p>
        <p class="mt-1 text-xs leading-relaxed text-slate-600">
            <strong class="font-semibold text-slate-800">Sales Posted</strong> uses <code class="text-[11px]">posted_at</code> — ROs you posted in the range (End of Day RO summary).
            <strong class="font-semibold text-slate-800">Cash Collected</strong> uses ledger payment/deposit dates (Payment Details / Total Cashiered).
            Deposits and partial payments never inflate posted sales until the RO is posted.
            Close — Paid posts automatically; paid ROs can also be posted from the repair order when ready.
        </p>
    </div>

    <form method="POST" action="{{ route('operations.settings.shop.excellence.update') }}" class="mt-4 max-w-3xl space-y-4">
        @csrf
        @method('PATCH')

        <div class="border border-slate-200 bg-white px-3 py-3">
            <p class="text-[11px] font-bold uppercase tracking-[0.08em] text-slate-500">Margin targets</p>
            <p class="mt-0.5 text-xs text-slate-500">ELR floor, ARO, parts margin, sales mix, and net profit planning bands.</p>
            <p class="mt-1 text-[11px] text-slate-400">Posted labor rate comes from <button type="button" @click="setActive('financial'); setFinancialTab('labor')" class="font-semibold text-slate-700 underline decoration-slate-300 hover:text-slate-950">Financial Rules → Labor</button>.</p>

        <div class="mt-3 grid gap-3 sm:grid-cols-2">
            <label class="block text-xs font-medium text-slate-500">
                ELR floor ($/hr)
                <input
                    type="number"
                    name="effective_labor_rate_floor"
                    min="0"
                    step="0.01"
                    value="{{ old('effective_labor_rate_floor', $excellenceTargets['effective_labor_rate_floor_cents'] !== null ? number_format($excellenceTargets['effective_labor_rate_floor_cents'] / 100, 2, '.', '') : '') }}"
                    placeholder="{{ $excellenceTargets['posted_labor_rate_cents'] !== null ? number_format($excellenceTargets['posted_labor_rate_cents'] / 100, 2) : '165.00' }}"
                    class="mt-1 w-full rounded-sm border border-slate-300 px-2 py-1.5 text-sm text-slate-950"
                >
                <span class="mt-1 block text-[11px] text-slate-400">Minimum acceptable effective labor rate on posted work.</span>
            </label>

            <label class="block text-xs font-medium text-slate-500">
                ARO target ($)
                <input
                    type="number"
                    name="aro_target"
                    min="0"
                    step="0.01"
                    required
                    value="{{ old('aro_target', number_format($excellenceTargets['aro_target_cents'] / 100, 2, '.', '')) }}"
                    class="mt-1 w-full rounded-sm border border-slate-300 px-2 py-1.5 text-sm text-slate-950"
                >
            </label>

            <label class="block text-xs font-medium text-slate-500">
                Parts margin target (%)
                <input
                    type="number"
                    name="parts_margin_target_percent"
                    min="1"
                    max="99"
                    required
                    value="{{ old('parts_margin_target_percent', $excellenceTargets['parts_margin_target_percent']) }}"
                    class="mt-1 w-full rounded-sm border border-slate-300 px-2 py-1.5 text-sm text-slate-950"
                >
            </label>

            <label class="block text-xs font-medium text-slate-500">
                Monthly fixed costs ($)
                <input
                    type="number"
                    name="monthly_fixed_costs"
                    min="0"
                    step="0.01"
                    value="{{ old('monthly_fixed_costs', $excellenceTargets['monthly_fixed_costs_cents'] !== null ? number_format($excellenceTargets['monthly_fixed_costs_cents'] / 100, 2, '.', '') : '') }}"
                    placeholder="25000.00"
                    class="mt-1 w-full rounded-sm border border-slate-300 px-2 py-1.5 text-sm text-slate-950"
                >
                <span class="mt-1 block text-[11px] text-slate-400">Break-even on Margin Health. Usually matches <button type="button" class="font-semibold underline decoration-slate-300 hover:text-slate-700" @click="setActive('overhead')">Shop Overhead</button> monthly total.</span>
            </label>

            <label class="block text-xs font-medium text-slate-500">
                Labor sales mix target (%)
                <input
                    type="number"
                    name="labor_sales_target_percent"
                    min="1"
                    max="99"
                    required
                    value="{{ old('labor_sales_target_percent', $excellenceTargets['labor_sales_target_percent']) }}"
                    class="mt-1 w-full rounded-sm border border-slate-300 px-2 py-1.5 text-sm text-slate-950"
                >
            </label>

            <label class="block text-xs font-medium text-slate-500">
                Parts sales mix target (%)
                <input
                    type="number"
                    name="parts_sales_target_percent"
                    min="1"
                    max="99"
                    required
                    value="{{ old('parts_sales_target_percent', $excellenceTargets['parts_sales_target_percent']) }}"
                    class="mt-1 w-full rounded-sm border border-slate-300 px-2 py-1.5 text-sm text-slate-950"
                >
            </label>
        </div>

        <div class="mt-4 border-t border-slate-200 pt-4">
            <p class="text-[11px] font-bold uppercase tracking-wide text-slate-400">P&amp;L &amp; tax estimates</p>
            <div class="mt-3 grid gap-3 sm:grid-cols-2">
                <label class="block text-xs font-medium text-slate-500">
                    Net profit target (%)
                    <input
                        type="number"
                        name="net_profit_target_percent"
                        min="1"
                        max="99"
                        required
                        value="{{ old('net_profit_target_percent', $excellenceTargets['net_profit_target_percent']) }}"
                        class="mt-1 w-full rounded-sm border border-slate-300 px-2 py-1.5 text-sm text-slate-950"
                    >
                    <span class="mt-1 block text-[11px] text-slate-400">~20% net on service revenue benchmark.</span>
                </label>

                <label class="block text-xs font-medium text-slate-500">
                    Income tax reserve (%)
                    <input
                        type="number"
                        name="income_tax_reserve_percent"
                        min="0"
                        max="99"
                        required
                        value="{{ old('income_tax_reserve_percent', $excellenceTargets['income_tax_reserve_percent']) }}"
                        class="mt-1 w-full rounded-sm border border-slate-300 px-2 py-1.5 text-sm text-slate-950"
                    >
                </label>

                <label class="block text-xs font-medium text-slate-500">
                    Payroll tax reserve (%)
                    <input
                        type="number"
                        name="payroll_tax_reserve_percent"
                        min="0"
                        max="99"
                        required
                        value="{{ old('payroll_tax_reserve_percent', $excellenceTargets['payroll_tax_reserve_percent']) }}"
                        class="mt-1 w-full rounded-sm border border-slate-300 px-2 py-1.5 text-sm text-slate-950"
                    >
                </label>

                <label class="block text-xs font-medium text-slate-500">
                    Monthly payroll tax ($)
                    <input
                        type="number"
                        name="monthly_payroll_tax"
                        min="0"
                        step="0.01"
                        value="{{ old('monthly_payroll_tax', ($excellenceTargets['monthly_payroll_tax_cents'] ?? null) !== null ? number_format($excellenceTargets['monthly_payroll_tax_cents'] / 100, 2, '.', '') : '') }}"
                        placeholder="Optional override"
                        class="mt-1 w-full rounded-sm border border-slate-300 px-2 py-1.5 text-sm text-slate-950"
                    >
                </label>
            </div>
        </div>
        </div>

        <div class="border border-slate-200 bg-white px-3 py-3">
            <p class="text-[11px] font-bold uppercase tracking-[0.08em] text-slate-500">Owner rhythm</p>
            <p class="mt-0.5 text-xs text-slate-500">Daily digest timing and quarterly target review.</p>

        <div class="mt-3 grid gap-3 sm:grid-cols-2">
            <label class="flex items-center gap-2 text-xs font-medium text-slate-600">
                <input
                    type="checkbox"
                    name="owner_digest_enabled"
                    value="1"
                    @checked(old('owner_digest_enabled', $excellenceTargets['owner_digest_enabled']))
                    class="rounded border-slate-300 text-slate-900"
                >
                Email daily owner digest
            </label>

            <label class="block text-xs font-medium text-slate-500">
                Digest send time (shop time)
                <input
                    type="time"
                    name="owner_digest_time"
                    required
                    value="{{ old('owner_digest_time', $excellenceTargets['owner_digest_time']) }}"
                    class="mt-1 w-full rounded-sm border border-slate-300 px-2 py-1.5 text-sm text-slate-950"
                >
            </label>
        </div>

        <div class="mt-3 flex flex-wrap items-center justify-between gap-3 border-t border-slate-200 pt-3">
            <label class="flex items-center gap-2 text-xs font-medium text-slate-600">
                <input
                    type="checkbox"
                    name="mark_target_reviewed"
                    value="1"
                    class="rounded border-slate-300 text-slate-900"
                >
                Mark quarterly target review complete today
            </label>
            @if ($excellenceTargetReview)
                <p class="text-[11px] text-slate-500">Last review: {{ $excellenceTargetReview }}</p>
            @endif
        </div>
        </div>

        <div class="flex flex-wrap gap-2">
            <button type="submit" class="rounded-sm bg-slate-950 px-4 py-2 text-xs font-bold text-white hover:bg-slate-900">Save owner targets</button>
            <a href="{{ route('operations.reports.operational', ['tab' => 'margin-health']) }}" class="rounded-sm border border-slate-300 px-4 py-2 text-xs font-bold text-slate-800 hover:border-slate-400">Open Margin Health</a>
            <a href="{{ route('operations.owner.day-review') }}" class="rounded-sm border border-slate-300 px-4 py-2 text-xs font-bold text-slate-800 hover:border-slate-400">Day Review</a>
        </div>
    </form>
</section>
