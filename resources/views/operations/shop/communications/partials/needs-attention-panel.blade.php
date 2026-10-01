@php
    /** @var array<string, mixed> $shop */
    $pending = $shop['pending_devices'] ?? [];
    $attention = $shop['attention'] ?? [];
    $pendingCount = (int) ($shop['pending_device_count'] ?? count($pending));
    $hasAttention = $pendingCount > 0 || $attention !== [];
    $pendingSummary = match (true) {
        $pendingCount === 1 => 'A new phone is ready to use.',
        $pendingCount > 1 => $pendingCount.' new phones are ready to use.',
        default => null,
    };
@endphp

@if ($hasAttention)
    <section class="space-y-2">
        <p class="text-[10px] font-bold uppercase tracking-wide text-slate-400">Needs attention</p>

        @if ($pendingCount > 0)
            @if ($pendingCount === 1)
                <div class="rounded-sm border border-amber-200 bg-amber-50/40 px-3 py-3">
                    @include('operations.shop.communications.partials.pending-devices-panel', [
                        'shop' => $shop,
                        'showIntro' => true,
                    ])
                </div>
            @else
                <details class="rounded-sm border border-amber-200 bg-amber-50/40" @if ($errors->any()) open @endif>
                    <summary class="cursor-pointer px-3 py-2.5 text-sm font-black text-amber-950">
                        {{ $pendingSummary }}
                    </summary>
                    <div class="space-y-3 border-t border-amber-100 px-3 pb-3 pt-3">
                        @include('operations.shop.communications.partials.pending-devices-panel', [
                            'shop' => $shop,
                            'showIntro' => false,
                        ])
                    </div>
                </details>
            @endif
        @endif

        @if ($attention !== [])
            <ul class="space-y-2">
                @foreach ($attention as $item)
                    <li class="flex items-start gap-2 rounded-sm border border-amber-200 bg-amber-50 px-3 py-2 text-sm">
                        <span class="font-black text-amber-700" aria-hidden="true">⚠</span>
                        @if ($item->deviceId !== null)
                            <a href="{{ route('operations.shop.devices.show', $item->deviceId) }}" class="font-semibold text-amber-900 hover:underline">
                                {{ $item->message }}
                            </a>
                        @else
                            <span class="font-semibold text-amber-900">{{ $item->message }}</span>
                        @endif
                    </li>
                @endforeach
            </ul>
        @endif
    </section>
@endif
