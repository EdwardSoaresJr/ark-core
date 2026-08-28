@php
    $defaultPlateState = $defaultPlateState ?? \App\Ark\Operations\Settings\ShopSettings::current()->state;
@endphp

<form
    x-data="arkVehicleDecode({
        decodeUrl: @js(route('operations.vehicles.decode-vin')),
        csrfToken: @js(csrf_token()),
    })"
    method="POST"
    action="{{ route('operations.customers.vehicles.store', $customer) }}"
    @submit="guardSubmit($event)"
    class="ops-intake-vehicle-add-form"
>
    @csrf
    <input type="hidden" name="intake" value="1">
    @include('operations.intake.partials.intake-workspace-hidden')
    <div class="ops-intake-field">
        <label for="intake-vehicle-vin" class="ops-index-field-label">VIN</label>
        <div class="ops-intake-vin-row">
            <input
                id="intake-vehicle-vin"
                name="vin"
                value="{{ old('vin') }}"
                maxlength="17"
                autocomplete="off"
                autocapitalize="characters"
                placeholder="Scan or type 17-character VIN"
                class="ops-intake-control ops-intake-control--vin"
                @if ($needsVehicle) autofocus @endif
            >
            <button type="button" @click="decode($event)" :disabled="decoding" class="ops-intake-vin-decode ops-index-btn ops-index-btn--ghost">
                <span x-show="!decoding">Decode VIN</span>
                <span x-show="decoding" x-cloak>Decoding…</span>
            </button>
        </div>
    </div>
    <div class="ops-intake-field">
        <label for="intake-vehicle-plate" class="ops-index-field-label">Plate</label>
        <div class="ops-intake-vin-row">
            <input
                id="intake-vehicle-plate"
                name="plate"
                value="{{ old('plate') }}"
                autocomplete="off"
                autocapitalize="characters"
                placeholder="License plate"
                class="ops-intake-control"
            >
            <input
                id="intake-vehicle-plate-state"
                name="plate_state"
                value="{{ old('plate_state', $defaultPlateState) }}"
                autocomplete="off"
                autocapitalize="characters"
                placeholder="ST"
                class="ops-intake-control ops-intake-control--plate-state"
            >
            <button type="button" @click="decodePlate($event)" :disabled="decoding" class="ops-intake-vin-decode ops-index-btn ops-index-btn--ghost">
                <span x-show="!decoding">Decode plate</span>
                <span x-show="decoding" x-cloak>Decoding…</span>
            </button>
        </div>
    </div>
    <p x-show="message" x-text="message" class="ops-intake-vehicle-add-message"></p>
    <div class="ops-intake-fields ops-intake-fields--2">
        <div class="ops-intake-field">
            <label for="intake-vehicle-year" class="ops-index-field-label">Year</label>
            <input id="intake-vehicle-year" name="year" value="{{ $prefillYear ?? old('year') }}" inputmode="numeric" class="ops-intake-control">
        </div>
        <div class="ops-intake-field">
            <label for="intake-vehicle-make" class="ops-index-field-label">Make</label>
            <input id="intake-vehicle-make" name="make" value="{{ $prefillMake ?? old('make') }}" class="ops-intake-control" @blur="normalizeField($event)">
        </div>
    </div>
    <div class="ops-intake-fields ops-intake-fields--2">
        <div class="ops-intake-field">
            <label for="intake-vehicle-model" class="ops-index-field-label">Model</label>
            <input id="intake-vehicle-model" name="model" value="{{ $prefillModel ?? old('model') }}" class="ops-intake-control" @blur="normalizeField($event)">
        </div>
        <div class="ops-intake-field">
            <label for="intake-vehicle-trim" class="ops-index-field-label">Trim</label>
            <input id="intake-vehicle-trim" name="trim" value="{{ old('trim') }}" placeholder="Optional" class="ops-intake-control" @blur="normalizeField($event)">
        </div>
    </div>
    <div class="ops-intake-field">
        <label for="intake-vehicle-engine" class="ops-index-field-label">Engine</label>
        <input id="intake-vehicle-engine" name="engine" value="{{ old('engine') }}" placeholder="Optional" class="ops-intake-control">
    </div>
    <div class="ops-intake-fields ops-intake-fields--2">
        <div class="ops-intake-field">
            <label for="intake-vehicle-drive" class="ops-index-field-label">Drive</label>
            <input id="intake-vehicle-drive" name="drive" value="{{ old('drive') }}" placeholder="AWD, FWD, 4WD" class="ops-intake-control">
        </div>
        <div class="ops-intake-field">
            <label for="intake-vehicle-transmission" class="ops-index-field-label">Transmission</label>
            <input id="intake-vehicle-transmission" name="transmission" value="{{ old('transmission') }}" placeholder="Automatic, Manual" class="ops-intake-control">
        </div>
    </div>
    <div class="ops-intake-fields ops-intake-fields--2">
        <div class="ops-intake-field">
            <label for="intake-vehicle-color" class="ops-index-field-label">Color</label>
            <input id="intake-vehicle-color" name="color" value="{{ old('color') }}" placeholder="Optional" class="ops-intake-control">
        </div>
        <div class="ops-intake-field">
            <label for="intake-vehicle-nickname" class="ops-index-field-label">Tag / nickname</label>
            <input id="intake-vehicle-nickname" name="nickname" value="{{ old('nickname') }}" placeholder="Optional" class="ops-intake-control">
        </div>
    </div>
    @error('year')<p class="text-xs font-semibold text-rose-700">{{ $message }}</p>@enderror
    @error('make')<p class="text-xs font-semibold text-rose-700">{{ $message }}</p>@enderror
    @error('model')<p class="text-xs font-semibold text-rose-700">{{ $message }}</p>@enderror
    @error('vin')<p class="text-xs font-semibold text-rose-700">{{ $message }}</p>@enderror
    <div class="ops-intake-panel-actions">
        <button type="submit" class="ops-index-btn ops-index-btn--primary ops-intake-vehicle-add-submit">
            {{ $needsVehicle ? 'Add vehicle & continue' : 'Save new vehicle' }}
        </button>
    </div>
</form>
