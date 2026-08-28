@php
    /** @var \App\Ark\Operations\Vehicles\Vehicle|null $vehicle */
    $vehicle = $vehicle ?? null;
    $defaultPlateState = $defaultPlateState
        ?? $vehicle?->plate_state
        ?? \App\Ark\Operations\Settings\ShopSettings::current()->state;
    $inputClass = $inputClass ?? 'mt-1 w-full rounded-sm border border-slate-300 bg-white px-3 py-1.5 text-sm text-slate-950';
    $buttonClass = $buttonClass ?? 'self-end rounded-sm border border-slate-300 bg-white px-3 py-1.5 text-xs font-semibold text-slate-700 hover:border-slate-400 disabled:opacity-60';
@endphp

<div class="grid gap-2 sm:grid-cols-[1fr_auto]">
    <label class="block text-[11px] font-medium text-slate-500">
        VIN
        <input
            name="vin"
            value="{{ old('vin', $vehicle?->authoritativeVin()) }}"
            maxlength="17"
            autocomplete="off"
            autocapitalize="characters"
            class="{{ $inputClass }}"
        />
    </label>
    <button type="button" @click="decode($event)" :disabled="decoding" class="{{ $buttonClass }}">
        <span x-show="!decoding">Decode VIN</span>
        <span x-show="decoding" x-cloak>Decoding…</span>
    </button>
</div>
<div class="grid gap-2 sm:grid-cols-[1fr_4.5rem_auto]">
    <label class="block text-[11px] font-medium text-slate-500">
        Plate
        <input name="plate" value="{{ old('plate', $vehicle?->plate) }}" autocomplete="off" autocapitalize="characters" class="{{ $inputClass }}" />
    </label>
    <label class="block text-[11px] font-medium text-slate-500">
        St
        <input name="plate_state" value="{{ old('plate_state', $defaultPlateState) }}" maxlength="32" autocomplete="off" autocapitalize="characters" class="{{ $inputClass }}" />
    </label>
    <button type="button" @click="decodePlate($event)" :disabled="decoding" class="{{ $buttonClass }}">
        <span x-show="!decoding">Decode plate</span>
        <span x-show="decoding" x-cloak>Decoding…</span>
    </button>
</div>
<p x-show="message" x-text="message" class="text-xs text-slate-500"></p>
<div class="grid gap-2 sm:grid-cols-3">
    <label class="block text-[11px] font-medium text-slate-500">
        Year
        <input name="year" value="{{ old('year', $vehicle?->year) }}" inputmode="numeric" class="{{ $inputClass }}" />
    </label>
    <label class="block text-[11px] font-medium text-slate-500">
        Make
        <input name="make" value="{{ old('make', $vehicle?->make) }}" class="{{ $inputClass }}" @blur="normalizeField($event)" />
    </label>
    <label class="block text-[11px] font-medium text-slate-500">
        Model
        <input name="model" value="{{ old('model', $vehicle?->model) }}" class="{{ $inputClass }}" @blur="normalizeField($event)" />
    </label>
</div>
<div class="grid gap-2 sm:grid-cols-2">
    <label class="block text-[11px] font-medium text-slate-500">
        Trim
        <input name="trim" value="{{ old('trim', $vehicle?->trim) }}" class="{{ $inputClass }}" @blur="normalizeField($event)" />
    </label>
    <label class="block text-[11px] font-medium text-slate-500">
        Engine
        <input name="engine" value="{{ old('engine', $vehicle?->engine) }}" class="{{ $inputClass }}" />
    </label>
    <label class="block text-[11px] font-medium text-slate-500">
        Drive
        <input name="drive" value="{{ old('drive', $vehicle?->drive) }}" placeholder="AWD, FWD, 4WD" class="{{ $inputClass }}" />
    </label>
    <label class="block text-[11px] font-medium text-slate-500">
        Transmission
        <input name="transmission" value="{{ old('transmission', $vehicle?->transmission) }}" placeholder="Automatic, Manual" class="{{ $inputClass }}" />
    </label>
    <label class="block text-[11px] font-medium text-slate-500">
        Color
        <input name="color" value="{{ old('color', $vehicle?->color) }}" class="{{ $inputClass }}" />
    </label>
    <label class="block text-[11px] font-medium text-slate-500">
        Tag / nickname
        <input name="nickname" value="{{ old('nickname', $vehicle?->nickname) }}" class="{{ $inputClass }}" />
    </label>
</div>
<label class="block text-[11px] font-medium text-slate-500">
    Public notes
    <input name="public_notes" value="{{ old('public_notes', $vehicle?->public_notes) }}" class="{{ $inputClass }}" />
</label>
<label class="block text-[11px] font-medium text-slate-500">
    Private notes
    <input name="private_notes" value="{{ old('private_notes', $vehicle?->private_notes) }}" class="{{ $inputClass }}" />
</label>
