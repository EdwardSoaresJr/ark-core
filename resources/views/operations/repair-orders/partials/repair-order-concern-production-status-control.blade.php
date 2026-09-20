@php
    $productionStatuses = App\Ark\Operations\RepairOrders\ScopeProductionStatus::cases();
    $productionToneStyle = $concern->productionStatus()->worksheetToneStyle();
    $returnMode = $returnMode ?? null;
    $authorViaModal = (bool) ($authorViaModal ?? false);
@endphp

@if (! $concern->tracksProduction())
    @if ($isTerminal ?? false)
        <span class="ops-state-pill ops-state-pill--neutral">Not in production</span>
    @endif
@elseif ($isTerminal ?? false)
    <span class="ops-state-pill ops-state-pill--{{ $concern->productionStatus()->value === 'completed' ? 'approved' : 'neutral' }}">
        {{ $concern->productionStatus()->label() }}
    </span>
@elseif ($authorViaModal)
    <button
        type="button"
        class="ops-builder-present-chip"
        style="{{ $productionToneStyle }}"
        title="Production status"
        @click="window.dispatchEvent(new CustomEvent('ark-workspace-modal-open', { detail: { task: 'concern-production', context: { concernId: {{ $concern->id }} }, invokeEl: $event.currentTarget } }))"
    >
        {{ $concern->productionStatus()->label() }}
    </button>
@else
    <div class="flex items-center gap-0.5">
        <form
            method="POST"
            action="{{ route('operations.repair-orders.concerns.production-status', [$repairOrder, $concern]) }}"
            data-refresh-scope="worksheet"
            data-continuity-focus="#concern-{{ $concern->id }} select[name='production_status']"
            @submit.prevent="submitWorksheetForm($event)"
        >
            @csrf
            @method('PATCH')
            <input type="hidden" name="{{ App\Ark\Operations\RepairOrders\RepairOrderConcurrency::FIELD }}" value="{{ $estimateVersion }}">
            @if ($returnMode)
                <input type="hidden" name="return_mode" value="{{ $returnMode }}">
            @endif
            <label class="sr-only" for="concern-production-status-{{ $concern->id }}">Production status for {{ $concern->summary }}</label>
            <select
                id="concern-production-status-{{ $concern->id }}"
                name="production_status"
                class="ops-production-status-select"
                style="{{ $productionToneStyle }}"
                onchange="preserveRepairOrderConcernScroll({{ $concern->id }}); this.form.requestSubmit()"
            >
                @foreach ($productionStatuses as $status)
                    <option
                        value="{{ $status->value }}"
                        title="{{ $status->helpText() }}"
                        @selected($concern->productionStatus() === $status)
                    >{{ $status->label() }}</option>
                @endforeach
            </select>
        </form>
        <x-operations.help-tip
            :text="$concern->productionStatus()->helpText()"
            label="Scope production status help for {{ $concern->summary }}"
        />
    </div>
@endif
