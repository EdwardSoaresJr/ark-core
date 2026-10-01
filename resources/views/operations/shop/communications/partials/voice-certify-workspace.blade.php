@php
    /** @var array<string, mixed> $shop */
@endphp

@include('operations.shop.communications.partials.voice-setup-steps', ['shop' => $shop])

<section class="mt-4 space-y-3">
    <div class="rounded-sm border border-emerald-200 bg-emerald-50/80 px-4 py-3">
        <p class="text-sm font-black text-emerald-950">Software path complete</p>
        <p class="mt-1 text-xs leading-5 text-emerald-900">Plug in the phone and run First Contact certification on the device workspace.</p>
    </div>

    <ul class="divide-y divide-slate-100 rounded-sm border border-slate-200 bg-white">
        @foreach ($shop['devices'] as $device)
            <li>
                <a
                    href="{{ route('operations.shop.devices.show', $device->deviceId) }}"
                    class="flex items-center justify-between gap-3 px-4 py-3 hover:bg-slate-50"
                >
                    <div class="min-w-0">
                        <p class="truncate text-sm font-black text-slate-950">{{ $device->name }}</p>
                        <p class="truncate text-xs text-slate-600">
                            {{ $device->workstationName ?? 'Unassigned' }}
                            · {{ $device->statusLabel }}
                        </p>
                    </div>
                    <span class="shrink-0 text-xs font-bold uppercase tracking-wide text-sky-700">Begin First Contact →</span>
                </a>
            </li>
        @endforeach
    </ul>
</section>
