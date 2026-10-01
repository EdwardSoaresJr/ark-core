@php
    /** @var array<string, mixed> $shop */
    $activeStep = $shop['active_setup_step'];
@endphp

@include('operations.shop.communications.partials.voice-setup-steps', ['shop' => $shop])

@if ($activeStep === 'workstation')
    <section class="mt-4 rounded-sm border border-slate-200 bg-white p-4 shadow-sm">
        <h2 class="text-sm font-black text-slate-950">Step 1 · Name a station</h2>
        <p class="mt-1 text-xs leading-5 text-slate-600">Where does work happen? Front Counter, Service Desk, Bay 1.</p>

        <form method="POST" action="{{ route('operations.shop.workstations.store') }}" class="mt-4 space-y-3">
            @csrf
            <label class="block">
                <span class="text-xs font-semibold text-slate-700">Station name</span>
                <input
                    type="text"
                    name="name"
                    value="{{ old('name') }}"
                    required
                    autofocus
                    placeholder="Front Counter"
                    class="mt-1 h-10 w-full rounded-sm border-slate-300 text-sm @error('name') border-rose-400 @enderror"
                >
                @error('name')
                    <p class="mt-1 text-xs font-semibold text-rose-700">{{ $message }}</p>
                @enderror
            </label>
            <label class="block">
                <span class="text-xs font-semibold text-slate-700">Location <span class="font-normal text-slate-500">(optional)</span></span>
                <input
                    type="text"
                    name="location_label"
                    value="{{ old('location_label') }}"
                    placeholder="Front desk"
                    class="mt-1 h-10 w-full rounded-sm border-slate-300 text-sm"
                >
            </label>
            <button type="submit" class="inline-flex min-h-10 w-full items-center justify-center rounded-sm bg-slate-950 px-5 text-sm font-semibold text-white hover:bg-slate-800 sm:w-auto">
                Add station
            </button>
        </form>
    </section>
@elseif ($activeStep === 'phone')
    <div class="mt-4">
        @include('operations.shop.communications.partials.connect-device-panel', ['shop' => $shop])
    </div>
@endif

@if ($shop['workstation_count'] > 0)
    @include('operations.shop.communications.partials.stations-list', ['shop' => $shop])
@endif
