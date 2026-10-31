@php
    $currentDisposition = $concern->disposition;
@endphp

@if ($isTerminal ?? false)
    <span class="badge bg-{{ $currentDisposition->tablerColor() }}-lt" data-disposition-tone="{{ $currentDisposition->value }}" title="{{ $currentDisposition->helpText() }}">
        <x-tabler.icon :name="$currentDisposition->tablerIcon()" class="me-1" />
        {{ $currentDisposition->decisionLabel() }}
    </span>
@else
    <form
        method="POST"
        action="{{ route('operations.repair-orders.concerns.disposition', [$repairOrder, $concern]) }}"
        data-refresh-scope="worksheet"
        data-worksheet-continuity
        data-continuity-focus="#concern-{{ $concern->id }} [data-concern-decision-choices] .dropdown-toggle"
        @submit.prevent="submitWorksheetForm($event)"
    >
        @csrf
        @method('PATCH')
        <input type="hidden" name="{{ App\Ark\Operations\RepairOrders\RepairOrderConcurrency::FIELD }}" value="{{ $estimateVersion }}">
        <input type="hidden" name="disposition" value="{{ $currentDisposition->value }}">
        <div class="dropdown" data-concern-decision-choices>
            <button
                type="button"
                class="btn btn-sm dropdown-toggle"
                data-bs-toggle="dropdown"
                aria-expanded="false"
                aria-label="Customer decision for {{ $concern->summary }}: {{ $currentDisposition->decisionLabel() }}"
                data-disposition-tone="{{ $currentDisposition->value }}"
            >
                <x-tabler.icon :name="$currentDisposition->tablerIcon()" class="text-{{ $currentDisposition->tablerColor() }} me-1" />
                <span class="fw-medium">{{ $currentDisposition->decisionLabel() }}</span>
            </button>
            <div class="dropdown-menu">
                <h6 class="dropdown-header">Customer decision</h6>
                @foreach (App\Ark\Operations\RepairOrders\RepairOrderConcernDisposition::cases() as $disposition)
                    @php $isCurrent = $disposition === $currentDisposition; @endphp
                    {{-- Re-saving the current decision would re-record the approved scope, so it only closes the menu. --}}
                    <button
                        type="button"
                        class="dropdown-item"
                        title="{{ $disposition->helpText() }}"
                        data-disposition-option="{{ $disposition->value }}"
                        @if ($isCurrent)
                            aria-current="true"
                        @else
                            @click="preserveRepairOrderConcernScroll({{ $concern->id }}); $el.form.elements.disposition.value = @js($disposition->value); $el.form.requestSubmit()"
                        @endif
                    >
                        <x-tabler.icon :name="$disposition->tablerIcon()" class="text-{{ $disposition->tablerColor() }} me-2" />
                        <span class="me-3">{{ $disposition->decisionLabel() }}</span>
                        @if ($isCurrent)
                            <x-tabler.icon name="check" class="ms-auto text-primary" />
                        @endif
                    </button>
                @endforeach
            </div>
        </div>
    </form>
@endif
