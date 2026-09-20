@php
    $legacyMileageIn = $repairOrder->mileage_in === null ? $repairOrder->resolvedMileageIn() : null;
    $displayIn = $repairOrder->mileage_in !== null
        ? number_format((int) $repairOrder->mileage_in)
        : ($legacyMileageIn ? number_format((int) $legacyMileageIn) : '—');
    $displayOut = $repairOrder->mileage_out !== null
        ? number_format((int) $repairOrder->mileage_out)
        : '—';
@endphp

<div
    class="ops-mileage-inline"
    x-data="arkRepairOrderMileage({
        url: @js(route('operations.repair-orders.mileage.update', $repairOrder)),
        csrf: @js(csrf_token()),
        estimateVersionField: @js(App\Ark\Operations\RepairOrders\RepairOrderConcurrency::FIELD),
        estimateVersion: @js($estimateVersion),
        mileageIn: @js($repairOrder->mileage_in),
        mileageOut: @js($repairOrder->mileage_out),
        legacyMileageIn: @js($legacyMileageIn),
    })"
>
    <div class="grid grid-cols-[4.75rem_minmax(0,1fr)] gap-x-2 text-xs leading-4">
        <dt class="font-semibold text-slate-500">Mileage</dt>
        <dd class="flex min-w-0 flex-wrap items-center gap-x-1.5 gap-y-0.5">
            <span class="flex items-center gap-1">
                <span class="text-[10px] font-bold uppercase tracking-[0.06em] text-slate-400">In</span>
                <button
                    type="button"
                    x-show="!editingIn"
                    @click="openIn()"
                    class="ops-mileage-value"
                    x-text="displayIn()"
                >{{ $displayIn }}</button>
                <input
                    x-show="editingIn"
                    x-cloak
                    x-ref="mileageInInput"
                    type="text"
                    inputmode="numeric"
                    autocomplete="off"
                    x-model="mileageIn"
                    @blur.debounce.350ms="finishIn()"
                    @keydown.enter.prevent="$event.target.blur()"
                    @keydown.escape.prevent="cancelIn()"
                    @if ($legacyMileageIn)
                        placeholder="{{ number_format($legacyMileageIn) }}"
                    @endif
                    class="ops-mileage-input"
                >
            </span>
            <span class="text-slate-300" aria-hidden="true">·</span>
            <span class="flex items-center gap-1">
                <span class="text-[10px] font-bold uppercase tracking-[0.06em] text-slate-400">Out</span>
                <button
                    type="button"
                    x-show="!editingOut"
                    @click="openOut()"
                    class="ops-mileage-value"
                    x-text="displayOut()"
                >{{ $displayOut }}</button>
                <input
                    x-show="editingOut"
                    x-cloak
                    x-ref="mileageOutInput"
                    type="text"
                    inputmode="numeric"
                    autocomplete="off"
                    x-model="mileageOut"
                    @blur.debounce.350ms="finishOut()"
                    @keydown.enter.prevent="$event.target.blur()"
                    @keydown.escape.prevent="cancelOut()"
                    class="ops-mileage-input"
                >
            </span>
            <span x-show="saving" x-cloak class="text-[10px] font-semibold text-slate-400">Saving…</span>
            <span x-show="error" x-cloak x-text="error" class="text-[10px] font-semibold text-rose-700"></span>
        </dd>
    </div>
</div>
