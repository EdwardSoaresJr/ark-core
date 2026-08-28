@php
    $isNoteLine = $line->type->isNote();
    $returnMode = $returnMode ?? null;
    $privacyInputId = $privacyInputId ?? 'note-private-'.$line->id;
    $hideInlineChrome = $hideInlineChrome ?? false;
    $workspaceModalForm = $workspaceModalForm ?? null;
@endphp

<div id="line-{{ $line->id }}" @class([
    'scroll-mt-24',
    'border border-slate-300 bg-white px-3 py-2 ring-1 ring-slate-100' => ! $hideInlineChrome,
])>
    @unless ($hideInlineChrome)
        <div class="mb-2 flex items-center justify-between gap-3 text-xs">
            <span class="font-semibold text-slate-500">Editing note</span>
            <a
                href="{{ $cancelUrl }}"
                data-refresh-scope="worksheet"
                @click.prevent="editLine($event)"
                class="inline-flex h-8 w-8 items-center justify-center rounded-sm text-slate-500 hover:bg-slate-50 hover:text-slate-900"
                aria-label="Cancel editing"
                title="Cancel editing"
            >
                <svg aria-hidden="true" class="h-4 w-4" viewBox="0 0 20 20" fill="none">
                    <path d="M6 6l8 8M14 6l-8 8" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" />
                </svg>
            </a>
        </div>
    @endunless
    <form
        id="line-update-{{ $line->id }}"
        method="POST"
        action="{{ route('operations.repair-orders.lines.update', [$repairOrder, $line]) }}"
        data-refresh-scope="worksheet"
        data-continuity-focus="#line-{{ $line->id }}"
        @if ($workspaceModalForm) data-workspace-modal-form="{{ $workspaceModalForm }}" @endif
        @submit.prevent="submitWorksheetForm($event)"
        class="space-y-2"
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
        <label class="block text-[11px] font-medium text-slate-500">
            Note
            <textarea name="description" rows="3" required class="mt-1 w-full rounded-sm border border-slate-300 bg-white px-3 py-1.5 text-sm italic text-slate-700">{{ $line->description }}</textarea>
        </label>
        @include('operations.repair-orders.partials.repair-order-note-privacy-field', [
            'audience' => [
                'advisor' => $line->isVisibleToAdvisor(),
                'technician' => $line->isVisibleToTechnician(),
                'customer' => $line->isVisibleToCustomer(),
            ],
            'inputId' => $privacyInputId,
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
