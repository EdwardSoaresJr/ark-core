@php
    /** @var \App\Ark\Operations\Settings\ShopSettings $settings */
    $catalog = \App\Ark\Platform\PlatformServiceCatalog::forCurrentShop();
    $connection = $catalog->connectionSummary();
    $services = $catalog->services();
    $cloud = \App\Ark\Platform\PlatformConnection::current();
    $cloud->clearExpiredPairing();
    $pairingCode = $cloud->pairingCode();
    $pairingPublicId = $cloud->pairingPublicId();

    $toneBadges = [
        'success' => 'bg-green-lt',
        'warning' => 'bg-yellow-lt',
        'danger' => 'bg-red-lt',
        'muted' => 'bg-secondary-lt',
    ];

    $serviceTone = fn (string $status): string => match ($status) {
        'active' => 'success',
        'needs_setup' => 'warning',
        'suspended', 'unavailable' => 'danger',
        'coming_soon', 'not_enabled', 'requires_cloud' => 'muted',
        default => 'muted',
    };
@endphp

<div class="card-body" data-settings-platform-connection>
    <div style="max-width: 48rem">
        <div class="d-flex flex-wrap align-items-start justify-content-between gap-3">
            <div>
                <div class="subheader">Connection</div>
                <div class="fw-medium">{{ $connection['shop_name'] }}</div>
                <div class="small text-secondary">Box: <span class="font-monospace">{{ $connection['box_host'] }}</span></div>
                @if ($connection['cloud_connected'] && $connection['cloud_shop_public_id'])
                    <div class="small text-secondary">Shop ID: <span class="font-monospace">{{ $connection['cloud_shop_public_id'] }}</span></div>
                @endif
            </div>
            <span class="badge {{ $toneBadges[$connection['connection_tone']] ?? $toneBadges['muted'] }}">
                {{ $connection['connection_label'] }}
            </span>
        </div>

        @if (! $connection['cloud_connected'] && ! $connection['cloud_pairing'])
            <div class="alert alert-secondary small mt-3 mb-0" role="status">
                ARK Core works on its own. Connect when you want managed ARK services.
            </div>
        @endif

        @if ($connection['cloud_pairing'] && $pairingPublicId)
            <div class="alert alert-warning small mt-3" role="status">
                <div class="fw-bold">Connecting…</div>
                <div>Finish approval in ARK Platform. This Box will complete the connection automatically.</div>
                @if ($pairingCode)
                    <div class="mt-2">Pairing code (fallback): <span class="font-monospace">{{ $pairingCode }}</span></div>
                @endif
            </div>
            <div class="btn-list">
                <a
                    href="{{ route('operations.cloud.connecting') }}"
                    class="btn btn-primary"
                >
                    Continue connecting
                </a>
                <form method="POST" action="{{ route('operations.settings.shop.ark-cloud.claim') }}">
                    @csrf
                    <input type="hidden" name="pairing_public_id" value="{{ $pairingPublicId }}">
                    <button type="submit" class="btn">
                        Check now
                    </button>
                </form>
            </div>
        @elseif (! $connection['cloud_connected'])
            <form method="POST" action="{{ route('operations.settings.shop.ark-cloud.connect') }}" class="mt-3">
                @csrf
                @if (! config('services.ark_cloud.base_url') && ! config('services.ark_mail.base_url'))
                    <div class="mb-3" style="max-width: 28rem">
                        <label for="settings-ark-cloud-url" class="form-label">Cloud URL (dev)</label>
                        <input
                            id="settings-ark-cloud-url"
                            type="url"
                            name="ark_mail_service_url"
                            value="{{ old('ark_mail_service_url', $settings->cloud_base_url ?: $settings->ark_mail_service_url) }}"
                            @class(['form-control font-monospace', 'is-invalid' => $errors->has('ark_mail_service_url')])
                            placeholder="https://cloud.arksms.com"
                        >
                        @error('ark_mail_service_url')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                @endif
                <button type="submit" class="btn btn-primary">
                    Connect ARK Platform
                </button>
            </form>
            <details class="mt-3 small">
                <summary class="fw-medium" style="cursor: pointer">Use pairing code</summary>
                <form method="POST" action="{{ route('operations.settings.shop.ark-cloud.connect-manual') }}" class="mt-2">
                    @csrf
                    <button type="submit" class="btn btn-sm">
                        Create pairing code
                    </button>
                </form>
            </details>
        @else
            @php($starter = $catalog->starterSummary())
            @php($mailService = collect($services)->firstWhere('key', 'mail'))
            @if (is_array($mailService) && ($mailService['status'] ?? null) === 'needs_setup')
                <div class="alert alert-warning small mt-3" role="status">
                    <div class="fw-bold">ARK Email needs setup</div>
                    <div>
                        Set a Reply-To address under Customer messaging so Cloud can finish Mail identity.
                        @if (filled($settings->ark_mail_from_email))
                            From address on this Box: <span class="font-monospace">{{ $settings->ark_mail_from_email }}</span>.
                        @endif
                    </div>
                </div>
            @endif
            @if ($starter)
                <div class="alert alert-success small mt-3" role="status">
                    <div class="fw-bold">ARK Platform Starter · Free</div>
                    <div>
                        {{ (int) $starter['used'] }} of {{ (int) $starter['limit'] }} included Cloud-enabled repair orders used this month.
                    </div>
                    <ul class="mt-2 mb-0 ps-3" style="list-style: disc">
                        <li>Estimate delivery</li>
                        <li>Final invoice delivery</li>
                        <li>Essential Delivery</li>
                    </ul>
                </div>
            @endif
            <div class="btn-list mt-3">
                @if ($catalog->manageUrl())
                    <a
                        href="{{ $catalog->manageUrl() }}"
                        target="_blank"
                        rel="noopener noreferrer"
                        class="btn"
                    >
                        Manage in ARK Platform
                    </a>
                @endif
                <form method="POST" action="{{ route('operations.settings.shop.ark-cloud.disconnect') }}">
                    @csrf
                    <button type="submit" class="btn btn-ghost-danger">
                        Disconnect
                    </button>
                </form>
            </div>
        @endif
    </div>
</div>

<div class="card-body" data-settings-platform-services>
    <div class="subheader mb-2">Services</div>
    <div class="list-group" style="max-width: 48rem">
        @foreach ($services as $service)
            <div class="list-group-item" data-platform-service="{{ $service['key'] ?? $loop->index }}">
                <div class="d-flex flex-wrap align-items-center justify-content-between gap-3">
                    <div>
                        <div class="fw-medium">{{ $service['label'] }}</div>
                        @if ($service['detail'])
                            <div class="small text-secondary">{{ $service['detail'] }}</div>
                        @endif
                    </div>
                    <div class="d-flex flex-shrink-0 align-items-center gap-3">
                        <span class="badge {{ $toneBadges[$serviceTone($service['status'])] ?? $toneBadges['muted'] }}">
                            {{ $service['status_label'] }}
                        </span>
                        @if ($service['manage_url'] && in_array($service['status'], ['active', 'needs_setup', 'not_enabled', 'suspended'], true))
                            <a
                                href="{{ $service['manage_url'] }}"
                                target="_blank"
                                rel="noopener noreferrer"
                                class="small"
                            >
                                Manage →
                            </a>
                        @endif
                    </div>
                </div>
            </div>
        @endforeach
    </div>
</div>
