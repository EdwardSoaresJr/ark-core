{{-- Labor Policies — Settings → Financial Rules --}}
@php
    /** @var array $laborPoliciesMatrix */
    /** @var array $laborPolicyPreview */
    $matrix = $laborPoliciesMatrix;
    $preview = $laborPolicyPreview;
    $shopDefault = $matrix['shop_default'] ?? null;
@endphp

<div
    class="mt-4 space-y-6"
    x-data="{
        editing: null,
        openEditor(classKey, className, posture, postureLabel, rateCents, effectiveFrom) {
            const rate = rateCents === null || rateCents === undefined
                ? ''
                : (Number(rateCents) / 100).toFixed(2);
            this.editing = {
                operation_class_key: classKey,
                operation_class_name: className,
                billing_posture: posture,
                posture_label: postureLabel,
                hourly_rate: rate,
                effective_from: effectiveFrom || @js(now()->toDateString()),
                change_reason: '',
            };
        },
        closeEditor() {
            this.editing = null;
        },
    }"
>
    <div class="border border-slate-200 bg-slate-50 px-3 py-2">
        <p class="text-[11px] font-bold uppercase tracking-wide text-slate-500">Labor Policies</p>
        <p class="mt-0.5 text-xs leading-5 text-slate-600">
            Rows are operation classes. Columns are billing postures. Each cell is an independent policy rate.
            Click a cell to edit one policy. The shop currently keeps
            <span class="font-semibold text-slate-700">RepairPal, Warranty, Comeback, and Internal</span>
            equal across classes by configuration — the matrix does not force that.
        </p>
    </div>

    @if ($shopDefault)
        <div class="border border-slate-300 bg-white px-3 py-3">
            <p class="text-[11px] font-bold uppercase tracking-wide text-slate-500">Default labor category</p>
            <p class="mt-1 text-sm font-semibold text-slate-950">
                {{ $shopDefault['labor_category_name'] }}
                @if ($shopDefault['operation_class_name'])
                    <span class="font-normal text-slate-500">→</span>
                    {{ $shopDefault['operation_class_name'] }}
                @endif
            </p>
            <p class="mt-1 text-xs leading-5 text-slate-600">
                Owned by Labor Categories. Pricing only displays it.
                <button
                    type="button"
                    @click="setFinancialTab('labor')"
                    class="font-semibold text-slate-800 underline decoration-slate-300 hover:text-slate-950"
                >Change under Financial Rules → Labor</button>.
            </p>
        </div>
    @endif

    @error('billing_posture')
        <div class="border border-rose-200 bg-rose-50 px-3 py-2 text-xs font-medium text-rose-800">{{ $message }}</div>
    @enderror
    @error('hourly_rate')
        <div class="border border-rose-200 bg-rose-50 px-3 py-2 text-xs font-medium text-rose-800">{{ $message }}</div>
    @enderror
    @error('effective_from')
        <div class="border border-rose-200 bg-rose-50 px-3 py-2 text-xs font-medium text-rose-800">{{ $message }}</div>
    @enderror

    <div class="overflow-x-auto border border-slate-300">
        <table class="min-w-full border-collapse text-sm">
            <thead>
                <tr class="bg-slate-100">
                    <th scope="col" class="border-b border-r border-slate-300 px-3 py-2 text-left text-[11px] font-bold uppercase tracking-wide text-slate-500">Operation Class</th>
                    @foreach ($matrix['postures'] as $posture)
                        <th scope="col" class="border-b border-r border-slate-300 px-3 py-2 text-left text-[11px] font-bold uppercase tracking-wide text-slate-500 last:border-r-0">{{ $posture['label'] }}</th>
                    @endforeach
                </tr>
            </thead>
            <tbody>
                @foreach ($matrix['operation_classes'] as $class)
                    <tr class="{{ ! empty($class['is_shop_default']) ? 'bg-slate-50' : 'bg-white' }}">
                        <th scope="row" class="border-b border-r border-slate-200 px-3 py-2 text-left text-xs font-semibold text-slate-950">
                            {{ $class['name'] }}
                            @if (! empty($class['is_shop_default']))
                                <span class="ml-1 inline-block rounded-sm bg-slate-900 px-1.5 py-0.5 text-[10px] font-bold uppercase tracking-wide text-white">Default</span>
                            @endif
                        </th>
                        @foreach ($matrix['postures'] as $posture)
                            @php
                                $cell = $matrix['cells'][$class['key']][$posture['value']] ?? null;
                            @endphp
                            <td class="border-b border-r border-slate-200 p-0 last:border-r-0">
                                <button
                                    type="button"
                                    class="block w-full px-3 py-2 text-left tabular-nums text-slate-950 hover:bg-slate-50 focus:bg-slate-100 focus:outline-none"
                                    @click="openEditor(
                                        @js($class['key']),
                                        @js($class['name']),
                                        @js($posture['value']),
                                        @js($posture['label']),
                                        @js($cell['hourly_rate_cents'] ?? null),
                                        @js($cell['effective_from'] ?? null)
                                    )"
                                >
                                    {{ $cell['rate_display'] ?? '—' }}
                                </button>
                            </td>
                        @endforeach
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    <div
        x-show="editing"
        x-cloak
        class="max-w-md border border-slate-300 bg-white p-4 shadow-sm"
    >
        <template x-if="editing">
            <form method="POST" action="{{ route('operations.settings.shop.labor-policies.update') }}">
                @csrf
                @method('PATCH')
                <input type="hidden" name="billing_posture" :value="editing.billing_posture">
                <input type="hidden" name="operation_class_key" :value="editing.operation_class_key">

                <p class="text-[11px] font-bold uppercase tracking-wide text-slate-500">Edit policy</p>
                <p class="mt-1 text-sm font-semibold text-slate-950">
                    <span x-text="editing.operation_class_name"></span>
                    <span class="font-normal text-slate-500"> · </span>
                    <span x-text="editing.posture_label"></span>
                </p>

                <label class="mt-4 block text-xs font-medium text-slate-500">
                    Hourly Rate
                    <div class="mt-1 flex max-w-xs rounded-md border border-slate-300 bg-white focus-within:border-slate-500">
                        <span class="inline-flex items-center rounded-l-md border-r border-slate-200 bg-slate-50 px-2 text-xs text-slate-500">$</span>
                        <input
                            name="hourly_rate"
                            x-model="editing.hourly_rate"
                            required
                            inputmode="decimal"
                            class="min-w-0 flex-1 rounded-r-md border-0 px-3 py-2 text-sm tabular-nums text-slate-950 focus:ring-0"
                        >
                    </div>
                </label>

                <label class="mt-3 block text-xs font-medium text-slate-500">
                    Effective Date
                    <input
                        type="date"
                        name="effective_from"
                        x-model="editing.effective_from"
                        required
                        class="mt-1 w-full max-w-xs rounded-md border border-slate-300 px-3 py-2 text-sm text-slate-950"
                    >
                </label>

                <label class="mt-3 block text-xs font-medium text-slate-500">
                    Reason <span class="font-normal text-slate-400">(optional)</span>
                    <input
                        name="change_reason"
                        x-model="editing.change_reason"
                        maxlength="255"
                        class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm text-slate-950"
                    >
                </label>

                <div class="mt-4 flex items-center justify-end gap-2 border-t border-slate-200 pt-3">
                    <button type="button" @click="closeEditor()" class="min-h-9 rounded-md px-3 text-sm font-medium text-slate-600 hover:bg-slate-50">
                        Cancel
                    </button>
                    <button type="submit" class="min-h-9 rounded-md bg-slate-950 px-4 text-sm font-semibold text-white hover:bg-slate-800">
                        Save
                    </button>
                </div>
            </form>
        </template>
    </div>

    <div class="max-w-md border border-slate-300 bg-white p-4">
        <p class="text-[11px] font-bold uppercase tracking-wide text-slate-500">Resolver Preview</p>
        <p class="mt-0.5 text-xs text-slate-500">Read-only check that policy resolves the same way estimates will.</p>

        <form method="GET" action="{{ route('operations.settings.shop.edit') }}" class="mt-3 space-y-3">
            <input type="hidden" name="section" value="financial">
            <input type="hidden" name="financial-tab" value="labor-policies">

            <label class="block text-xs font-medium text-slate-500">
                Billing Posture
                <select name="lp_posture" class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm text-slate-950" onchange="this.form.submit()">
                    @foreach ($matrix['postures'] as $posture)
                        <option value="{{ $posture['value'] }}" @selected($preview['billing_posture'] === $posture['value'])>{{ $posture['label'] }}</option>
                    @endforeach
                </select>
            </label>

            <label class="block text-xs font-medium text-slate-500">
                Operation Class
                <select name="lp_class" class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm text-slate-950" onchange="this.form.submit()">
                    @foreach ($matrix['operation_classes'] as $class)
                        <option value="{{ $class['key'] }}" @selected($preview['operation_class_key'] === $class['key'])>
                            {{ $class['name'] }}@if (! empty($class['is_shop_default'])) (Default)@endif
                        </option>
                    @endforeach
                </select>
            </label>
        </form>

        <div class="mt-4 border-t border-slate-200 pt-3">
            <p class="text-[11px] font-bold uppercase tracking-wide text-slate-500">Resolved Rate</p>
            @if ($preview['error'])
                <p class="mt-1 text-sm font-medium text-rose-700">{{ $preview['error'] }}</p>
            @else
                <p class="mt-1 text-2xl font-black tabular-nums text-slate-950">{{ $preview['rate_display'] }}</p>
            @endif
        </div>
    </div>
</div>
