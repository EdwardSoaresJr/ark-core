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
    data-intake-vehicle-form
>
    @csrf
    <input type="hidden" name="intake" value="1">
    @include('operations.intake.partials.intake-workspace-hidden')
    <div class="row g-3">
        <div class="col-12">
            <label for="intake-vehicle-vin" class="form-label">VIN</label>
            <div class="input-group">
                <input
                    id="intake-vehicle-vin"
                    name="vin"
                    value="{{ old('vin') }}"
                    maxlength="17"
                    autocomplete="off"
                    autocapitalize="characters"
                    placeholder="Scan or type 17-character VIN"
                    @class(['form-control', 'is-invalid' => $errors->has('vin')])
                    @if ($needsVehicle) autofocus @endif
                >
                <button type="button" @click="decode($event)" :disabled="decoding" class="btn">
                    <span x-show="!decoding">Decode VIN</span>
                    <span x-show="decoding" x-cloak>Decoding…</span>
                </button>
            </div>
        </div>
        <div class="col-12">
            <label for="intake-vehicle-plate" class="form-label">Plate</label>
            <div class="input-group">
                <input
                    id="intake-vehicle-plate"
                    name="plate"
                    value="{{ old('plate') }}"
                    autocomplete="off"
                    autocapitalize="characters"
                    placeholder="License plate"
                    class="form-control"
                >
                <input
                    id="intake-vehicle-plate-state"
                    name="plate_state"
                    value="{{ old('plate_state', $defaultPlateState) }}"
                    autocomplete="off"
                    autocapitalize="characters"
                    placeholder="ST"
                    aria-label="Plate state"
                    class="form-control flex-grow-0 w-auto"
                    size="3"
                >
                <button type="button" @click="decodePlate($event)" :disabled="decoding" class="btn">
                    <span x-show="!decoding">Decode plate</span>
                    <span x-show="decoding" x-cloak>Decoding…</span>
                </button>
            </div>
        </div>
        <div class="col-12" x-show="message" x-cloak>
            <div x-text="message" class="small" :class="messageIsError ? 'text-danger' : 'text-secondary'"></div>
        </div>
        <div class="col-6">
            <label for="intake-vehicle-year" class="form-label">Year</label>
            <input id="intake-vehicle-year" name="year" value="{{ $prefillYear ?? old('year') }}" inputmode="numeric" @class(['form-control', 'is-invalid' => $errors->has('year')])>
        </div>
        <div class="col-6">
            <label for="intake-vehicle-make" class="form-label">Make</label>
            <input id="intake-vehicle-make" name="make" value="{{ $prefillMake ?? old('make') }}" @class(['form-control', 'is-invalid' => $errors->has('make')]) @blur="normalizeField($event)">
        </div>
        <div class="col-6">
            <label for="intake-vehicle-model" class="form-label">Model</label>
            <input id="intake-vehicle-model" name="model" value="{{ $prefillModel ?? old('model') }}" @class(['form-control', 'is-invalid' => $errors->has('model')]) @blur="normalizeField($event)">
        </div>
        <div class="col-6">
            <label for="intake-vehicle-trim" class="form-label">Trim</label>
            <input id="intake-vehicle-trim" name="trim" value="{{ old('trim') }}" placeholder="Optional" class="form-control" @blur="normalizeField($event)">
        </div>
        <div class="col-12">
            <label for="intake-vehicle-engine" class="form-label">Engine</label>
            <input id="intake-vehicle-engine" name="engine" value="{{ old('engine') }}" placeholder="Optional" class="form-control">
        </div>
        <div class="col-6">
            <label for="intake-vehicle-drive" class="form-label">Drive</label>
            <input id="intake-vehicle-drive" name="drive" value="{{ old('drive') }}" placeholder="AWD, FWD, 4WD" class="form-control">
        </div>
        <div class="col-6">
            <label for="intake-vehicle-transmission" class="form-label">Transmission</label>
            <input id="intake-vehicle-transmission" name="transmission" value="{{ old('transmission') }}" placeholder="Automatic, Manual" class="form-control">
        </div>
        <div class="col-6">
            <label for="intake-vehicle-color" class="form-label">Color</label>
            <input id="intake-vehicle-color" name="color" value="{{ old('color') }}" placeholder="Optional" class="form-control">
        </div>
        <div class="col-6">
            <label for="intake-vehicle-nickname" class="form-label">Tag / nickname</label>
            <input id="intake-vehicle-nickname" name="nickname" value="{{ old('nickname') }}" placeholder="Optional" class="form-control">
        </div>
        @if ($errors->hasAny(['year', 'make', 'model', 'vin']))
            <div class="col-12">
                @foreach (['year', 'make', 'model', 'vin'] as $field)
                    @error($field)<div class="small text-danger">{{ $message }}</div>@enderror
                @endforeach
            </div>
        @endif
        <div class="col-12 d-grid d-sm-block">
            <button type="submit" class="btn btn-primary">
                {{ $needsVehicle ? 'Add vehicle & continue' : 'Save new vehicle' }}
            </button>
        </div>
    </div>
</form>
