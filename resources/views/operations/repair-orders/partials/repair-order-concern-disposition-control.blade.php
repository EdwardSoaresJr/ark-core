@php
    $concernDispositions = App\Ark\Operations\RepairOrders\RepairOrderConcernDisposition::cases();
    $dispositionColor = $concern->disposition->tablerColor();
    $authorViaModal = (bool) ($authorViaModal ?? false);
@endphp

<div id="concern-disposition-{{ $concern->id }}" data-continuity-region="concern-authorization">
@if ($isTerminal ?? false)
    <div class="d-flex align-items-center gap-1">
        <span class="badge bg-{{ $dispositionColor }}-lt">{{ $concern->disposition->label() }}</span>
        <x-operations.help-tip
            :text="$concern->disposition->helpText()"
            label="Customer decision help for {{ $concern->summary }}"
        />
    </div>
@elseif ($authorViaModal)
    <button
        type="button"
        class="btn btn-sm bg-{{ $dispositionColor }}-lt"
        data-disposition-tone="{{ $concern->disposition->value }}"
        title="Customer decision"
        @click="window.dispatchEvent(new CustomEvent('ark-workspace-modal-open', { detail: { task: 'concern-disposition', context: { concernId: {{ $concern->id }} }, invokeEl: $event.currentTarget } }))"
    >
        {{ $concern->disposition->label() }}
    </button>
@else
    <div class="d-flex align-items-center gap-1">
        <form
            method="POST"
            action="{{ route('operations.repair-orders.concerns.disposition', [$repairOrder, $concern]) }}"
            data-refresh-scope="worksheet"
            data-worksheet-continuity
            data-continuity-focus="#concern-{{ $concern->id }} select[name='disposition']"
            @submit.prevent="submitWorksheetForm($event)"
        >
            @csrf
            @method('PATCH')
            <input type="hidden" name="{{ App\Ark\Operations\RepairOrders\RepairOrderConcurrency::FIELD }}" value="{{ $estimateVersion }}">
            <label class="visually-hidden" for="concern-disposition-{{ $concern->id }}">Customer decision for {{ $concern->summary }}</label>
            <select
                id="concern-disposition-{{ $concern->id }}"
                name="disposition"
                class="form-select form-select-sm border-{{ $dispositionColor }} text-{{ $dispositionColor }}"
                data-disposition-tone="{{ $concern->disposition->value }}"
                style="width: auto"
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
</div>
