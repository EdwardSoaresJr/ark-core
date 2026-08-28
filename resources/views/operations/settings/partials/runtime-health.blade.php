@php
    /** @var \App\Ark\Operations\Diagnostics\OperationalClockProjection $operationalClock */
    use App\Ark\Runtime\Broadcast\ReverbDeployment;
@endphp

<section x-show="active === 'runtime-health'" x-cloak class="space-y-4">
    <div class="border border-slate-300 bg-white px-4 py-3">
        <p class="text-[10px] font-bold uppercase tracking-[0.08em] text-slate-400">Troubleshooting</p>
        <h2 class="mt-1 text-base font-black text-slate-950">Runtime health</h2>
        <p class="mt-1 max-w-3xl text-xs leading-5 text-slate-600">
            Infrastructure may observe local time. ARK records facts in UTC and projects them into the shop timezone at display time.
            Use this page after deploys or VPS timezone changes — not for daily operations.
        </p>
    </div>

    <div class="border border-slate-300 bg-white px-4 py-4">
        <p class="text-[10px] font-bold uppercase tracking-[0.08em] text-slate-400">Live clocks</p>
        <div class="mt-3">
            <x-operations.operational-clock :clock="$operationalClock" variant="panel" />
        </div>
        <p class="mt-3 text-[11px] text-slate-500">
            UTC and DB should match. Shop time should track Settings → Shop timezone.
            Amber means PHP or MySQL drifted off UTC.
        </p>
    </div>

    <div class="grid gap-3 lg:grid-cols-2">
        <div class="border border-slate-300 bg-white px-4 py-3">
            <p class="text-[10px] font-bold uppercase tracking-[0.08em] text-slate-400">Fact authority</p>
            <dl class="mt-3 space-y-2 text-xs">
                <div class="flex items-baseline justify-between gap-3 border-b border-slate-100 pb-2">
                    <dt class="font-semibold text-slate-600">Laravel <code class="text-[10px]">app.timezone</code></dt>
                    <dd class="font-mono font-bold text-slate-950">{{ config('app.timezone') }}</dd>
                </div>
                <div class="flex items-baseline justify-between gap-3 border-b border-slate-100 pb-2">
                    <dt class="font-semibold text-slate-600">PHP default timezone</dt>
                    <dd @class([
                        'font-mono font-bold',
                        'text-amber-700' => ! $operationalClock->phpIsUtc,
                        'text-slate-950' => $operationalClock->phpIsUtc,
                    ])>{{ $operationalClock->phpDefaultTimezone }}</dd>
                </div>
                <div class="flex items-baseline justify-between gap-3 border-b border-slate-100 pb-2">
                    <dt class="font-semibold text-slate-600">Shop display timezone</dt>
                    <dd class="font-mono font-bold text-slate-950">{{ $operationalClock->shopTimezone }}</dd>
                </div>
                <div class="flex items-baseline justify-between gap-3">
                    <dt class="font-semibold text-slate-600"><code class="text-[10px]">app.display_timezone</code></dt>
                    <dd class="font-mono font-bold text-slate-950">{{ config('app.display_timezone') }}</dd>
                </div>
            </dl>
        </div>

        <div class="border border-slate-300 bg-white px-4 py-3">
            <p class="text-[10px] font-bold uppercase tracking-[0.08em] text-slate-400">MySQL session</p>
            <dl class="mt-3 space-y-2 text-xs">
                @if ($operationalClock->dbUtc !== null)
                    <div class="flex items-baseline justify-between gap-3 border-b border-slate-100 pb-2">
                        <dt class="font-semibold text-slate-600"><code class="text-[10px]">UTC_TIMESTAMP()</code></dt>
                        <dd class="font-mono font-bold text-slate-950">{{ $operationalClock->dbUtc->format('Y-m-d H:i:s') }} UTC</dd>
                    </div>
                    <div class="flex items-baseline justify-between gap-3 border-b border-slate-100 pb-2">
                        <dt class="font-semibold text-slate-600"><code class="text-[10px]">NOW()</code></dt>
                        <dd class="font-mono font-bold text-slate-950">{{ $operationalClock->dbSessionNow?->format('Y-m-d H:i:s') }}</dd>
                    </div>
                    <div class="flex items-baseline justify-between gap-3 border-b border-slate-100 pb-2">
                        <dt class="font-semibold text-slate-600">NOW matches UTC</dt>
                        <dd @class([
                            'font-bold',
                            'text-emerald-700' => $operationalClock->dbMatchesUtc,
                            'text-amber-700' => ! $operationalClock->dbMatchesUtc,
                        ])>{{ $operationalClock->dbMatchesUtc ? 'Yes' : 'No — investigate' }}</dd>
                    </div>
                    <div class="flex items-baseline justify-between gap-3 border-b border-slate-100 pb-2">
                        <dt class="font-semibold text-slate-600">@@session.time_zone</dt>
                        <dd class="font-mono font-bold text-slate-950">{{ $operationalClock->dbSessionTimezone ?? '—' }}</dd>
                    </div>
                    <div class="flex items-baseline justify-between gap-3">
                        <dt class="font-semibold text-slate-600">@@global.time_zone</dt>
                        <dd class="font-mono font-bold text-slate-950">{{ $operationalClock->dbGlobalTimezone ?? '—' }}</dd>
                    </div>
                @else
                    <p class="text-xs text-slate-600">MySQL clock probe unavailable on this connection.</p>
                @endif
            </dl>
        </div>
    </div>

    <div class="border border-slate-300 bg-white px-4 py-3">
        <p class="text-[10px] font-bold uppercase tracking-[0.08em] text-slate-400">Realtime endpoint</p>
        <dl class="mt-3 grid gap-2 text-xs sm:grid-cols-2">
            <div class="flex items-baseline justify-between gap-3 border-b border-slate-100 pb-2 sm:border-b-0 sm:pb-0">
                <dt class="font-semibold text-slate-600">WebSocket host</dt>
                <dd class="font-mono font-bold text-slate-950">{{ ReverbDeployment::publicHost() }}</dd>
            </div>
            <div class="flex items-baseline justify-between gap-3 border-b border-slate-100 pb-2 sm:border-b-0 sm:pb-0">
                <dt class="font-semibold text-slate-600">Host source</dt>
                <dd class="font-mono font-bold text-slate-950">{{ ReverbDeployment::publicHostSource() }}</dd>
            </div>
            <div class="flex items-baseline justify-between gap-3">
                <dt class="font-semibold text-slate-600">Health JSON</dt>
                <dd>
                    <a href="{{ route('health.reverb') }}" target="_blank" rel="noopener" class="font-mono font-bold text-slate-700 underline decoration-slate-300 hover:text-slate-950">
                        /up/reverb
                    </a>
                </dd>
            </div>
            <div class="flex items-baseline justify-between gap-3">
                <dt class="font-semibold text-slate-600">App URL</dt>
                <dd class="truncate font-mono font-bold text-slate-950">{{ config('app.url') }}</dd>
            </div>
        </dl>
    </div>

    @isset($telephonyHealth, $settings)
        @include('operations.settings.partials.communications-infrastructure', [
            'telephonyHealth' => $telephonyHealth,
            'settings' => $settings,
        ])
    @endisset
</section>
