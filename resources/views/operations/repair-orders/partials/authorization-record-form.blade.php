@php
    use App\Ark\Operations\Approvals\ApprovalSource;

    $defaultApprovedAmount = number_format(($approvedTotals ?? $totals)->totalCents() / 100, 2, '.', '');
@endphp

@can(App\Ark\Runtime\Authorization\ArkCapability::RepairOrdersManage->value)
    @unless ($isTerminal ?? false)
        <div class="border-b border-slate-200 px-3 py-3">
            <p class="text-[10px] font-bold uppercase tracking-[0.08em] text-slate-400">Record approval</p>
            <p class="mt-0.5 text-[11px] leading-4 text-slate-500">Record how the customer authorized the scopes already marked approved on this estimate.</p>

            <form
                method="POST"
                action="{{ route('operations.repair-orders.authorization.store', $repairOrder) }}"
                data-refresh-scope="auth"
                data-saving-label="Recording authorization…"
                data-continuity-focus="#financial-rail button[type='submit']"
                @submit.prevent="window.arkWorksheetFormSubmit($event)"
                class="mt-3 space-y-3"
            >
                @csrf
                <input type="hidden" name="{{ App\Ark\Operations\RepairOrders\RepairOrderConcurrency::FIELD }}" value="{{ $estimateVersion }}">

                <div class="grid gap-2">
                    <label class="block text-[11px] font-medium text-slate-500">
                        Method
                        <select name="source" required class="mt-1 w-full rounded-sm border border-slate-300 px-2 py-1.5 text-sm text-slate-950">
                            @foreach (ApprovalSource::cases() as $source)
                                <option value="{{ $source->value }}" @selected(old('source', ApprovalSource::InPerson->value) === $source->value)>{{ $source->label() }}</option>
                            @endforeach
                        </select>
                    </label>

                    <label class="block text-[11px] font-medium text-slate-500">
                        Approved by
                        <input
                            type="text"
                            name="approved_by"
                            value="{{ old('approved_by', $repairOrder->customer->name) }}"
                            required
                            class="mt-1 w-full rounded-sm border border-slate-300 px-2 py-1.5 text-sm text-slate-950"
                        >
                    </label>

                    <label class="block text-[11px] font-medium text-slate-500">
                        Authorized amount
                        <input
                            type="text"
                            name="approved_amount"
                            value="{{ old('approved_amount', $defaultApprovedAmount) }}"
                            inputmode="decimal"
                            class="mt-1 w-full rounded-sm border border-slate-300 px-2 py-1.5 text-sm text-slate-950"
                            placeholder="0.00"
                        >
                        <span class="mt-0.5 block text-[10px] text-slate-400">Defaults to approved scope total when left blank.</span>
                    </label>

                    <label class="block text-[11px] font-medium text-slate-500">
                        Notes
                        <textarea name="notes" rows="2" class="mt-1 w-full rounded-sm border border-slate-300 px-2 py-1.5 text-sm text-slate-950" placeholder="Optional authorization notes">{{ old('notes') }}</textarea>
                    </label>
                </div>

                @error('authorization')
                    <p class="text-xs text-red-600">{{ $message }}</p>
                @enderror

                <button type="submit" class="w-full rounded-sm bg-slate-950 px-3 py-2 text-sm font-semibold text-white hover:bg-slate-800">
                    Record approval
                </button>
            </form>
        </div>
    @endunless
@endcan
