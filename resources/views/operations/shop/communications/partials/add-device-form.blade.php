@php
    /** @var array<string, mixed> $shop */
    /** @var \App\Ark\Operations\Communications\CommunicationsShopWorkstationRow|null $workstation */
    $workstation = $workstation ?? null;
    $selectedWorkstationId = (int) old('workstation_id', $workstation?->workstationId ?? 0);
    $defaultName = $workstation ? $workstation->name.' phone' : '';
    $autofocusMac = $autofocusMac ?? false;
@endphp

<form method="POST" action="{{ route('operations.shop.devices.store') }}">
    @csrf
    <input type="hidden" name="provider" value="{{ App\Ark\Operations\Communications\CommunicationDeviceProvider::ShopPhone->value }}">
    @if ($workstation)
        <input type="hidden" name="workstation_id" value="{{ $workstation->workstationId }}">
    @endif

    @if ($errors->has('device'))
        <div class="alert alert-danger" role="alert">{{ $errors->first('device') }}</div>
    @endif

    @if ($workstation)
        <div class="mb-3 fw-medium">{{ $workstation->name }}</div>
    @endif

    <div class="mb-3">
        <label class="form-label" for="shop-device-name">Device label</label>
        <input
            id="shop-device-name"
            type="text"
            name="name"
            value="{{ old('name', $defaultName) }}"
            required
            placeholder="{{ $workstation ? $workstation->name.' phone' : 'Front Counter phone' }}"
            @class(['form-control', 'is-invalid' => $errors->has('name')])
        >
        @error('name')
            <div class="invalid-feedback">{{ $message }}</div>
        @enderror
    </div>

    <div class="row g-3 mb-3">
        <div class="col-12 col-sm-6">
            <label class="form-label" for="shop-device-mac">MAC address</label>
            <input
                id="shop-device-mac"
                type="text"
                name="mac_address"
                value="{{ old('mac_address') }}"
                required
                @if ($autofocusMac && ! old('mac_address')) autofocus @endif
                placeholder="48:25:67:30:75:7F"
                autocomplete="off"
                spellcheck="false"
                inputmode="text"
                @class(['form-control font-monospace text-uppercase', 'is-invalid' => $errors->has('mac_address')])
            >
            @error('mac_address')
                <div class="invalid-feedback">{{ $message }}</div>
            @else
                <div class="form-hint">12 characters from the label on the device.</div>
            @enderror
        </div>
        <div class="col-12 col-sm-6">
            <label class="form-label" for="shop-device-model">Model</label>
            @if (($shop['device_models'] ?? collect())->isNotEmpty())
                <select id="shop-device-model" name="model" required @class(['form-select', 'is-invalid' => $errors->has('model')])>
                    <option value="">Choose model…</option>
                    @foreach ($shop['device_models'] as $deviceModel)
                        <option value="{{ $deviceModel->slug }}" @selected(old('model', 'vvx350') === $deviceModel->slug)>{{ $deviceModel->label }}</option>
                    @endforeach
                </select>
            @else
                <input
                    id="shop-device-model"
                    type="text"
                    name="model"
                    value="{{ old('model', 'VVX350') }}"
                    required
                    placeholder="VVX350"
                    @class(['form-control', 'is-invalid' => $errors->has('model')])
                >
            @endif
            @error('model')
                <div class="invalid-feedback">{{ $message }}</div>
            @enderror
        </div>
    </div>

    @if (! $workstation && ($shop['workstations'] ?? []) !== [])
        <div class="mb-3">
            <label class="form-label" for="shop-device-station">Station</label>
            <select id="shop-device-station" name="workstation_id" required @class(['form-select', 'is-invalid' => $errors->has('workstation_id')])>
                <option value="">Choose station…</option>
                @foreach ($shop['workstations'] as $row)
                    @if ($row->deviceCount === 0)
                        <option value="{{ $row->workstationId }}" @selected($selectedWorkstationId === $row->workstationId)>
                            {{ $row->name }}
                        </option>
                    @endif
                @endforeach
            </select>
            @error('workstation_id')
                <div class="invalid-feedback">{{ $message }}</div>
            @enderror
        </div>
    @endif

    <div class="d-grid d-sm-flex">
        <button type="submit" class="btn btn-primary">Attach device</button>
    </div>
</form>
