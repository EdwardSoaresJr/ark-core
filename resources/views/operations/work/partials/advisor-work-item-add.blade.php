@props([
    'label',
    'storeRoute',
])

@php
    $defaultDue = now()->addDay()->setHour(9)->setMinute(0)->format('Y-m-d\TH:i');
@endphp

<details class="ops-work-item-add">
    <summary class="ops-page-link shrink-0 cursor-pointer list-none">{{ $label }}</summary>
    <div class="ops-work-item-add-panel">
        <div class="ops-work-item-add-head">
            <p class="ops-work-item-add-title">{{ $label }}</p>
            <button
                type="button"
                class="ops-work-item-quick-add-close"
                data-work-item-quick-add-cancel
                aria-label="Cancel"
            >×</button>
        </div>
        <form method="POST" action="{{ $storeRoute }}" class="space-y-2">
            @csrf
            <textarea name="notes" rows="2" required maxlength="1000" placeholder="What needs to happen?" class="w-full rounded-sm border border-slate-300 bg-white px-2.5 py-1.5 text-sm text-slate-950 placeholder:text-slate-400"></textarea>
            <div class="grid gap-2 sm:grid-cols-2">
                <label class="block">
                    <span class="text-[10px] font-bold uppercase tracking-wide text-slate-400">Remind on</span>
                    <input type="datetime-local" name="due_at" required value="{{ $defaultDue }}" class="mt-0.5 h-9 w-full rounded-sm border border-slate-300 bg-white px-2 text-sm text-slate-950">
                </label>
                <label class="block">
                    <span class="text-[10px] font-bold uppercase tracking-wide text-slate-400">RO # (optional)</span>
                    <input type="number" name="repair_order_shop_number" min="1" placeholder="Shop RO #" class="mt-0.5 h-9 w-full rounded-sm border border-slate-300 bg-white px-2 text-sm text-slate-950 placeholder:text-slate-400">
                </label>
            </div>
            <div class="ops-work-item-quick-add-actions">
                <button type="button" class="ops-work-item-quick-add-cancel" data-work-item-quick-add-cancel>Cancel</button>
                <button type="submit" class="ops-work-item-quick-add-save">Save</button>
            </div>
        </form>
    </div>
</details>
