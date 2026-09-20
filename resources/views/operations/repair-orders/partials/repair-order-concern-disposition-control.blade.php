@php
    $concernDispositions = App\Ark\Operations\RepairOrders\RepairOrderConcernDisposition::cases();
    $dispositionToneStyle = $concern->disposition->worksheetToneStyle();
    $authorViaModal = (bool) ($authorViaModal ?? false);
@endphp

@if ($isTerminal ?? false)
    <div class="flex items-center gap-0.5">
        @include('operations.repair-orders.partials.repair-order-concern-disposition-badge', [
            'concern' => $concern,
        ])
        <x-operations.help-tip
            :text="$concern->disposition->helpText()"
            label="Customer decision help for {{ $concern->summary }}"
        />
    </div>
@elseif ($authorViaModal)
    <button
        type="button"
        class="ops-builder-present-chip"
        style="{{ $dispositionToneStyle }}"
        title="Customer decision"
        @click="window.dispatchEvent(new CustomEvent('ark-workspace-modal-open', { detail: { task: 'concern-disposition', context: { concernId: {{ $concern->id }} }, invokeEl: $event.currentTarget } }))"
    >
        {{ $concern->disposition->label() }}
    </button>
@else
    <div class="flex items-center gap-0.5">
        <form
            method="POST"
            action="{{ route('operations.repair-orders.concerns.disposition', [$repairOrder, $concern]) }}"
            data-refresh-scope="worksheet"
            data-continuity-focus="#concern-{{ $concern->id }} select[name='disposition']"
            @submit.prevent="submitWorksheetForm($event)"
        >
            @csrf
            @method('PATCH')
            <input type="hidden" name="{{ App\Ark\Operations\RepairOrders\RepairOrderConcurrency::FIELD }}" value="{{ $estimateVersion }}">
            <label class="sr-only" for="concern-disposition-{{ $concern->id }}">Customer decision for {{ $concern->summary }}</label>
            <select
                id="concern-disposition-{{ $concern->id }}"
                name="disposition"
                class="ops-disposition-select"
                style="{{ $dispositionToneStyle }}"
                onchange="preserveRepairOrderConcernScroll({{ $concern->id }}); this.form.requestSubmit()"
            >
                @foreach ($concernDispositions as $disposition)
                    <option
                        value="{{ $disposition->value }}"
                        title="{{ $disposition->helpText() }}"
                        @selected($concern->disposition === $disposition)
                    >{{ $disposition->label() }}</option>
                @endforeach
            </select>
        </form>
        <x-operations.help-tip
            :items="App\Ark\Operations\RepairOrders\RepairOrderConcernDisposition::advisorHelpOverviewItems()"
            title="Scope disposition"
            label="Scope disposition help"
            class="ops-help-tip--above"
        />
    </div>
@endif
