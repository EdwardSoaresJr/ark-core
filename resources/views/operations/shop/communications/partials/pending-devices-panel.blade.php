@php
    /** @var array<string, mixed> $shop */
    $pending = $shop['pending_devices'] ?? [];
    $stations = $shop['workstations'] ?? [];
    $showIntro = $showIntro ?? true;
    $pendingCount = count($pending);
    $compact = $pendingCount === 1;
    $stationsNeedingDevice = collect($stations)->filter(fn ($row): bool => $row->deviceCount === 0);
    $showWaiting = $stationsNeedingDevice->isNotEmpty() && $pending === [];
@endphp

@if ($pending !== [])
    @foreach ($pending as $device)
        <div @class(['rounded-sm border border-amber-200 bg-white p-3 shadow-sm', 'border-0 p-0 shadow-none' => ! $showIntro && $compact])>
            @if ($showIntro)
                <p class="text-sm font-semibold text-slate-900">
                    {{ $compact ? 'A new phone is ready to use.' : 'A new phone is ready.' }}
                </p>
            @endif

            @if (! $compact)
                <p @class(['text-sm font-black text-slate-950', 'mt-2' => $showIntro])>{{ $device->modelLabel }}</p>
                <p class="mt-1 text-xs text-slate-600">{{ $device->foundAgoLabel }}</p>
            @elseif ($showIntro)
                <p class="mt-1 text-sm font-black text-slate-950">{{ $device->modelLabel }}</p>
            @endif

            <div @class(['mt-3 space-y-2', 'border-t border-slate-100 pt-3' => $showIntro || ! $compact])>
                <p class="text-xs font-semibold text-slate-800">Where should it be used?</p>

                @forelse ($stations as $station)
                    <form
                        method="POST"
                        action="{{ route('operations.shop.devices.assign-station', $device->deviceId) }}"
                        class="m-0"
                    >
                        @csrf
                        <input type="hidden" name="workstation_id" value="{{ $station->workstationId }}">
                        <button
                            type="submit"
                            class="inline-flex min-h-10 w-full items-center justify-center rounded-sm bg-slate-950 px-5 text-sm font-semibold text-white hover:bg-slate-800"
                        >
                            {{ $station->name }}
                        </button>
                    </form>
                @empty
                    <p class="text-xs text-slate-600">Name a station first — then choose where this phone belongs.</p>
                @endforelse

                <form
                    method="POST"
                    action="{{ route('operations.shop.devices.assign-station', $device->deviceId) }}"
                    class="space-y-2 border-t border-slate-100 pt-3"
                >
                    @csrf
                    <label class="block">
                        <span class="sr-only">New station name</span>
                        <input
                            type="text"
                            name="new_station_name"
                            value="{{ old('new_station_name') }}"
                            placeholder="New station name"
                            class="h-9 w-full rounded-sm border-slate-300 text-sm"
                        >
                    </label>
                    <button
                        type="submit"
                        class="inline-flex min-h-10 w-full items-center justify-center rounded-sm border border-slate-300 bg-white px-5 text-sm font-semibold text-slate-900 hover:bg-slate-50"
                    >
                        Create station
                    </button>
                </form>
            </div>
        </div>
    @endforeach
@elseif ($showWaiting)
    <p class="rounded-sm border border-slate-200 bg-slate-50 px-3 py-3 text-sm text-slate-600">
        Waiting for a phone to appear… ARK will list it here as soon as it checks in.
    </p>
    <p class="text-[11px] text-slate-500">Plug in the phone — ARK keeps checking in until someone chooses where it belongs.</p>
@endif
