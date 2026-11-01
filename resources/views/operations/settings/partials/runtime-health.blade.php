@php
    /** @var \App\Ark\Operations\Diagnostics\OperationalClockProjection $operationalClock */
    use App\Ark\Runtime\Broadcast\ReverbDeployment;
@endphp

<div class="card-body" data-runtime-live-clocks>
    <div class="subheader mb-2">Live clocks</div>
    <div class="ark-legacy bg-white text-dark border rounded p-3">
        <x-operations.operational-clock :clock="$operationalClock" variant="panel" />
    </div>
    <p class="small text-secondary mt-2 mb-0">
        UTC and DB should match. Shop time should track Settings → Shop timezone.
        Amber means PHP or MySQL drifted off UTC.
    </p>
</div>

<div class="card-body">
    <div class="row g-4">
        <div class="col-12 col-lg-6" data-runtime-timezone-sources>
            <div class="subheader mb-2">Time zone sources</div>
            <div class="list-group small">
                <div class="list-group-item d-flex align-items-baseline justify-content-between gap-3">
                    <span class="text-secondary">Laravel <code>app.timezone</code></span>
                    <span class="font-monospace fw-bold">{{ config('app.timezone') }}</span>
                </div>
                <div class="list-group-item d-flex align-items-baseline justify-content-between gap-3">
                    <span class="text-secondary">PHP default timezone</span>
                    <span @class([
                        'font-monospace fw-bold',
                        'text-warning' => ! $operationalClock->phpIsUtc,
                    ])>{{ $operationalClock->phpDefaultTimezone }}</span>
                </div>
                <div class="list-group-item d-flex align-items-baseline justify-content-between gap-3">
                    <span class="text-secondary">Shop display timezone</span>
                    <span class="font-monospace fw-bold">{{ $operationalClock->shopTimezone }}</span>
                </div>
                <div class="list-group-item d-flex align-items-baseline justify-content-between gap-3">
                    <span class="text-secondary"><code>app.display_timezone</code></span>
                    <span class="font-monospace fw-bold">{{ config('app.display_timezone') }}</span>
                </div>
            </div>
        </div>

        <div class="col-12 col-lg-6" data-runtime-mysql-session>
            <div class="subheader mb-2">MySQL session</div>
            @if ($operationalClock->dbUtc !== null)
                <div class="list-group small">
                    <div class="list-group-item d-flex align-items-baseline justify-content-between gap-3">
                        <span class="text-secondary"><code>UTC_TIMESTAMP()</code></span>
                        <span class="font-monospace fw-bold">{{ $operationalClock->dbUtc->format('Y-m-d H:i:s') }} UTC</span>
                    </div>
                    <div class="list-group-item d-flex align-items-baseline justify-content-between gap-3">
                        <span class="text-secondary"><code>NOW()</code></span>
                        <span class="font-monospace fw-bold">{{ $operationalClock->dbSessionNow?->format('Y-m-d H:i:s') }}</span>
                    </div>
                    <div class="list-group-item d-flex align-items-baseline justify-content-between gap-3">
                        <span class="text-secondary">NOW matches UTC</span>
                        <span @class([
                            'fw-bold',
                            'text-success' => $operationalClock->dbMatchesUtc,
                            'text-warning' => ! $operationalClock->dbMatchesUtc,
                        ])>{{ $operationalClock->dbMatchesUtc ? 'Yes' : 'No - investigate' }}</span>
                    </div>
                    <div class="list-group-item d-flex align-items-baseline justify-content-between gap-3">
                        <span class="text-secondary">@@session.time_zone</span>
                        <span class="font-monospace fw-bold">{{ $operationalClock->dbSessionTimezone ?? '-' }}</span>
                    </div>
                    <div class="list-group-item d-flex align-items-baseline justify-content-between gap-3">
                        <span class="text-secondary">@@global.time_zone</span>
                        <span class="font-monospace fw-bold">{{ $operationalClock->dbGlobalTimezone ?? '-' }}</span>
                    </div>
                </div>
            @else
                <p class="small text-secondary mb-0">MySQL clock probe unavailable on this connection.</p>
            @endif
        </div>
    </div>
</div>

<div class="card-body" data-runtime-realtime>
    <div class="subheader mb-2">Realtime endpoint</div>
    <div class="row g-2 small">
        <div class="col-12 col-sm-6">
            <div class="list-group">
                <div class="list-group-item d-flex align-items-baseline justify-content-between gap-3">
                    <span class="text-secondary">WebSocket host</span>
                    <span class="font-monospace fw-bold text-break text-end">{{ ReverbDeployment::publicHost() }}</span>
                </div>
                <div class="list-group-item d-flex align-items-baseline justify-content-between gap-3">
                    <span class="text-secondary">Host source</span>
                    <span class="font-monospace fw-bold text-break text-end">{{ ReverbDeployment::publicHostSource() }}</span>
                </div>
            </div>
        </div>
        <div class="col-12 col-sm-6">
            <div class="list-group">
                <div class="list-group-item d-flex align-items-baseline justify-content-between gap-3">
                    <span class="text-secondary">Health JSON</span>
                    <a href="{{ route('health.reverb') }}" target="_blank" rel="noopener" class="font-monospace fw-bold">
                        /up/reverb
                    </a>
                </div>
                <div class="list-group-item d-flex align-items-baseline justify-content-between gap-3">
                    <span class="text-secondary">App URL</span>
                    <span class="font-monospace fw-bold text-break text-end">{{ config('app.url') }}</span>
                </div>
            </div>
        </div>
    </div>
</div>

@isset($telephonyHealth, $settings)
    @include('operations.settings.partials.communications-infrastructure', [
        'telephonyHealth' => $telephonyHealth,
        'settings' => $settings,
        'platformVoiceManaged' => $platformVoiceManaged ?? \App\Ark\Platform\Voice\ManagedVoiceGate::platformVoiceReady(),
    ])
@endisset
