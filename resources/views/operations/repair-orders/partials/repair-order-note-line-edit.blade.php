@php
    $isNoteLine = $line->type->isNote();
    $returnMode = $returnMode ?? null;
    $privacyInputId = $privacyInputId ?? 'note-private-'.$line->id;
    $hideInlineChrome = $hideInlineChrome ?? false;
    $workspaceModalForm = $workspaceModalForm ?? null;
@endphp

<div id="line-editor-{{ $line->id }}" @class([
    'scroll-mt-24',
    'card card-sm' => ! $hideInlineChrome,
])>
    <div @class(['card-body' => ! $hideInlineChrome])>
    @unless ($hideInlineChrome)
        <div class="d-flex align-items-center justify-content-between gap-3 small mb-2">
            <span class="fw-medium text-secondary">Editing note</span>
            <a
                href="{{ $cancelUrl }}"
                data-refresh-scope="worksheet"
                @click.prevent="editLine($event)"
                class="btn btn-ghost-secondary btn-icon btn-sm"
                aria-label="Cancel editing"
                title="Cancel editing"
            >
                <x-tabler.icon name="x" />
            </a>
        </div>
    @endunless
    <form
        id="line-update-{{ $line->id }}"
        method="POST"
        action="{{ route('operations.repair-orders.lines.update', [$repairOrder, $line]) }}"
        data-worksheet-continuity
        data-refresh-scope="worksheet"
        data-continuity-focus="#line-{{ $line->id }}"
        @if ($workspaceModalForm) data-workspace-modal-form="{{ $workspaceModalForm }}" @endif
        @submit.prevent="submitWorksheetForm($event)"
        class="d-flex flex-column gap-2"
    >
        @csrf
        @method('PATCH')
        @if ($returnMode)
            <input type="hidden" name="return_mode" value="{{ $returnMode }}">
        @endif
        <input type="hidden" name="{{ App\Ark\Operations\RepairOrders\RepairOrderConcurrency::FIELD }}" value="{{ $estimateVersion }}">
        <input type="hidden" name="repair_order_concern_id" value="{{ $line->repair_order_concern_id }}">
        <input type="hidden" name="repair_order_work_group_id" value="{{ $line->repair_order_work_group_id }}">
        <input type="hidden" name="type" value="note">
        <input type="hidden" name="unit_price" value="0">
        <input type="hidden" name="quantity" value="1">
        <label class="d-block">
            <span class="form-label">Note</span>
            <textarea name="description" rows="3" required class="form-control fst-italic">{{ $line->description }}</textarea>
        </label>
        @include('operations.repair-orders.partials.repair-order-note-privacy-field', [
            'audience' => [
                'advisor' => $line->isVisibleToAdvisor(),
                'technician' => $line->isVisibleToTechnician(),
                'customer' => $line->isVisibleToCustomer(),
            ],
            'inputId' => $privacyInputId,
            'ui' => 'tabler',
        ])
    </form>
    @unless ($hideInlineChrome)
        @include('operations.repair-orders.partials.repair-order-line-edit-actions', [
            'line' => $line,
            'repairOrder' => $repairOrder,
            'estimateVersion' => $estimateVersion,
            'gridPlacement' => false,
        ])
    @endunless
    </div>
</div>
