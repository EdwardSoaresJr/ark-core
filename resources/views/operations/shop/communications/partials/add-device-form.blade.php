@php
    /** @var array<string, mixed> $shop */
    /** @var \App\Ark\Operations\Communications\CommunicationsShopWorkstationRow|null $workstation */
    $workstation = $workstation ?? null;
    $selectedWorkstationId = (int) old('workstation_id', $workstation?->workstationId ?? 0);
    $defaultName = $workstation ? $workstation->name.' phone' : '';
    $autofocusMac = $autofocusMac ?? false;
@endphp

<form method="POST" action="{{ route('operations.shop.devices.store') }}" class="space-y-3">
    @csrf
    <input type="hidden" name="provider" value="{{ App\Ark\Operations\Communications\CommunicationDeviceProvider::ShopPhone->value }}">
    @if ($workstation)
        <input type="hidden" name="workstation_id" value="{{ $workstation->workstationId }}">
    @endif

    @if ($errors->has('device'))
        <p class="rounded-sm border border-rose-200 bg-rose-50 px-3 py-2 text-sm font-semibold text-rose-800">{{ $errors->first('device') }}</p>
    @endif

    @if ($workstation)
        <div class="rounded-sm border border-slate-200 bg-slate-50 px-3 py-2 text-xs font-semibold text-slate-900">
            {{ $workstation->name }}
        </div>
    @endif

    <label class="block">
        <span class="text-xs font-semibold text-slate-700">Device label</span>
        <input
            type="text"
            name="name"
            value="{{ old('name', $defaultName) }}"
            required
            placeholder="{{ $workstation ? $workstation->name.' phone' : 'Front Counter phone' }}"
            class="mt-1 h-10 w-full rounded-sm border-slate-300 text-sm @error('name') border-rose-400 @enderror"
        >
        @error('name')
            <p class="mt-1 text-xs font-semibold text-rose-700">{{ $message }}</p>
        @enderror
    </label>

    <div class="grid gap-3 sm:grid-cols-2">
        <label class="block">
            <span class="text-xs font-semibold text-slate-700">MAC address</span>
            <input
                type="text"
                name="mac_address"
                value="{{ old('mac_address') }}"
                required
                @if ($autofocusMac && ! old('mac_address')) autofocus @endif
                placeholder="48:25:67:30:75:7F"
                autocomplete="off"
                spellcheck="false"
                inputmode="text"
                class="mt-1 h-10 w-full rounded-sm border-slate-300 font-mono text-sm uppercase @error('mac_address') border-rose-400 @enderror"
            >
            @error('mac_address')
                <p class="mt-1 text-xs font-semibold text-rose-700">{{ $message }}</p>
            @else
                <p class="mt-1 text-[11px] text-slate-500">12 characters from the label on the device.</p>
            @enderror
        </label>
        <label class="block">
            <span class="text-xs font-semibold text-slate-700">Model</span>
            @if (($shop['device_models'] ?? collect())->isNotEmpty())
                <select name="model" required class="mt-1 h-10 w-full rounded-sm border-slate-300 text-sm @error('model') border-rose-400 @enderror">
                    <option value="">Choose model…</option>
                    @foreach ($shop['device_models'] as $deviceModel)
                        <option value="{{ $deviceModel->slug }}" @selected(old('model', 'vvx350') === $deviceModel->slug)>{{ $deviceModel->label }}</option>
                    @endforeach
                </select>
            @else
                <input
                    type="text"
                    name="model"
                    value="{{ old('model', 'VVX350') }}"
                    required
                    placeholder="VVX350"
                    class="mt-1 h-10 w-full rounded-sm border-slate-300 text-sm @error('model') border-rose-400 @enderror"
                >
            @endif
            @error('model')
                <p class="mt-1 text-xs font-semibold text-rose-700">{{ $message }}</p>
            @enderror
        </label>
    </div>

    @if (! $workstation && ($shop['workstations'] ?? []) !== [])
        <label class="block">
            <span class="text-xs font-semibold text-slate-700">Station</span>
            <select name="workstation_id" required class="mt-1 h-10 w-full rounded-sm border-slate-300 text-sm @error('workstation_id') border-rose-400 @enderror">
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
                <p class="mt-1 text-xs font-semibold text-rose-700">{{ $message }}</p>
            @enderror
        </label>
    @endif

    <button type="submit" class="inline-flex min-h-10 w-full items-center justify-center rounded-sm bg-slate-950 px-5 text-sm font-semibold text-white hover:bg-slate-800 sm:w-auto">
        Attach device
    </button>
</form>
