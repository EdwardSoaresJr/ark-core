@php
    /** @var array<string, mixed> $shop */
    $toneDot = static fn (string $tone): string => match ($tone) {
        'success' => 'bg-emerald-500',
        'warning' => 'bg-amber-500',
        'danger' => 'bg-rose-500',
        default => 'bg-slate-300',
    };

    $workstationsNeedingDevice = collect($shop['workstations'] ?? [])
        ->filter(fn ($row): bool => $row->deviceCount === 0);
@endphp

<section class="space-y-2">
    <p class="text-[10px] font-bold uppercase tracking-wide text-slate-400">Ready</p>
    <ul class="divide-y divide-slate-100 rounded-sm border border-slate-200 bg-white">
        @foreach ($shop['health'] as $health)
            <li class="flex items-start gap-2 px-3 py-2.5 text-sm">
                <span @class(['mt-0.5 font-black', 'text-emerald-600' => $health->passed, 'text-slate-400' => ! $health->passed])>{{ $health->passed ? '✓' : '○' }}</span>
                <div>
                    <p class="font-semibold text-slate-950">{{ $health->label }}</p>
                    @if (! $health->passed)
                        <p class="text-xs text-slate-600">{{ $health->detail }}</p>
                    @endif
                </div>
            </li>
        @endforeach
        @foreach ($shop['workstations'] as $station)
            <li class="flex items-start gap-2 px-3 py-2.5 text-sm">
                <span @class(['mt-0.5 font-black', 'text-emerald-600' => $station->isReady, 'text-slate-400' => ! $station->isReady])>{{ $station->isReady ? '✓' : '○' }}</span>
                <div>
                    <p class="font-semibold text-slate-950">{{ $station->name }}</p>
                    @if ($station->isReady)
                        <p class="text-xs text-emerald-700">Ready</p>
                    @else
                        <p class="text-xs text-slate-600">{{ $station->stationStatusLabel }}</p>
                    @endif
                </div>
            </li>
        @endforeach
    </ul>
</section>

@include('operations.shop.communications.partials.needs-attention-panel', ['shop' => $shop])

<section class="space-y-3">
    <p class="text-[10px] font-bold uppercase tracking-wide text-slate-400">Coverage today</p>
    <ul class="divide-y divide-slate-100 rounded-sm border border-slate-200 bg-white">
        @forelse ($shop['coverage'] as $person)
            <li>
                <a href="{{ route('operations.shop.people.show', $person->userId) }}" class="flex items-center gap-2.5 px-3 py-2.5 hover:bg-slate-50">
                    <span @class(['h-2 w-2 shrink-0 rounded-full', $toneDot($person->statusTone)]) aria-hidden="true"></span>
                    <div class="min-w-0">
                        <p class="truncate text-sm font-black text-slate-950">{{ $person->name }}</p>
                        <p class="truncate text-xs text-slate-600">{{ $person->summary }}</p>
                    </div>
                </a>
            </li>
        @empty
            <li class="px-3 py-3 text-sm text-slate-600">No staff on the floor yet.</li>
        @endforelse
    </ul>
</section>

@include('operations.shop.communications.partials.stations-list', ['shop' => $shop])

@if (($shop['pending_devices'] ?? []) === [] && $workstationsNeedingDevice->isEmpty() && auth()->user()?->is_master_admin)
    <details class="rounded-sm border border-slate-200 bg-slate-50/60 px-3 py-2" @if ($errors->any()) open @endif>
        <summary class="cursor-pointer text-xs font-semibold text-slate-700">Manual device entry (support)</summary>
        <div class="mt-3 pb-2">
            @include('operations.shop.communications.partials.add-device-form', ['shop' => $shop, 'workstation' => null])
        </div>
    </details>
@endif
