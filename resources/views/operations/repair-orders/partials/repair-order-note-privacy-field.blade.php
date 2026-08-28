@php
    $audience = $audience ?? [
        'advisor' => true,
        'technician' => false,
        'customer' => false,
    ];
    $inputId = $inputId ?? 'note-audience-'.uniqid();
@endphp

<div
    x-data="{
        advisor: @js((bool) ($audience['advisor'] ?? true)),
        technician: @js((bool) ($audience['technician'] ?? false)),
        customer: @js((bool) ($audience['customer'] ?? false)),
        audienceHint: '',
        _hintTimer: null,
        guardAudience(which) {
            if (this.advisor || this.technician || this.customer) {
                this.audienceHint = '';
                return;
            }

            this[which] = true;
            this.audienceHint = 'Choose another view first, then you can turn this one off.';
            clearTimeout(this._hintTimer);
            this._hintTimer = setTimeout(() => { this.audienceHint = ''; }, 4000);
        }
    }"
    class="ops-note-privacy-field space-y-1.5"
>
    <input type="hidden" name="visible_to_advisor" :value="advisor ? '1' : '0'">
    <input type="hidden" name="visible_to_technician" :value="technician ? '1' : '0'">
    <input type="hidden" name="visible_to_customer" :value="customer ? '1' : '0'">

    <p class="text-[11px] font-semibold uppercase tracking-[0.08em] text-slate-500">Visible to</p>

    <div class="grid grid-cols-3 gap-2">
        <label class="flex cursor-pointer flex-col items-center gap-1 text-center" for="{{ $inputId }}-advisor">
            <span class="inline-flex items-center gap-1.5">
                <input
                    id="{{ $inputId }}-advisor"
                    type="checkbox"
                    x-model="advisor"
                    x-on:change="guardAudience('advisor')"
                    class="rounded border-slate-300 text-slate-800"
                >
                <span
                    class="ops-note-visibility ops-note-visibility--advisor ops-note-visibility--selectable inline-flex items-center gap-1 rounded-sm border border-emerald-300 bg-emerald-100 px-1.5 py-0.5 text-[10px] font-extrabold uppercase tracking-wide text-emerald-900"
                    :class="advisor ? 'ring-2 ring-emerald-200 opacity-100' : 'opacity-40'"
                >Advisor</span>
            </span>
            <span class="text-[10px] font-normal leading-3 text-slate-500">Repair Order</span>
        </label>

        <label class="flex cursor-pointer flex-col items-center gap-1 text-center" for="{{ $inputId }}-technician">
            <span class="inline-flex items-center gap-1.5">
                <input
                    id="{{ $inputId }}-technician"
                    type="checkbox"
                    x-model="technician"
                    x-on:change="guardAudience('technician')"
                    class="rounded border-slate-300 text-slate-800"
                >
                <span
                    class="ops-note-visibility ops-note-visibility--technician ops-note-visibility--selectable inline-flex items-center gap-1 rounded-sm border border-sky-300 bg-sky-100 px-1.5 py-0.5 text-[10px] font-extrabold uppercase tracking-wide text-sky-900"
                    :class="technician ? 'ring-2 ring-sky-200 opacity-100' : 'opacity-40'"
                >Technician</span>
            </span>
            <span class="text-[10px] font-normal leading-3 text-slate-500">Tech Sheet</span>
        </label>

        <label class="flex cursor-pointer flex-col items-center gap-1 text-center" for="{{ $inputId }}-customer">
            <span class="inline-flex items-center gap-1.5">
                <input
                    id="{{ $inputId }}-customer"
                    type="checkbox"
                    x-model="customer"
                    x-on:change="guardAudience('customer')"
                    class="rounded border-slate-300 text-slate-800"
                >
                <span
                    class="ops-note-visibility ops-note-visibility--customer ops-note-visibility--selectable inline-flex items-center gap-1 rounded-sm border border-rose-300 bg-rose-100 px-1.5 py-0.5 text-[10px] font-extrabold uppercase tracking-wide text-rose-900"
                    :class="customer ? 'ring-2 ring-rose-200 opacity-100' : 'opacity-40'"
                >Customer</span>
            </span>
            <span class="text-[10px] font-normal leading-3 text-slate-500">Estimate / Portal</span>
        </label>
    </div>

    <p
        x-show="audienceHint"
        x-cloak
        x-text="audienceHint"
        class="text-center text-[11px] font-medium leading-4 text-amber-800"
        role="status"
    ></p>
</div>
