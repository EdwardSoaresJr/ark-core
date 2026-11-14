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
    $selectId = 'status-move-'.$repairOrder->repair_order_id;
    $menuId = 'status-move-menu-'.$repairOrder->repair_order_id;
    $currentLabel = $repairOrder->statusDisplayLabel();
@endphp

@if (! $repairOrder->isTerminal())
    <div class="row g-0 align-items-baseline ark-ro-visit-commands__status">
        <div class="col-4 text-secondary">Status</div>
        <div class="col-8">
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

                <div class="dropdown">
                    <button
                        type="button"
                        class="ops-visit-inline-trigger fw-medium"
                        data-bs-toggle="dropdown"
                        data-bs-display="static"
                        aria-expanded="false"
                        aria-haspopup="listbox"
                        aria-controls="{{ $menuId }}"
                        aria-label="Repair order status"
                        title="Click to change status"
                        data-lifecycle-trigger
                    >
                        <span class="ops-visit-inline-value">{{ $currentLabel }}</span>
                    </button>
                    <ul
                        id="{{ $menuId }}"
                        class="dropdown-menu"
                        role="listbox"
                        aria-label="Repair order status"
                    >
                        <li>
                            <button
                                type="button"
                                class="dropdown-item active"
                                role="option"
                                aria-selected="true"
                                data-lifecycle-option
                                data-status-value="{{ $repairOrder->status->value }}"
                            >{{ $currentLabel }}</button>
                        </li>
                        @foreach ($lifecycleSelect->statusOptions as $option)
                            <li>
                                <button
                                    type="button"
                                    class="dropdown-item"
                                    role="option"
                                    aria-selected="false"
                                    data-lifecycle-option
                                    data-status-value="{{ $option['value'] }}"
                                    @if ($option['blockedReason'] !== null) data-blocked-reason="{{ $option['blockedReason'] }}" @endif
                                    @if (! empty($option['needsTechnician'])) data-needs-technician="1" @endif
                                >
                                    {{ $option['label'] }}@if (filled($option['blockedReason'])) - {{ $option['blockedReason'] }}@endif
                                </button>
                            </li>
                        @endforeach
                        @foreach ($lifecycleSelect->closeOptions as $option)
                            <li>
                                <button
                                    type="button"
                                    class="dropdown-item"
                                    role="option"
                                    aria-selected="false"
                                    data-lifecycle-option
                                    data-status-value="{{ $option['value'] }}"
                                    @if ($option['blockedReason'] !== null) data-blocked-reason="{{ $option['blockedReason'] }}" @endif
                                >
                                    {{ $option['label'] }}@if (filled($option['blockedReason'])) - {{ $option['blockedReason'] }}@endif
                                </button>
                            </li>
                        @endforeach
                        @if ($lifecycleSelect->showLostCloseOption)
                            <li>
                                <button
                                    type="button"
                                    class="dropdown-item"
                                    role="option"
                                    aria-selected="false"
                                    data-lifecycle-option
                                    data-status-value="closed:lost"
                                >Closed - Lost</button>
                            </li>
                        @endif
                    </ul>
                </div>

                {{-- Visually hidden: keeps deep-link + change handlers on the same select contract. --}}
                <label class="visually-hidden" for="{{ $selectId }}">Repair order status</label>
                <select
                    id="{{ $selectId }}"
                    name="status"
                    required
                    class="visually-hidden"
                    tabindex="-1"
                    aria-hidden="true"
                    data-lifecycle-select
                    data-current-status="{{ $repairOrder->status->value }}"
                >
                    <option value="{{ $repairOrder->status->value }}" selected>{{ $currentLabel }}</option>
                    @foreach ($lifecycleSelect->statusOptions as $option)
                        <option
                            value="{{ $option['value'] }}"
                            @if ($option['blockedReason'] !== null) data-blocked-reason="{{ $option['blockedReason'] }}" @endif
                            @if (! empty($option['needsTechnician'])) data-needs-technician="1" @endif
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

                @php
                    $operationalChallenge = session('operational_closeout_challenge');
                    $showOperationalPanel = is_array($operationalChallenge)
                        && ($operationalChallenge['required'] ?? false) === true
                        && ! empty($operationalChallenge['items']);
                @endphp
                <div
                    class="card card-sm mt-2 border-warning {{ $showOperationalPanel ? '' : 'hidden' }}"
                    style="max-width: 24rem"
                    data-operational-closeout-panel
                    @if (! $showOperationalPanel) hidden @endif
                >
                    <div class="card-body p-2">
                        <div class="fw-bold small mb-1">Unfinished work on this RO</div>
                        <div class="text-secondary small mb-2">ARK found items that have not been completed. Document why you are proceeding — this does not mark the work finished.</div>
                        <ul class="list-unstyled small mb-2" data-operational-closeout-items>
                            @foreach (($operationalChallenge['items'] ?? []) as $item)
                                <li class="mb-1">
                                    <div class="fw-medium">{{ $item['title'] ?? 'Outstanding item' }}</div>
                                    <div class="text-secondary">{{ $item['detail'] ?? '' }}</div>
                                </li>
                            @endforeach
                        </ul>
                        <label for="operational-closeout-reason-{{ $repairOrder->repair_order_id }}" class="form-label small mb-1">Reason for proceeding</label>
                        <textarea
                            id="operational-closeout-reason-{{ $repairOrder->repair_order_id }}"
                            name="operational_closeout_reason"
                            rows="3"
                            maxlength="1000"
                            class="form-control form-control-sm mb-2"
                            placeholder="Example: Customer requested vehicle release. Parts will be installed during a return visit."
                            data-operational-closeout-reason
                        >{{ old('operational_closeout_reason') }}</textarea>
                        <input
                            type="hidden"
                            name="operational_closeout_fingerprint"
                            value="{{ $operationalChallenge['fingerprint'] ?? '' }}"
                            data-operational-closeout-fingerprint
                        >
                        <div class="btn-list">
                            <button type="submit" class="btn btn-warning btn-sm" data-operational-closeout-confirm>
                                Confirm closeout
                            </button>
                            <button type="button" class="btn btn-link btn-sm" data-operational-closeout-cancel>
                                Cancel
                            </button>
                        </div>
                    </div>
                </div>
            </form>
        </div>
    </div>
@endif
