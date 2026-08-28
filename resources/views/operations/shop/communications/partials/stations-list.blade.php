@php
    /** @var array<string, mixed> $shop */
    $toneDot = static fn (string $tone): string => match ($tone) {
        'success' => 'bg-emerald-500',
        'warning' => 'bg-amber-500',
        'danger' => 'bg-rose-500',
        default => 'bg-slate-300',
    };
    $statusText = static fn (string $tone): string => match ($tone) {
        'success' => 'text-emerald-700',
        'warning' => 'text-amber-700',
        'danger' => 'text-rose-700',
        default => 'text-slate-600',
    };
    $activeStep = $shop['active_setup_step'] ?? null;
    $editStationId = (int) old('edit_workstation_id', 0);
@endphp

<section class="space-y-3">
    <p class="text-[10px] font-bold uppercase tracking-wide text-slate-400">Stations</p>

    @error('workstation')
        <p class="text-xs font-semibold text-rose-700">{{ $message }}</p>
    @enderror

    <ul class="divide-y divide-slate-100 rounded-sm border border-slate-200 bg-white">
        @forelse ($shop['workstations'] as $station)
            <li>
                @if ($station->primaryDeviceId)
                    <a href="{{ route('operations.shop.devices.show', $station->primaryDeviceId) }}" class="flex items-center justify-between gap-3 px-3 py-3 hover:bg-slate-50">
                @else
                    <div class="flex items-center justify-between gap-3 px-3 py-3">
                @endif
                    <div class="min-w-0">
                        <p class="truncate text-sm font-black text-slate-950">{{ $station->name }}</p>
                        <p class="truncate text-xs text-slate-600">
                            @if ($station->currentOperatorLabel)
                                {{ $station->currentOperatorLabel }}
                                @if ($station->primaryDeviceName)
                                    · {{ $station->primaryDeviceName }}
                                @endif
                            @elseif ($station->primaryDeviceName)
                                {{ $station->primaryDeviceName }}
                            @else
                                No device attached
                            @endif
                        </p>
                    </div>
                    <span @class(['shrink-0 text-xs font-bold uppercase tracking-wide', $statusText($station->stationStatusTone)])>
                        {{ $station->stationStatusLabel }}
                    </span>
                @if ($station->primaryDeviceId)
                    </a>
                @else
                    </div>
                @endif
            </li>
        @empty
            <li class="px-3 py-3 text-sm text-slate-600">No stations yet.</li>
        @endforelse
    </ul>

    @if ($activeStep !== 'workstation')
        <details class="rounded-sm border border-slate-200 bg-slate-50/60 px-3 py-2">
            <summary class="cursor-pointer text-xs font-semibold text-slate-700">Add a station</summary>
            <form method="POST" action="{{ route('operations.shop.workstations.store') }}" class="mt-3 space-y-2 pb-2">
                @csrf
                <div class="grid gap-2 sm:grid-cols-2">
                    <input type="text" name="name" value="{{ old('name') }}" required placeholder="Front Counter" class="h-9 rounded-sm border-slate-300 text-sm @error('name') border-rose-400 @enderror">
                    <input type="text" name="location_label" value="{{ old('location_label') }}" placeholder="Location (optional)" class="h-9 rounded-sm border-slate-300 text-sm">
                </div>
                @error('name')
                    @if (! $editStationId)
                        <p class="text-xs font-semibold text-rose-700">{{ $message }}</p>
                    @endif
                @enderror
                <button type="submit" class="text-xs font-semibold text-sky-700 hover:underline">Add station</button>
            </form>
        </details>
    @endif
</section>

@if (($shop['workstation_count'] ?? 0) > 0)
    <details class="rounded-sm border border-slate-200 bg-slate-50/60 p-3">
        <summary class="cursor-pointer text-xs font-semibold text-slate-700">Manage station names</summary>
        <ul class="mt-3 divide-y divide-slate-200 text-sm">
            @foreach ($shop['workstations'] as $station)
                <li class="py-2">
                    <details class="group" @if ($editStationId === $station->workstationId) open @endif>
                        <summary class="cursor-pointer list-none font-semibold text-slate-950 hover:text-sky-700 [&::-webkit-details-marker]:hidden">
                            {{ $station->name }}
                        </summary>
                        <form
                            method="POST"
                            action="{{ route('operations.shop.workstations.update', $station->workstationId) }}"
                            class="mt-2 space-y-2 rounded-sm border border-slate-200 bg-white p-3"
                        >
                            @csrf
                            @method('PATCH')
                            <input type="hidden" name="edit_workstation_id" value="{{ $station->workstationId }}">
                            <label class="block">
                                <span class="text-xs font-semibold text-slate-700">Name</span>
                                <input
                                    type="text"
                                    name="name"
                                    value="{{ old('edit_workstation_id') == $station->workstationId ? old('name', $station->name) : $station->name }}"
                                    required
                                    class="mt-1 h-9 w-full rounded-sm border-slate-300 text-sm"
                                >
                            </label>
                            <label class="block">
                                <span class="text-xs font-semibold text-slate-700">Location <span class="font-normal text-slate-500">(optional)</span></span>
                                <input
                                    type="text"
                                    name="location_label"
                                    value="{{ old('edit_workstation_id') == $station->workstationId ? old('location_label', $station->rawLocationLabel) : ($station->rawLocationLabel ?? '') }}"
                                    class="mt-1 h-9 w-full rounded-sm border-slate-300 text-sm"
                                >
                            </label>
                            <button type="submit" class="inline-flex min-h-9 items-center rounded-sm bg-slate-950 px-4 text-xs font-semibold text-white hover:bg-slate-800">
                                Save
                            </button>
                        </form>
                    </details>
                </li>
            @endforeach
        </ul>
    </details>
@endif
