@php
    $currentProduction = $concern->productionStatus();
    $returnMode = $returnMode ?? null;
@endphp

@if (! $concern->tracksProduction())
    @if ($isTerminal ?? false)
        <span class="badge bg-secondary-lt">Not in production</span>
    @endif
@elseif ($isTerminal ?? false)
    <span class="badge bg-{{ $currentProduction->value === 'completed' ? 'green' : 'secondary' }}-lt">
        {{ $currentProduction->workStatusLabel() }}
    </span>
@else
    <form
        method="POST"
        action="{{ route('operations.repair-orders.concerns.production-status', [$repairOrder, $concern]) }}"
        data-refresh-scope="worksheet"
        data-continuity-focus="#concern-{{ $concern->id }} [data-concern-work-status-choices] .dropdown-toggle"
        @submit.prevent="submitWorksheetForm($event)"
    >
        @csrf
        @method('PATCH')
        <input type="hidden" name="{{ App\Ark\Operations\RepairOrders\RepairOrderConcurrency::FIELD }}" value="{{ $estimateVersion }}">
        @if ($returnMode)
            <input type="hidden" name="return_mode" value="{{ $returnMode }}">
        @endif
        <input type="hidden" name="production_status" value="{{ $currentProduction->value }}">
        <div class="dropdown" data-concern-work-status-choices>
            <button
                type="button"
                class="btn btn-sm dropdown-toggle"
                data-bs-toggle="dropdown"
                aria-expanded="false"
                aria-label="Work status for {{ $concern->summary }}: {{ $currentProduction->workStatusLabel() }}"
                data-production-current="{{ $currentProduction->value }}"
            >
                <x-tabler.icon :name="$currentProduction->tablerIcon()" class="text-{{ $currentProduction->tablerColor() }} me-1" />
                <span class="fw-medium">{{ $currentProduction->workStatusLabel() }}</span>
            </button>
            <div class="dropdown-menu">
                <h6 class="dropdown-header">Work status</h6>
                @foreach (App\Ark\Operations\RepairOrders\ScopeProductionStatus::cases() as $status)
                    @php $isCurrent = $status === $currentProduction; @endphp
                    <button
                        type="button"
                        class="dropdown-item"
                        title="{{ $status->helpText() }}"
                        data-production-option="{{ $status->value }}"
                        @if ($isCurrent)
                            aria-current="true"
                        @else
                            @click="preserveRepairOrderConcernScroll({{ $concern->id }}); $el.form.elements.production_status.value = @js($status->value); $el.form.requestSubmit()"
                        @endif
                    >
                        <x-tabler.icon :name="$status->tablerIcon()" class="text-{{ $status->tablerColor() }} me-2" />
                        <span class="me-3">{{ $status->workStatusLabel() }}</span>
                        @if ($isCurrent)
                            <x-tabler.icon name="check" class="ms-auto text-primary" />
                        @endif
                    </button>
                @endforeach
            </div>
        </div>
    </form>
@endif
