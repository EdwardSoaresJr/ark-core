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

{{--
    ark-worksheet-draft.js finds this editor by data-mileage-inline: its fields sit outside a form.
    Each field is ONE button (label + value) so the whole visible In/Out area opens the editor,
    including the "-" empty state. The editing input is a sibling, never nested in the button.
--}}
@php
    $fields = [
        'in' => ['Studly' => 'In', 'label' => 'In', 'name' => 'Mileage in', 'display' => $displayIn, 'placeholder' => $legacyMileageIn ? number_format($legacyMileageIn) : null],
        'out' => ['Studly' => 'Out', 'label' => 'Out', 'name' => 'Mileage out', 'display' => $displayOut, 'placeholder' => null],
    ];
@endphp

@if ($mileageInlineAsFacts)
    <dt class="col-4 text-secondary fw-normal">Mileage</dt>
    <dd
        class="col-8 d-flex flex-wrap align-items-center gap-2 fw-medium ark-mileage-fields"
@else
    <div class="mt-1.5 grid grid-cols-[4.75rem_minmax(0,1fr)] gap-x-2 text-xs leading-4">
        <div class="font-semibold text-slate-500">Mileage</div>
        <div
            class="min-w-0 flex flex-wrap items-center gap-2 font-semibold text-slate-800"
@endif
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
        @foreach ($fields as $key => $field)
            @php
                $studly = $field['Studly'];
                $triggerClass = $mileageInlineAsFacts
                    ? 'ark-mileage-trigger'
                    : 'inline-flex min-h-[2.75rem] items-center gap-1 rounded px-1.5 -mx-1.5 cursor-pointer text-left hover:bg-slate-100 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-1 focus-visible:outline-blue-600 md:min-h-7';
                $labelClass = $mileageInlineAsFacts ? 'ark-mileage-trigger__label' : 'text-slate-500 uppercase tracking-wide';
                $valueClass = $mileageInlineAsFacts ? 'ark-mileage-trigger__value' : 'font-semibold text-slate-800 underline decoration-dotted underline-offset-2';
                $inputClass = $mileageInlineAsFacts ? 'form-control form-control-sm ark-mileage-input' : 'rounded border border-slate-300 px-1.5 py-0.5 text-xs min-h-[2.75rem] md:min-h-0';
            @endphp
            @if (! $loop->first)
                <span class="{{ $mileageInlineAsFacts ? 'text-secondary' : 'text-slate-400' }}" aria-hidden="true">·</span>
            @endif
            <span class="inline-flex items-center gap-1" data-mileage-field="{{ $key }}">
                <button
                    type="button"
                    x-show="!editing{{ $studly }}"
                    @click="open{{ $studly }}()"
                    class="{{ $triggerClass }}"
                    aria-label="Edit {{ strtolower($field['name']) }}"
                    :aria-label="'Edit {{ strtolower($field['name']) }}, ' + (display{{ $studly }}() === '-' ? 'not set' : display{{ $studly }}())"
                    data-mileage-trigger="{{ $key }}"
                >
                    <span class="{{ $labelClass }}">{{ $field['label'] }}</span>
                    <span class="{{ $valueClass }}" x-text="display{{ $studly }}()">{{ $field['display'] }}</span>
                </button>
                <span x-show="editing{{ $studly }}" x-cloak class="{{ $mileageInlineAsFacts ? 'text-secondary text-uppercase small' : 'text-slate-500 uppercase tracking-wide' }}" aria-hidden="true">{{ $field['label'] }}</span>
                <input
                    x-show="editing{{ $studly }}"
                    x-cloak
                    x-ref="mileage{{ $studly }}Input"
                    type="text"
                    inputmode="numeric"
                    autocomplete="off"
                    aria-label="{{ $field['name'] }}"
                    x-model="mileage{{ $studly }}"
                    @blur.debounce.350ms="finish{{ $studly }}()"
                    @keydown.enter.prevent="$event.target.blur()"
                    @keydown.escape.prevent="cancel{{ $studly }}()"
                    @if ($field['placeholder'])
                        placeholder="{{ $field['placeholder'] }}"
                    @endif
                    class="{{ $inputClass }}"
                    style="width: 6.5rem"
                    data-mileage-input="{{ $key }}"
                >
            </span>
        @endforeach
        <span x-show="saving" x-cloak class="{{ $mileageInlineAsFacts ? 'small text-secondary' : 'text-[11px] text-slate-500' }}">Saving…</span>
        <span x-show="error" x-cloak x-text="error" class="{{ $mileageInlineAsFacts ? 'small text-danger' : 'text-[11px] text-red-600' }}"></span>
@if ($mileageInlineAsFacts)
    </dd>
@else
        </div>
    </div>
@endif
