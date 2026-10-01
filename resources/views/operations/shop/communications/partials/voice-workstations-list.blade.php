@php
    /** @var array<string, mixed> $shop */
    $activeStep = $shop['active_setup_step'] ?? null;
    $editWorkstationId = (int) old('edit_workstation_id', 0);
@endphp

<section class="mt-4 rounded-sm border border-slate-200 bg-slate-50/60 p-3">
    <p class="text-[10px] font-bold uppercase tracking-wide text-slate-400">Workstations</p>

    @error('workstation')
        <p class="mt-2 text-xs font-semibold text-rose-700">{{ $message }}</p>
    @enderror

    <ul class="mt-2 divide-y divide-slate-200 text-sm">
        @foreach ($shop['workstations'] as $ws)
            <li class="py-2">
                <div class="flex flex-wrap items-start justify-between gap-2">
                    <div class="min-w-0">
                        <p class="font-semibold text-slate-950">{{ $ws->name }}</p>
                        <p class="text-xs text-slate-600">
                            @if ($ws->rawLocationLabel)
                                {{ $ws->rawLocationLabel }}
                                ·
                            @endif
                            @if ($ws->primaryDeviceName)
                                {{ $ws->primaryDeviceName }}
                                · {{ $ws->primaryDeviceStatusLabel }}
                            @else
                                No phone yet
                            @endif
                            @if ($ws->extensionNumber)
                                · ext {{ $ws->extensionNumber }}
                            @endif
                        </p>
                    </div>
                    <div class="flex shrink-0 items-center gap-3">
                        @if ($ws->primaryDeviceId)
                            <a href="{{ route('operations.shop.devices.show', $ws->primaryDeviceId) }}" class="text-xs font-semibold text-sky-700 hover:underline">Open phone</a>
                        @endif
                        <details class="group" @if ($editWorkstationId === $ws->workstationId) open @endif>
                            <summary class="cursor-pointer list-none text-xs font-semibold text-sky-700 hover:underline [&::-webkit-details-marker]:hidden">
                                Edit
                            </summary>
                            <form
                                method="POST"
                                action="{{ route('operations.shop.workstations.update', $ws->workstationId) }}"
                                class="mt-2 space-y-2 rounded-sm border border-slate-200 bg-white p-3"
                            >
                                @csrf
                                @method('PATCH')
                                <input type="hidden" name="edit_workstation_id" value="{{ $ws->workstationId }}">
                                <label class="block">
                                    <span class="text-xs font-semibold text-slate-700">Name</span>
                                    <input
                                        type="text"
                                        name="name"
                                        value="{{ old('edit_workstation_id') == $ws->workstationId ? old('name', $ws->name) : $ws->name }}"
                                        required
                                        class="mt-1 h-9 w-full rounded-sm border-slate-300 text-sm @error('name') border-rose-400 @enderror"
                                    >
                                    @if (old('edit_workstation_id') == $ws->workstationId)
                                        @error('name')
                                            <p class="mt-1 text-xs font-semibold text-rose-700">{{ $message }}</p>
                                        @enderror
                                    @endif
                                </label>
                                <label class="block">
                                    <span class="text-xs font-semibold text-slate-700">Location <span class="font-normal text-slate-500">(optional)</span></span>
                                    <input
                                        type="text"
                                        name="location_label"
                                        value="{{ old('edit_workstation_id') == $ws->workstationId ? old('location_label', $ws->rawLocationLabel) : ($ws->rawLocationLabel ?? '') }}"
                                        class="mt-1 h-9 w-full rounded-sm border-slate-300 text-sm"
                                    >
                                </label>
                                <button type="submit" class="inline-flex min-h-9 items-center rounded-sm bg-slate-950 px-4 text-xs font-semibold text-white hover:bg-slate-800">
                                    Save changes
                                </button>
                            </form>
                        </details>
                        @if ($ws->deviceCount === 0)
                            <form
                                method="POST"
                                action="{{ route('operations.shop.workstations.destroy', $ws->workstationId) }}"
                                onsubmit="return confirm('Delete {{ $ws->name }}? This frees extension {{ $ws->extensionNumber ?? 'assignment' }}.')"
                            >
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="text-xs font-semibold text-rose-700 hover:underline">
                                    Delete
                                </button>
                            </form>
                        @else
                            <span class="text-xs text-slate-500" title="Remove the phone first">Delete</span>
                        @endif
                    </div>
                </div>
            </li>
        @endforeach
    </ul>

    @if ($activeStep !== 'workstation')
        <form method="POST" action="{{ route('operations.shop.workstations.store') }}" class="mt-3 space-y-2 border-t border-slate-200 pt-3">
            @csrf
            <p class="text-xs font-semibold text-slate-700">Add another workstation</p>
            <div class="grid gap-2 sm:grid-cols-2">
                <input type="text" name="name" value="{{ old('name') }}" required placeholder="Parts Counter" class="h-9 rounded-sm border-slate-300 text-sm @error('name') border-rose-400 @enderror">
                <input type="text" name="location_label" value="{{ old('location_label') }}" placeholder="Location" class="h-9 rounded-sm border-slate-300 text-sm">
            </div>
            @error('name')
                @if (! $editWorkstationId)
                    <p class="text-xs font-semibold text-rose-700">{{ $message }}</p>
                @endif
            @enderror
            <button type="submit" class="text-xs font-semibold text-sky-700 hover:underline">Add workstation</button>
        </form>
    @endif
</section>
