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
    $formId = 'repair-order-workflow';
@endphp

@if (! $repairOrder->isTerminal())
    <div class="ark-ro-command-bar__status">
        @if ($errors->has('lifecycle'))
            <div class="small text-danger mb-1">{{ $errors->first('lifecycle') }}</div>
        @endif

        <form
            id="{{ $formId }}"
            method="POST"
            action="{{ route('operations.repair-orders.lifecycle.update', $repairOrder) }}"
            class="m-0"
            data-lifecycle-form
            data-continuity-region="workflow"
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
                class="form-select"
                data-lifecycle-select
                data-current-status="{{ $repairOrder->status->value }}"
            >
                <option value="{{ $repairOrder->status->value }}" selected>{{ $repairOrder->statusDisplayLabel() }}</option>
                @foreach ($lifecycleSelect->statusOptions as $option)
                    <option
                        value="{{ $option['value'] }}"
                        @if ($option['blockedReason'] !== null) data-blocked-reason="{{ $option['blockedReason'] }}" @endif
                    >
                        {{ $option['label'] }}@if (filled($option['blockedReason'])) - {{ $option['blockedReason'] }}@endif
                    </option>
                @endforeach
                @foreach ($lifecycleSelect->closeOptions as $option)
                    <option
                        value="{{ $option['value'] }}"
                        @if ($option['blockedReason'] !== null) data-blocked-reason="{{ $option['blockedReason'] }}" @endif
                    >
                        {{ $option['label'] }}@if (filled($option['blockedReason'])) - {{ $option['blockedReason'] }}@endif
                    </option>
                @endforeach
                @if ($lifecycleSelect->showLostCloseOption)
                    <option value="closed:lost">Closed - Lost</option>
                @endif
            </select>

            <div
                class="hidden card card-sm mt-2 border-warning" style="max-width: 20rem"
                data-lost-reason-panel
                hidden
            >
                <div class="card-body p-2"><div class="fw-bold small mb-2">Why is this repair order closing lost?</div>
                <label for="lost-reason-key-{{ $repairOrder->repair_order_id }}" class="sr-only">Lost reason</label>
                <select
                    id="lost-reason-key-{{ $repairOrder->repair_order_id }}"
                    name="lost_reason_key"
                    class="form-select mb-2"
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
                    class="form-control mb-2"
                >
                <div class="btn-list">
                    <button type="submit" class="btn btn-warning btn-sm">
                        Confirm close lost
                    </button>
                    <button type="button" class="btn btn-link btn-sm" data-lost-reason-cancel>
                        Cancel
                    </button>
                </div>
                </div>
            </div>
        </form>
    </div>
@endif
