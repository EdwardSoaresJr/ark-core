@props([
    'kind',
    'storeRoute',
    'row',
])

@php
    $isFollowUp = $kind === 'follow-up';
    $summaryLabel = $isFollowUp ? 'Follow-up' : 'Task';
    $quickAddId = 'ops-decision-quick-add-'.$row['repair_order_shop_number'].'-'.$kind;
    $defaultDue = now()->addDay()->setHour(9)->setMinute(0)->format('Y-m-d\TH:i');
    $defaultNote = ($isFollowUp ? 'Follow up: ' : 'Task: ')
        .$row['customer_name']
        .' · RO #'.$row['repair_order_shop_number'];
@endphp

<details class="ops-work-item-quick-add" id="{{ $quickAddId }}">
    <summary
        class="ops-call-queue__action ops-call-queue__action--ghost cursor-pointer list-none"
        data-work-item-quick-add-label="{{ $summaryLabel }}"
    >{{ $summaryLabel }}</summary>
    <div class="ops-work-item-quick-add-panel" data-decision-quick-add-owner="{{ $quickAddId }}">
        <div class="ops-work-item-quick-add-head">
            <p class="ops-work-item-quick-add-context">
                {{ $summaryLabel }} · {{ $row['customer_name'] }}
                <span class="text-slate-400">·</span>
                RO #{{ $row['repair_order_shop_number'] }}
            </p>
            <button
                type="button"
                class="ops-work-item-quick-add-close"
                data-work-item-quick-add-cancel
                aria-label="Cancel {{ strtolower($summaryLabel) }}"
            >×</button>
        </div>
        <form
            method="POST"
            action="{{ $storeRoute }}"
            class="ops-work-item-quick-add-form space-y-2"
            data-default-notes="{{ $defaultNote }}"
            data-default-due="{{ $defaultDue }}"
        >
            @csrf
            @if (! empty($row['customer_id']))
                <input type="hidden" name="customer_id" value="{{ (int) $row['customer_id'] }}">
            @endif
            @if (! empty($row['vehicle_id']))
                <input type="hidden" name="vehicle_id" value="{{ (int) $row['vehicle_id'] }}">
            @endif
            <input type="hidden" name="repair_order_shop_number" value="{{ (int) $row['repair_order_shop_number'] }}">
            <label class="block">
                <span class="text-[10px] font-bold uppercase tracking-wide text-slate-400">What needs to happen?</span>
                <textarea
                    name="notes"
                    rows="2"
                    required
                    maxlength="1000"
                    class="mt-0.5 w-full rounded-sm border border-slate-300 bg-white px-2 py-1.5 text-sm text-slate-950"
                >{{ $defaultNote }}</textarea>
            </label>
            <label class="block">
                <span class="text-[10px] font-bold uppercase tracking-wide text-slate-400">Remind on</span>
                <input
                    type="datetime-local"
                    name="due_at"
                    required
                    value="{{ $defaultDue }}"
                    class="mt-0.5 h-8 w-full rounded-sm border border-slate-300 bg-white px-2 text-sm text-slate-950"
                >
            </label>
            <p class="ops-work-item-quick-add-feedback hidden text-[11px] font-semibold text-emerald-800" role="status"></p>
            <p class="ops-work-item-quick-add-error hidden text-[11px] font-semibold text-rose-800" role="alert"></p>
            <div class="ops-work-item-quick-add-actions">
                <button
                    type="button"
                    class="ops-work-item-quick-add-cancel"
                    data-work-item-quick-add-cancel
                >Cancel</button>
                <button type="submit" class="ops-work-item-quick-add-save">
                    Save {{ strtolower($summaryLabel) }}
                </button>
            </div>
        </form>
    </div>
</details>
