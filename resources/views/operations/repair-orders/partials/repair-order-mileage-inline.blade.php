@php
    $legacyMileageIn = $repairOrder->mileage_in === null ? $repairOrder->resolvedMileageIn() : null;
    $displayIn = $repairOrder->mileage_in !== null
        ? number_format((int) $repairOrder->mileage_in)
        : ($legacyMileageIn ? number_format((int) $legacyMileageIn) : '-');
    $displayOut = $repairOrder->mileage_out !== null
        ? number_format((int) $repairOrder->mileage_out)
        : '-';
    // Tabler Visit facts dl expects sibling dt/dd. Document/service-lane bands keep a self-contained block.
    $mileageInlineAsFacts = (bool) ($mileageInlineAsFacts ?? false);
@endphp

{{-- ark-worksheet-draft.js finds this editor by data-mileage-inline: its fields sit outside a form. --}}
@if ($mileageInlineAsFacts)
    <dt class="col-4 text-secondary fw-normal">Mileage</dt>
    <dd
        class="col-8 d-flex flex-wrap align-items-center gap-2 fw-medium"
        data-mileage-inline
        x-data="arkRepairOrderMileage({
            url: @js(route('operations.repair-orders.mileage.update', $repairOrder)),
            csrf: @js(csrf_token()),
            estimateVersionField: @js(App\Ark\Operations\RepairOrders\RepairOrderConcurrency::FIELD),
            estimateVersion: @js($estimateVersion),
            mileageIn: @js($repairOrder->mileage_in),
            mileageOut: @js($repairOrder->mileage_out),
            legacyMileageIn: @js($legacyMileageIn),
        })"
        x-effect="syncDraftMarker()"
    >
        <span class="d-inline-flex align-items-center gap-1">
            <span class="text-secondary text-uppercase small">In</span>
            <button
                type="button"
                x-show="!editingIn"
                @click="openIn()"
                class="ops-visit-inline-trigger fw-medium"
                aria-label="Edit mileage in"
                x-text="displayIn()"
            >{{ $displayIn }}</button>
            <input
                x-show="editingIn"
                x-cloak
                x-ref="mileageInInput"
                type="text"
                inputmode="numeric"
                autocomplete="off"
                aria-label="Mileage in"
                x-model="mileageIn"
                @blur.debounce.350ms="finishIn()"
                @keydown.enter.prevent="$event.target.blur()"
                @keydown.escape.prevent="cancelIn()"
                @if ($legacyMileageIn)
                    placeholder="{{ number_format($legacyMileageIn) }}"
                @endif
                class="form-control form-control-sm"
                style="width: 6.5rem"
                data-mileage-input="in"
            >
        </span>
        <span class="text-secondary" aria-hidden="true">·</span>
        <span class="d-inline-flex align-items-center gap-1">
            <span class="text-secondary text-uppercase small">Out</span>
            <button
                type="button"
                x-show="!editingOut"
                @click="openOut()"
                class="ops-visit-inline-trigger fw-medium"
                aria-label="Edit mileage out"
                x-text="displayOut()"
            >{{ $displayOut }}</button>
            <input
                x-show="editingOut"
                x-cloak
                x-ref="mileageOutInput"
                type="text"
                inputmode="numeric"
                autocomplete="off"
                aria-label="Mileage out"
                x-model="mileageOut"
                @blur.debounce.350ms="finishOut()"
                @keydown.enter.prevent="$event.target.blur()"
                @keydown.escape.prevent="cancelOut()"
                class="form-control form-control-sm"
                style="width: 6.5rem"
                data-mileage-input="out"
            >
        </span>
        <span x-show="saving" x-cloak class="small text-secondary">Saving…</span>
        <span x-show="error" x-cloak x-text="error" class="small text-danger"></span>
    </dd>
@else
    <div
        class="mt-1.5 grid grid-cols-[4.75rem_minmax(0,1fr)] gap-x-2 text-xs leading-4"
        data-mileage-inline
        x-data="arkRepairOrderMileage({
            url: @js(route('operations.repair-orders.mileage.update', $repairOrder)),
            csrf: @js(csrf_token()),
            estimateVersionField: @js(App\Ark\Operations\RepairOrders\RepairOrderConcurrency::FIELD),
            estimateVersion: @js($estimateVersion),
            mileageIn: @js($repairOrder->mileage_in),
            mileageOut: @js($repairOrder->mileage_out),
            legacyMileageIn: @js($legacyMileageIn),
        })"
        x-effect="syncDraftMarker()"
    >
        <div class="font-semibold text-slate-500">Mileage</div>
        <div class="min-w-0 flex flex-wrap items-center gap-2 font-semibold text-slate-800">
            <span class="inline-flex items-center gap-1">
                <span class="text-slate-500 uppercase tracking-wide">In</span>
                <button
                    type="button"
                    x-show="!editingIn"
                    @click="openIn()"
                    class="font-semibold text-slate-800 underline decoration-dotted underline-offset-2"
                    aria-label="Edit mileage in"
                    x-text="displayIn()"
                >{{ $displayIn }}</button>
                <input
                    x-show="editingIn"
                    x-cloak
                    x-ref="mileageInInput"
                    type="text"
                    inputmode="numeric"
                    autocomplete="off"
                    aria-label="Mileage in"
                    x-model="mileageIn"
                    @blur.debounce.350ms="finishIn()"
                    @keydown.enter.prevent="$event.target.blur()"
                    @keydown.escape.prevent="cancelIn()"
                    @if ($legacyMileageIn)
                        placeholder="{{ number_format($legacyMileageIn) }}"
                    @endif
                    class="rounded border border-slate-300 px-1.5 py-0.5 text-xs"
                    style="width: 6.5rem"
                    data-mileage-input="in"
                >
            </span>
            <span class="text-slate-400" aria-hidden="true">·</span>
            <span class="inline-flex items-center gap-1">
                <span class="text-slate-500 uppercase tracking-wide">Out</span>
                <button
                    type="button"
                    x-show="!editingOut"
                    @click="openOut()"
                    class="font-semibold text-slate-800 underline decoration-dotted underline-offset-2"
                    aria-label="Edit mileage out"
                    x-text="displayOut()"
                >{{ $displayOut }}</button>
                <input
                    x-show="editingOut"
                    x-cloak
                    x-ref="mileageOutInput"
                    type="text"
                    inputmode="numeric"
                    autocomplete="off"
                    aria-label="Mileage out"
                    x-model="mileageOut"
                    @blur.debounce.350ms="finishOut()"
                    @keydown.enter.prevent="$event.target.blur()"
                    @keydown.escape.prevent="cancelOut()"
                    class="rounded border border-slate-300 px-1.5 py-0.5 text-xs"
                    style="width: 6.5rem"
                    data-mileage-input="out"
                >
            </span>
            <span x-show="saving" x-cloak class="text-[11px] text-slate-500">Saving…</span>
            <span x-show="error" x-cloak x-text="error" class="text-[11px] text-red-600"></span>
        </div>
    </div>
@endif
