@php
    /** @var \App\Ark\Operations\Vehicles\Vehicle|null $vehicle */
    $vehicle = $vehicle ?? null;
    $defaultPlateState = $defaultPlateState
        ?? $vehicle?->plate_state
        ?? \App\Ark\Operations\Settings\ShopSettings::current()->state;
    $idPrefix = $idPrefix ?? 'vehicle-'.($vehicle?->id ?? 'new');
    $invalid = fn (string $field): bool => $errors->has($field);
@endphp

{{-- Same fields, names, and arkVehicleDecode bindings as workspace-vehicle-fields, which the repair order workspace keeps. --}}
<div class="row g-3">
    <div class="col-12">
        <label class="form-label" for="{{ $idPrefix }}-vin">VIN</label>
        <div class="input-group">
            <input id="{{ $idPrefix }}-vin" name="vin" value="{{ old('vin', $vehicle?->authoritativeVin()) }}" maxlength="17" autocomplete="off" autocapitalize="characters" @class(['form-control font-monospace', 'is-invalid' => $invalid('vin')]) />
            <button type="button" @click="decode($event)" :disabled="decoding" class="btn">
                <span x-show="!decoding">Decode VIN</span>
                <span x-show="decoding" x-cloak>Decoding…</span>
            </button>
            @error('vin')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>
    </div>
    <div class="col-12">
        <label class="form-label" for="{{ $idPrefix }}-plate">Plate</label>
        <div class="input-group">
            <input id="{{ $idPrefix }}-plate" name="plate" value="{{ old('plate', $vehicle?->plate) }}" autocomplete="off" autocapitalize="characters" @class(['form-control', 'is-invalid' => $invalid('plate')]) />
            <input name="plate_state" value="{{ old('plate_state', $defaultPlateState) }}" maxlength="32" autocomplete="off" autocapitalize="characters" aria-label="Plate state" size="3" @class(['form-control flex-grow-0 w-auto', 'is-invalid' => $invalid('plate_state')]) />
            <button type="button" @click="decodePlate($event)" :disabled="decoding" class="btn">
                <span x-show="!decoding">Decode plate</span>
                <span x-show="decoding" x-cloak>Decoding…</span>
            </button>
            @error('plate')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>
    </div>
    <div class="col-12" x-show="message" x-cloak>
        <div x-text="message" class="small fw-medium" :class="messageIsError ? 'text-danger' : 'text-secondary'"></div>
    </div>
    <div class="col-4">
        <label class="form-label" for="{{ $idPrefix }}-year">Year</label>
        <input id="{{ $idPrefix }}-year" name="year" value="{{ old('year', $vehicle?->year) }}" inputmode="numeric" @class(['form-control', 'is-invalid' => $invalid('year')]) />
        @error('year')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>
    <div class="col-8 col-sm-4">
        <label class="form-label" for="{{ $idPrefix }}-make">Make</label>
        <input id="{{ $idPrefix }}-make" name="make" value="{{ old('make', $vehicle?->make) }}" @class(['form-control', 'is-invalid' => $invalid('make')]) @blur="normalizeField($event)" />
        @error('make')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>
    <div class="col-12 col-sm-4">
        <label class="form-label" for="{{ $idPrefix }}-model">Model</label>
        <input id="{{ $idPrefix }}-model" name="model" value="{{ old('model', $vehicle?->model) }}" @class(['form-control', 'is-invalid' => $invalid('model')]) @blur="normalizeField($event)" />
        @error('model')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>
    <div class="col-sm-6">
        <label class="form-label" for="{{ $idPrefix }}-trim">Trim</label>
        <input id="{{ $idPrefix }}-trim" name="trim" value="{{ old('trim', $vehicle?->trim) }}" @class(['form-control', 'is-invalid' => $invalid('trim')]) @blur="normalizeField($event)" />
    </div>
    <div class="col-sm-6">
        <label class="form-label" for="{{ $idPrefix }}-engine">Engine</label>
        <input id="{{ $idPrefix }}-engine" name="engine" value="{{ old('engine', $vehicle?->engine) }}" class="form-control" />
    </div>
    <div class="col-sm-6">
        <label class="form-label" for="{{ $idPrefix }}-drive">Drive</label>
        <input id="{{ $idPrefix }}-drive" name="drive" value="{{ old('drive', $vehicle?->drive) }}" placeholder="AWD, FWD, 4WD" class="form-control" />
    </div>
    <div class="col-sm-6">
        <label class="form-label" for="{{ $idPrefix }}-transmission">Transmission</label>
        <input id="{{ $idPrefix }}-transmission" name="transmission" value="{{ old('transmission', $vehicle?->transmission) }}" placeholder="Automatic, Manual" class="form-control" />
    </div>
    <div class="col-sm-6">
        <label class="form-label" for="{{ $idPrefix }}-color">Color</label>
        <input id="{{ $idPrefix }}-color" name="color" value="{{ old('color', $vehicle?->color) }}" class="form-control" />
    </div>
    <div class="col-sm-6">
        <label class="form-label" for="{{ $idPrefix }}-nickname">Tag / nickname</label>
        <input id="{{ $idPrefix }}-nickname" name="nickname" value="{{ old('nickname', $vehicle?->nickname) }}" class="form-control" />
    </div>
    <div class="col-12">
        <label class="form-label" for="{{ $idPrefix }}-public-notes">Public notes</label>
        <input id="{{ $idPrefix }}-public-notes" name="public_notes" value="{{ old('public_notes', $vehicle?->public_notes) }}" class="form-control" />
    </div>
    <div class="col-12">
        <label class="form-label" for="{{ $idPrefix }}-private-notes">Private notes</label>
        <input id="{{ $idPrefix }}-private-notes" name="private_notes" value="{{ old('private_notes', $vehicle?->private_notes) }}" class="form-control" />
    </div>
</div>
