@php
    /** @var \App\Ark\Operations\Settings\ShopSettings $settings */
    $catalog = \App\Ark\Platform\PlatformServiceCatalog::forCurrentShop();
    $connection = $catalog->connectionSummary();
    $services = $catalog->services();
    $cloud = \App\Ark\Platform\PlatformConnection::current();
    $cloud->clearExpiredPairing();
    $pairingCode = $cloud->pairingCode();
    $pairingPublicId = $cloud->pairingPublicId();

    $toneClasses = [
        'success' => 'border-emerald-200 bg-emerald-50 text-emerald-900',
        'warning' => 'border-amber-200 bg-amber-50 text-amber-900',
        'danger' => 'border-rose-200 bg-rose-50 text-rose-900',
        'muted' => 'border-slate-200 bg-slate-50 text-slate-600',
    ];

    $serviceTone = fn (string $status): string => match ($status) {
        'active' => 'success',
        'needs_setup' => 'warning',
        'suspended', 'unavailable' => 'danger',
        'coming_soon', 'not_enabled', 'requires_cloud' => 'muted',
        default => 'muted',
    };
@endphp

<section x-show="active === 'ark-cloud'" x-cloak>
    <div class="space-y-4">
        <div class="border-b border-slate-200 pb-2">
            <p class="text-[11px] font-bold uppercase tracking-wide text-slate-400">ARK Platform</p>
            <h2 class="text-base font-black text-slate-950">Managed ARK services</h2>
            <p class="mt-0.5 text-xs leading-5 text-slate-500">
                Connect this Box to ARK Platform for Email, Texting, Voice, and other managed services. Shop work stays in ARK. Service accounts are managed in ARK Platform.
            </p>
        </div>

        <div class="rounded-sm border border-slate-200 bg-white p-4">
            <p class="text-[10px] font-bold uppercase tracking-wide text-slate-400">Connection</p>
            <div class="mt-2 flex flex-wrap items-start justify-between gap-3">
                <div class="min-w-0 space-y-1">
                    <p class="text-sm font-semibold text-slate-950">{{ $connection['shop_name'] }}</p>
                    <p class="text-xs text-slate-600">Box: <span class="font-mono">{{ $connection['box_host'] }}</span></p>
                    @if ($connection['cloud_connected'] && $connection['cloud_shop_public_id'])
                        <p class="text-xs text-slate-500">Shop ID: <span class="font-mono">{{ $connection['cloud_shop_public_id'] }}</span></p>
                    @endif
                </div>
                <span @class([
                    'shrink-0 rounded-sm border px-2 py-1 text-[10px] font-bold uppercase tracking-wide',
                    $toneClasses[$connection['connection_tone']] ?? $toneClasses['muted'],
                ])>
                    {{ $connection['connection_label'] }}
                </span>
            </div>

            @if (! $connection['cloud_connected'] && ! $connection['cloud_pairing'])
                <p class="mt-3 rounded-sm border border-slate-200 bg-slate-50 px-3 py-2 text-xs leading-5 text-slate-600">
                    ARK Core works on its own. Connect when you want managed ARK services.
                </p>
            @endif

            @if ($connection['cloud_pairing'] && $pairingPublicId)
                <div class="mt-3 rounded-sm border border-amber-200 bg-amber-50 px-3 py-2 text-xs text-amber-950">
                    <p class="font-semibold">Connecting…</p>
                    <p class="mt-1">Finish approval in ARK Platform. This Box will complete the connection automatically.</p>
                    @if ($pairingCode)
                        <p class="mt-2">Pairing code (fallback): <span class="font-mono tracking-widest">{{ $pairingCode }}</span></p>
                    @endif
                </div>
                <div class="mt-3 flex flex-wrap gap-2">
                    <a
                        href="{{ route('operations.cloud.connecting') }}"
                        class="inline-flex min-h-9 items-center justify-center rounded-sm bg-slate-950 px-4 text-xs font-semibold text-white hover:bg-slate-800"
                    >
                        Continue connecting
                    </a>
                    <form method="POST" action="{{ route('operations.settings.shop.ark-cloud.claim') }}">
                        @csrf
                        <input type="hidden" name="pairing_public_id" value="{{ $pairingPublicId }}">
                        <button type="submit" class="inline-flex min-h-9 items-center justify-center rounded-sm border border-slate-300 bg-white px-4 text-xs font-semibold text-slate-800 hover:bg-slate-50">
                            Check now
                        </button>
                    </form>
                </div>
            @elseif (! $connection['cloud_connected'])
                <form method="POST" action="{{ route('operations.settings.shop.ark-cloud.connect') }}" class="mt-3 space-y-2">
                    @csrf
                    @if (! config('services.ark_cloud.base_url') && ! config('services.ark_mail.base_url'))
                        <label class="block max-w-md">
                            <span class="text-xs font-bold uppercase tracking-[0.08em] text-slate-400">Cloud URL (dev)</span>
                            <input
                                type="url"
                                name="ark_mail_service_url"
                                value="{{ old('ark_mail_service_url', $settings->cloud_base_url ?: $settings->ark_mail_service_url) }}"
                                class="mt-1 h-9 w-full rounded-sm border-slate-300 font-mono text-sm text-slate-800"
                                placeholder="https://cloud.arksms.com"
                            >
                        </label>
                    @endif
                    <button type="submit" class="inline-flex min-h-9 items-center justify-center rounded-sm bg-slate-950 px-4 text-xs font-semibold text-white hover:bg-slate-800">
                        Connect ARK Platform
                    </button>
                </form>
                <details class="mt-3 text-xs text-slate-500">
                    <summary class="cursor-pointer font-semibold text-slate-700">Use pairing code</summary>
                    <form method="POST" action="{{ route('operations.settings.shop.ark-cloud.connect-manual') }}" class="mt-2">
                        @csrf
                        <button type="submit" class="inline-flex min-h-8 items-center justify-center rounded-sm border border-slate-300 bg-white px-3 text-xs font-semibold text-slate-800 hover:bg-slate-50">
                            Create pairing code
                        </button>
                    </form>
                </details>
            @else
                @php($starter = $catalog->starterSummary())
                @php($mailService = collect($services)->firstWhere('key', 'mail'))
                @if (is_array($mailService) && ($mailService['status'] ?? null) === 'needs_setup')
                    <div class="mt-3 rounded-sm border border-amber-200 bg-amber-50 px-3 py-2 text-xs text-amber-950">
                        <p class="font-semibold">ARK Email needs setup</p>
                        <p class="mt-1">
                            Set a Reply-To address under Customer messaging so Cloud can finish Mail identity.
                            @if (filled($settings->ark_mail_from_email))
                                From address on this Box: <span class="font-mono">{{ $settings->ark_mail_from_email }}</span>.
                            @endif
                        </p>
                    </div>
                @endif
                @if ($starter)
                    <div class="mt-3 rounded-sm border border-emerald-200 bg-emerald-50 px-3 py-2 text-xs text-emerald-950">
                        <p class="font-semibold">ARK Platform Starter · Free</p>
                        <p class="mt-1">
                            {{ (int) $starter['used'] }} of {{ (int) $starter['limit'] }} included Cloud-enabled repair orders used this month.
                        </p>
                        <ul class="mt-2 list-inside list-disc text-emerald-900/90">
                            <li>Estimate delivery</li>
                            <li>Final invoice delivery</li>
                            <li>Essential Delivery</li>
                        </ul>
                    </div>
                @endif
                <div class="mt-3 flex flex-wrap gap-2">
                    @if ($catalog->manageUrl())
                        <a
                            href="{{ $catalog->manageUrl() }}"
                            target="_blank"
                            rel="noopener noreferrer"
                            class="inline-flex min-h-9 items-center justify-center rounded-sm border border-slate-300 bg-white px-4 text-xs font-semibold text-slate-800 hover:bg-slate-50"
                        >
                            Manage in ARK Platform
                        </a>
                    @endif
                    <form method="POST" action="{{ route('operations.settings.shop.ark-cloud.disconnect') }}">
                        @csrf
                        <button type="submit" class="inline-flex min-h-9 items-center justify-center rounded-sm border border-slate-300 bg-white px-3 text-xs font-semibold text-slate-800 hover:bg-slate-50">
                            Disconnect
                        </button>
                    </form>
                </div>
            @endif
        </div>

        <div class="overflow-hidden rounded-sm border border-slate-200">
            <div class="border-b border-slate-200 bg-slate-50 px-3 py-2">
                <p class="text-[10px] font-bold uppercase tracking-wide text-slate-500">Services</p>
            </div>
            <ul class="divide-y divide-slate-100">
                @foreach ($services as $service)
                    <li class="flex flex-wrap items-center justify-between gap-3 px-3 py-3">
                        <div class="min-w-0">
                            <p class="text-sm font-semibold text-slate-950">{{ $service['label'] }}</p>
                            @if ($service['detail'])
                                <p class="mt-0.5 text-xs text-slate-500">{{ $service['detail'] }}</p>
                            @endif
                        </div>
                        <div class="flex shrink-0 items-center gap-3">
                            <span @class([
                                'rounded-sm border px-2 py-1 text-[10px] font-bold uppercase tracking-wide',
                                $toneClasses[$serviceTone($service['status'])] ?? $toneClasses['muted'],
                            ])>
                                {{ $service['status_label'] }}
                            </span>
                            @if ($service['manage_url'] && in_array($service['status'], ['active', 'needs_setup', 'not_enabled', 'suspended'], true))
                                <a
                                    href="{{ $service['manage_url'] }}"
                                    target="_blank"
                                    rel="noopener noreferrer"
                                    class="text-xs font-semibold text-slate-700 underline decoration-slate-300 hover:text-slate-950"
                                >
                                    Manage →
                                </a>
                            @endif
                        </div>
                    </li>
                @endforeach
            </ul>
        </div>
    </div>
</section>
