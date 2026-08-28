@php
    use App\Ark\Operations\RepairOrders\PartProcurementState;
    use App\Ark\Operations\RepairOrders\RepairOrderLineItemPresentation;
    use App\Ark\Runtime\Authorization\ArkCapability;

    $currentState = $line->procurementState();
    $transitionOptions = $partStateOptions ?? $line->availableProcurementTransitions();
    $statusChipTone = $statusChipTone ?? RepairOrderLineItemPresentation::procurementChipTone($currentState);
    $selectOptions = collect([$currentState])
        ->merge($transitionOptions)
        ->unique(fn (PartProcurementState $state): string => $state->value)
        ->filter(function (PartProcurementState $state): bool {
            if ($state !== PartProcurementState::Canceled) {
                return true;
            }

            return auth()->user()?->can(ArkCapability::ProcurementCancel->value) ?? false;
        })
        ->values()
        ->all();
    $canManage = auth()->user()?->can(ArkCapability::RepairOrdersManage->value) ?? false;
    $isInteractive = $canManage && count($selectOptions) > 1;
@endphp

@if ($isInteractive)
    <form
        method="POST"
        action="{{ route('operations.repair-orders.lines.procurement.update', [$repairOrder, $line]) }}"
        data-procurement-form
        data-line-card-ignore
        data-refresh-scope="worksheet"
        data-continuity-focus="#line-{{ $line->id }}"
        class="ops-chip-form inline-flex"
    >
        @csrf
        @method('PATCH')
        @if ($returnMode ?? null)
            <input type="hidden" name="return_mode" value="{{ $returnMode }}">
        @endif
        @if ($estimateVersion ?? null)
            <input type="hidden" name="{{ App\Ark\Operations\RepairOrders\RepairOrderConcurrency::FIELD }}" value="{{ $estimateVersion }}">
        @endif
        <label for="part-procurement-{{ $line->id }}" class="sr-only">Part status for {{ $line->description }}</label>
        <select
            id="part-procurement-{{ $line->id }}"
            name="procurement_state"
            data-procurement-select
            data-current-state="{{ $currentState->value }}"
            class="ops-chip ops-chip--status ops-chip--select ops-chip--{{ $statusChipTone }}"
        >
            @foreach ($selectOptions as $state)
                <option value="{{ $state->value }}" @selected($state === $currentState)>
                    {{ $state->label($line->part_source) }}
                </option>
            @endforeach
        </select>
    </form>
@else
    <x-operations.line-item.status-chip
        :label="$line->procurementStateLabel()"
        :tone="$statusChipTone"
    />
@endif
