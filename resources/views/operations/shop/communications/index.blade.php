@php
    /** @var array<string, mixed> $shop */
    $toneDot = static fn (string $tone): string => match ($tone) {
        'success' => 'bg-emerald-500',
        'warning' => 'bg-amber-500',
        'danger' => 'bg-rose-500',
        default => 'bg-slate-300',
    };

    $postureLabel = match ($shop['voice_posture']) {
        'setup' => 'Setup in progress',
        'certify' => 'Ready to connect',
        default => $shop['readiness_label'],
    };

    $postureTone = match ($shop['voice_posture']) {
        'setup' => 'muted',
        'certify' => 'success',
        default => $shop['readiness_tone'],
    };
@endphp

<x-operations.app title="Stations &amp; Phones">
    <div class="mx-auto max-w-2xl space-y-4 px-4 py-4">
        <header class="space-y-3 border-b border-slate-200 pb-4">
            <div class="flex items-start justify-between gap-3">
                <div class="min-w-0">
                    <h1 class="text-xl font-black text-slate-950">Stations &amp; Phones</h1>
                    <p class="mt-1 text-xs leading-5 text-slate-600">
                        @if ($shop['voice_posture'] === 'setup')
                            Name the places where work happens — then plug in phones.
                        @elseif ($shop['voice_posture'] === 'certify')
                            Station setup is ready. Connect the device on the bench.
                        @else
                            {{ $shop['operator_question'] }}
                        @endif
                    </p>
                </div>
                <div class="flex shrink-0 items-center gap-2 pt-0.5">
                    <span @class(['h-2.5 w-2.5 rounded-full', $toneDot($postureTone)]) aria-hidden="true"></span>
                    <span @class([
                        'text-sm font-black',
                        'text-emerald-700' => $postureTone === 'success',
                        'text-amber-700' => $postureTone === 'warning',
                        'text-slate-600' => $postureTone === 'muted',
                    ])>{{ $postureLabel }}</span>
                </div>
            </div>

            @if (session('status'))
                <p class="rounded-sm border border-emerald-200 bg-emerald-50 px-3 py-2 text-sm font-semibold text-emerald-800">{{ session('status') }}</p>
            @endif

            @if ($errors->has('device') || $errors->has('mac_address') || $errors->has('name') || $errors->has('model'))
                <div class="rounded-sm border border-rose-200 bg-rose-50 px-3 py-2 text-sm text-rose-800">
                    @foreach (['device', 'mac_address', 'name', 'model', 'workstation_id'] as $field)
                        @error($field)
                            <p class="font-semibold">{{ $message }}</p>
                        @enderror
                    @endforeach
                </div>
            @endif

            @if (filled($shop['business_number_display']))
                <p class="text-xs text-slate-500">
                    Customer line <span class="font-semibold text-slate-800">{{ $shop['business_number_display'] }}</span>
                </p>
            @endif
        </header>

        @if ($shop['voice_posture'] === 'setup')
            @include('operations.shop.communications.partials.voice-setup-workspace', ['shop' => $shop])
        @elseif ($shop['voice_posture'] === 'certify')
            @include('operations.shop.communications.partials.voice-certify-workspace', ['shop' => $shop])
        @else
            @include('operations.shop.communications.partials.voice-operate-workspace', ['shop' => $shop])
        @endif
    </div>
</x-operations.app>
