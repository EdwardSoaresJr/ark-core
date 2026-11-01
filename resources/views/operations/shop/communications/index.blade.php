@php
    /** @var array<string, mixed> $shop */
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

    $postureStatus = match ($postureTone) {
        'success' => 'status-green',
        'warning' => 'status-yellow',
        'danger' => 'status-red',
        default => 'status-secondary',
    };

    $subtitle = match ($shop['voice_posture']) {
        'setup' => 'Name the places where work happens - then plug in phones.',
        'certify' => 'Station setup is ready. Connect the device on the bench.',
        default => $shop['operator_question'],
    };
@endphp

<x-operations.app title="Stations &amp; Phones" ui="tabler">
    <x-tabler.page layout="focused" title="Stations & Phones" :subtitle="$subtitle">
        <x-slot:actions>
            <span class="status {{ $postureStatus }}" data-shop-comms-posture="{{ $shop['voice_posture'] }}">
                <span class="status-dot" aria-hidden="true"></span>
                {{ $postureLabel }}
            </span>
        </x-slot:actions>

        <p class="text-secondary d-md-none">{{ $subtitle }}</p>

        @if (session('status'))
            <div class="alert alert-success" role="status">{{ session('status') }}</div>
        @endif

        @if ($errors->has('device') || $errors->has('mac_address') || $errors->has('name') || $errors->has('model'))
            <div class="alert alert-danger" role="alert">
                @foreach (['device', 'mac_address', 'name', 'model', 'workstation_id'] as $field)
                    @error($field)
                        <div>{{ $message }}</div>
                    @enderror
                @endforeach
            </div>
        @endif

        @if (filled($shop['business_number_display']))
            <p class="text-secondary mb-3">
                Customer line <span class="fw-bold text-body">{{ $shop['business_number_display'] }}</span>
            </p>
        @endif

        <div class="d-flex flex-column gap-3">
            @if ($shop['voice_posture'] === 'setup')
                @include('operations.shop.communications.partials.voice-setup-workspace', ['shop' => $shop])
            @elseif ($shop['voice_posture'] === 'certify')
                @include('operations.shop.communications.partials.voice-certify-workspace', ['shop' => $shop])
            @else
                @include('operations.shop.communications.partials.voice-operate-workspace', ['shop' => $shop])
            @endif
        </div>
    </x-tabler.page>
</x-operations.app>
