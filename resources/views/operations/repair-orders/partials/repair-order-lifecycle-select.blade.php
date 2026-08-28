@props([
    'repairOrder',
    'lifecycleOptions',
    'closeVariantOptions',
    'estimateVersion',
    'lifecycleSelect' => null,
    'balanceProjection' => null,
    'reviewRequest' => null,
])

@php
    use App\Ark\Operations\RepairOrders\RepairOrderLifecycleSelectProjection;
    use App\Ark\Operations\RepairOrders\RepairOrderLostReason;

    $lifecycleSelect = $lifecycleSelect ?? RepairOrderLifecycleSelectProjection::forRepairOrder(
        $repairOrder,
        collect($lifecycleOptions),
        is_array($closeVariantOptions) ? $closeVariantOptions : iterator_to_array($closeVariantOptions),
        auth()->user(),
        $balanceProjection,
    );
    $formId = 'lifecycle-form-'.$repairOrder->repair_order_id;
@endphp

@if (! $repairOrder->isTerminal())
        @if ($errors->has('lifecycle'))
            <p class="mb-1 max-w-xs text-[11px] font-semibold leading-4 text-rose-800">{{ $errors->first('lifecycle') }}</p>
        @endif

        <form
            id="{{ $formId }}"
            method="POST"
            action="{{ route('operations.repair-orders.lifecycle.update', $repairOrder) }}"
            class="ops-review-toolbar-control"
            data-lifecycle-form
            data-refresh-scope="toolbar"
        >
            @csrf
            @method('PATCH')
            <input type="hidden" name="{{ App\Ark\Operations\RepairOrders\RepairOrderConcurrency::FIELD }}" value="{{ $estimateVersion }}">
            <label for="status-move-{{ $repairOrder->repair_order_id }}" class="sr-only">Repair order status</label>
            <select
                id="status-move-{{ $repairOrder->repair_order_id }}"
                name="status"
                required
                class="h-9 min-w-[10rem] rounded-sm border-slate-300 bg-white py-1 pl-2 pr-8 text-xs font-semibold text-slate-700"
                data-lifecycle-select
                data-current-status="{{ $repairOrder->status->value }}"
            >
                <option value="{{ $repairOrder->status->value }}" selected>{{ $repairOrder->statusDisplayLabel() }}</option>
                @foreach ($lifecycleSelect->statusOptions as $option)
                    <option
                        value="{{ $option['value'] }}"
                        @disabled($option['disabled'])
                        @if ($option['blockedReason'] !== null) title="{{ $option['blockedReason'] }}" data-blocked-reason="{{ $option['blockedReason'] }}" @endif
                    >
                        {{ $option['label'] }}
                    </option>
                @endforeach
                @foreach ($lifecycleSelect->closeOptions as $option)
                    <option
                        value="{{ $option['value'] }}"
                        @disabled($option['blockedReason'] !== null)
                        @if ($option['blockedReason'] !== null) title="{{ $option['blockedReason'] }}" data-blocked-reason="{{ $option['blockedReason'] }}" @endif
                    >
                        {{ $option['label'] }}
                    </option>
                @endforeach
                @if ($lifecycleSelect->showLostCloseOption)
                    <option value="closed:lost">Closed — Lost</option>
                @endif
            </select>

            <div
                class="mt-2 hidden max-w-md space-y-2 rounded-sm border border-amber-200 bg-amber-50 p-2"
                data-lost-reason-panel
                hidden
            >
                <p class="text-[11px] font-semibold leading-4 text-amber-950">Why is this repair order closing lost?</p>
                <label for="lost-reason-key-{{ $repairOrder->repair_order_id }}" class="sr-only">Lost reason</label>
                <select
                    id="lost-reason-key-{{ $repairOrder->repair_order_id }}"
                    name="lost_reason_key"
                    class="h-9 w-full rounded-sm border-amber-200 bg-white px-2 text-xs font-semibold text-slate-800"
                >
                    <option value="">Choose lost reason…</option>
                    @foreach (RepairOrderLostReason::options() as $option)
                        <option value="{{ $option['value'] }}" @selected(old('lost_reason_key') === $option['value'])>
                            {{ $option['label'] }}
                        </option>
                    @endforeach
                </select>
                <label for="lost-reason-note-{{ $repairOrder->repair_order_id }}" class="sr-only">Lost reason note</label>
                <input
                    id="lost-reason-note-{{ $repairOrder->repair_order_id }}"
                    type="text"
                    name="lost_reason_note"
                    value="{{ old('lost_reason_note') }}"
                    maxlength="500"
                    placeholder="Required when reason is Other"
                    class="h-9 w-full rounded-sm border-amber-200 bg-white px-2 text-xs text-slate-800"
                >
                <div class="flex items-center gap-2">
                    <button type="submit" class="rounded-sm bg-amber-900 px-2 py-1 text-xs font-bold text-white">
                        Confirm close lost
                    </button>
                    <button type="button" class="text-xs font-semibold text-amber-900" data-lost-reason-cancel>
                        Cancel
                    </button>
                </div>
            </div>
        </form>

@endif
