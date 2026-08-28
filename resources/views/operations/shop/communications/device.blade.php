@php
    /** @var array<string, mixed> $workspace */
    $device = $workspace['device'];
    $stationName = $workspace['workstation_name'] ?? $device->name;
    $toneText = static fn (string $tone): string => match ($tone) {
        'success' => 'text-emerald-700',
        'warning' => 'text-amber-700',
        'danger' => 'text-rose-700',
        default => 'text-slate-600',
    };
@endphp

<x-operations.app :title="$stationName.' · Communications'">
    <div class="mx-auto max-w-3xl space-y-4 px-4 py-4">
        <header class="space-y-1 border-b border-slate-200 pb-3">
            <a href="{{ route('operations.shop.communications') }}" class="text-xs font-semibold text-sky-700">← Communications</a>
            <div class="flex flex-wrap items-start justify-between gap-3">
                <h1 class="text-xl font-black text-slate-950">{{ $stationName }}</h1>
                @if ($workspace['first_contact']['ready'] && auth()->user()?->isMasterAdmin())
                    <a
                        href="#certification"
                        class="inline-flex min-h-8 items-center rounded-sm bg-slate-950 px-3 text-xs font-bold uppercase tracking-wide text-white hover:bg-slate-800"
                    >
                        Bench certification
                    </a>
                @endif
            </div>
            @if ($workspace['workstation_location'] && $workspace['workstation_location'] !== $stationName)
                <p class="text-xs text-slate-600">{{ $workspace['workstation_location'] }}</p>
            @endif
        </header>

        @if (session('status'))
            <p class="rounded-sm border border-emerald-200 bg-emerald-50 px-3 py-2 text-sm font-semibold text-emerald-800">{{ session('status') }}</p>
        @endif

        @include('operations.shop.communications.partials.first-contact-readiness', [
            'firstContact' => $workspace['first_contact'],
        ])

        <div class="divide-y divide-slate-200 rounded-sm border border-slate-200">
            <div class="px-3 py-3">
                <p class="text-[10px] font-bold uppercase tracking-wide text-slate-400">Status</p>
                <p @class(['mt-1 text-sm font-black', $toneText($workspace['status_tone'])])>
                    {{ $workspace['status_label'] === 'Connected' ? 'Ready' : $workspace['status_label'] }}
                </p>
            </div>

            <div class="px-3 py-3">
                <p class="text-[10px] font-bold uppercase tracking-wide text-slate-400">Current operator</p>
                <p class="mt-1 text-sm font-black text-slate-950">
                    {{ $workspace['current_operator_label'] ?? 'Not signed in' }}
                </p>
            </div>

            <div class="px-3 py-3">
                <p class="text-[10px] font-bold uppercase tracking-wide text-slate-400">Attached devices</p>
                <dl class="mt-2 space-y-2 text-sm">
                    <div class="flex flex-wrap justify-between gap-x-4 gap-y-1">
                        <dt class="font-semibold text-slate-950">{{ $device->name }}</dt>
                        <dd @class(['font-bold uppercase tracking-wide text-xs', $toneText($workspace['status_tone'])])>
                            {{ $workspace['status_label'] }}
                        </dd>
                    </div>
                    @if ($workspace['device_model_label'])
                        <p class="text-xs text-slate-600">{{ $workspace['device_model_label'] }}</p>
                    @endif
                </dl>
            </div>

            @if ($workspace['current_activity'] !== 'Idle')
                <div class="px-3 py-3">
                    <p class="text-[10px] font-bold uppercase tracking-wide text-slate-400">Right now</p>
                    <p @class(['mt-1 text-sm font-black', $toneText($workspace['current_activity_tone'])])>{{ $workspace['current_activity'] }}</p>
                </div>
            @endif

            @if ($workspace['last_session_headline'])
                <div class="px-3 py-3">
                    <p class="text-[10px] font-bold uppercase tracking-wide text-slate-400">Last call</p>
                    <p class="mt-1 text-sm font-black text-slate-950">{{ $workspace['last_session_headline'] }}</p>
                    @if ($workspace['last_session_when'])
                        <p class="text-xs font-semibold text-slate-600">{{ $workspace['last_session_when'] }}</p>
                    @endif
                </div>
            @endif
        </div>

        @if ($workspace['first_contact']['ready'])
            @include('operations.shop.communications.partials.poly-provisioning-instructions', [
                'workspace' => $workspace,
            ])

            @if (auth()->user()?->isMasterAdmin())
                @include('operations.shop.communications.partials.first-contact-certification', [
                    'workspace' => $workspace,
                ])
            @endif
        @endif

        @if ($errors->has('device'))
            <p class="rounded-sm border border-rose-200 bg-rose-50 px-3 py-2 text-sm font-semibold text-rose-800">{{ $errors->first('device') }}</p>
        @endif

        @if (auth()->user()?->isMasterAdmin())
            <section class="space-y-3 rounded-sm border border-slate-200 bg-slate-50/60 p-3">
                <p class="text-[10px] font-bold uppercase tracking-wide text-slate-400">Infrastructure</p>
                <p class="text-xs leading-5 text-slate-600">Bench tools — not part of daily operations.</p>

                <dl class="space-y-2 text-sm">
                    <div class="flex flex-wrap justify-between gap-x-4 gap-y-1">
                        <dt class="text-slate-500">MAC</dt>
                        <dd class="font-mono font-semibold text-slate-950">{{ $workspace['mac_address_display'] ?? 'Not set' }}</dd>
                    </div>
                    <div class="flex flex-wrap justify-between gap-x-4 gap-y-1">
                        <dt class="text-slate-500">Projection</dt>
                        <dd class="font-semibold text-slate-950">{{ $workspace['projection_status'] }}</dd>
                    </div>
                </dl>

                @if ($workspace['projection_serialized_config'])
                    <pre class="max-h-48 overflow-auto rounded-sm border border-slate-200 bg-white p-2 text-xs leading-5 text-slate-800">{{ $workspace['projection_serialized_config'] }}</pre>
                @endif

                <div class="flex flex-wrap gap-2">
                    @if ($workspace['can_generate_config'])
                        <form method="POST" action="{{ route('operations.shop.devices.provision.generate', $device) }}">
                            @csrf
                            <button type="submit" class="rounded-sm bg-slate-900 px-3 py-1.5 text-xs font-bold uppercase tracking-wide text-white">
                                Regenerate config
                            </button>
                        </form>
                    @endif
                    @if ($workspace['can_download_config'])
                        <a href="{{ route('operations.shop.devices.provision.download', $device) }}" class="rounded-sm border border-slate-300 bg-white px-3 py-1.5 text-xs font-bold uppercase tracking-wide text-slate-800">
                            Download config
                        </a>
                    @endif
                </div>
            </section>
        @endif

        <section class="rounded-sm border border-slate-200 bg-white p-3">
            <p class="text-xs text-slate-600">Remove this device to re-add after factory reset or bench testing. The station keeps its place in the shop.</p>
            <form
                method="POST"
                action="{{ route('operations.shop.devices.destroy', $device) }}"
                class="mt-3"
                onsubmit="return confirm('Remove {{ $device->name }} from {{ $stationName }}?')"
            >
                @csrf
                @method('DELETE')
                <button type="submit" class="text-sm font-semibold text-rose-700 hover:underline">
                    Remove device
                </button>
            </form>
        </section>
    </div>
</x-operations.app>
