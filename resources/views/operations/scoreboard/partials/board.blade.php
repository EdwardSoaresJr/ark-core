@php
    $visibleKpi = function (array $kpi) use ($access): bool {
        return $kpi['financial'] ? $access['financial'] : $access['operational'];
    };
    $linkFor = function (string $focus, ?int $days = null) use ($periodKey, $wall, $access): ?string {
        if ($wall || ! $access['drilldown']) {
            return null;
        }
        if (! \App\Ark\Operations\Scoreboard\ShopOperatingScoreboardAccess::canOpenFocus(auth()->user(), $focus)) {
            return null;
        }
        $query = ['period' => $periodKey, 'focus' => $focus];
        if ($days !== null) {
            $query['days'] = $days;
        }

        return route('operations.owner.scoreboard', $query);
    };
@endphp

<div class="{{ $wall ? 'flex h-full min-h-0 flex-col gap-4 p-4' : 'space-y-4' }}">
    <div class="flex flex-wrap items-end justify-between gap-3">
        <div>
            <p class="text-[11px] font-bold uppercase tracking-[0.14em] text-slate-500">Shop scoreboard</p>
            <p class="{{ $wall ? 'text-2xl' : 'text-lg' }} font-black text-slate-950">{{ $snapshot['period']['label'] }}</p>
        </div>
        <p class="text-xs font-semibold text-slate-500">Updated {{ $snapshot['generated_at'] }}</p>
    </div>

    @if ($access['operational'] || $access['financial'])
        <div class="{{ $wall ? 'scoreboard-kpis grid grid-cols-5 gap-3' : 'grid grid-cols-1 gap-3 sm:grid-cols-2 xl:grid-cols-5' }}">
            @foreach ($snapshot['kpis'] as $kpi)
                @continue(! $visibleKpi($kpi))
                @php
                    $href = $linkFor($kpi['focus']);
                    $tone = match ($kpi['tone']) {
                        'good' => 'border-emerald-700',
                        'warn' => 'border-amber-600',
                        default => 'border-slate-300',
                    };
                @endphp
                <article class="min-w-0 border-t-4 bg-white px-3 py-3 {{ $tone }}">
                    <p class="text-[11px] font-bold uppercase tracking-[0.08em] text-slate-500">{{ $kpi['label'] }}</p>
                    @if ($href)
                        <a href="{{ $href }}" class="mt-1 block {{ $wall ? 'text-4xl' : 'text-3xl' }} font-black leading-none tracking-tight text-slate-950 hover:underline">{{ $kpi['value'] }}</a>
                    @else
                        <p class="mt-1 {{ $wall ? 'text-4xl' : 'text-3xl' }} font-black leading-none tracking-tight text-slate-950">{{ $kpi['value'] }}</p>
                    @endif
                    <p class="mt-2 text-xs font-semibold text-slate-600">{{ $kpi['target_label'] }}</p>
                    <p class="mt-1 text-[11px] leading-4 text-slate-500">{{ $kpi['hint'] }}</p>
                    @if ($kpi['trend'] !== null)
                        <p class="mt-1 text-[11px] font-semibold text-slate-600">{{ $kpi['trend'] }} {{ $kpi['trend_caption'] }}</p>
                    @endif
                </article>
            @endforeach
        </div>
    @endif

    @if ($access['financial'])
        <section class="border border-slate-300 bg-white px-3 py-3">
            <div class="flex flex-wrap items-baseline justify-between gap-2">
                <h2 class="text-sm font-black uppercase tracking-[0.08em] text-slate-700">Money flow</h2>
                <p class="{{ $wall ? 'text-2xl' : 'text-xl' }} font-black text-slate-950">{{ $snapshot['money']['presented_label'] }} presented</p>
            </div>
            <p class="mt-1 text-sm font-semibold text-amber-900">{{ $snapshot['money']['waiting_label'] }} awaiting a decision</p>
            @php $presentedShare = array_sum(array_column($snapshot['money']['segments'], 'share')); @endphp
            @if ($presentedShare > 0)
                <div class="mt-3 flex h-3 overflow-hidden bg-slate-100" aria-hidden="true">
                    @foreach ($snapshot['money']['segments'] as $segment)
                        @if ($segment['share'] > 0)
                            <div
                                class="{{ $segment['key'] === 'recommended' ? 'bg-amber-500' : ($segment['key'] === 'approved' ? 'bg-emerald-700' : ($segment['key'] === 'declined' ? 'bg-slate-400' : 'bg-slate-600')) }}"
                                style="width: {{ $segment['share'] }}%"
                            ></div>
                        @endif
                    @endforeach
                </div>
            @else
                <p class="mt-3 text-xs text-slate-500">No customer-facing work on repair orders opened in this period.</p>
            @endif
            <dl class="mt-3 grid grid-cols-2 gap-2 lg:grid-cols-5">
                @foreach ($snapshot['money']['segments'] as $segment)
                    <div>
                        <dt class="text-[11px] font-bold uppercase tracking-wide text-slate-500">{{ $segment['label'] }}</dt>
                        <dd class="text-sm font-black text-slate-950">{{ $segment['label_money'] }}</dd>
                    </div>
                @endforeach
                <div>
                    <dt class="text-[11px] font-bold uppercase tracking-wide text-slate-500">Draft</dt>
                    <dd class="text-sm font-black text-slate-950">{{ $snapshot['money']['draft_label'] }}</dd>
                </div>
            </dl>
            <details class="mt-3 text-xs leading-5 text-slate-600">
                <summary class="cursor-pointer font-semibold text-slate-800">How dollar close is counted</summary>
                <p class="mt-1 max-w-4xl">{{ $snapshot['explanation'] }}</p>
            </details>
        </section>

        <section class="grid gap-3 lg:grid-cols-2">
            <div class="border border-emerald-800 bg-white px-3 py-3">
                <p class="text-[11px] font-bold uppercase tracking-[0.08em] text-emerald-800">Authorized backlog</p>
                @php $authorizedHref = $linkFor('authorized'); @endphp
                @if ($authorizedHref)
                    <a href="{{ $authorizedHref }}" class="mt-1 block {{ $wall ? 'text-4xl' : 'text-3xl' }} font-black text-slate-950 hover:underline">{{ $snapshot['now']['authorized_label'] }}</a>
                @else
                    <p class="mt-1 {{ $wall ? 'text-4xl' : 'text-3xl' }} font-black text-slate-950">{{ $snapshot['now']['authorized_label'] }}</p>
                @endif
                <p class="mt-1 text-xs text-slate-500">{{ $snapshot['now']['authorized_count'] }} open repair orders with approved work</p>
            </div>
            <div class="border border-slate-400 bg-white px-3 py-3">
                <p class="text-[11px] font-bold uppercase tracking-[0.08em] text-slate-600">Not authorized</p>
                @php $notAuthorizedHref = $linkFor('not_authorized'); @endphp
                @if ($notAuthorizedHref)
                    <a href="{{ $notAuthorizedHref }}" class="mt-1 block {{ $wall ? 'text-4xl' : 'text-3xl' }} font-black text-slate-950 hover:underline">{{ $snapshot['now']['not_authorized_label'] }}</a>
                @else
                    <p class="mt-1 {{ $wall ? 'text-4xl' : 'text-3xl' }} font-black text-slate-950">{{ $snapshot['now']['not_authorized_label'] }}</p>
                @endif
                <p class="mt-1 text-xs text-slate-500">{{ $snapshot['now']['not_authorized_count'] }} open repair orders with draft, recommended, or deferred dollars</p>
            </div>
        </section>
    @endif

    @if ($access['operational'] || $access['queues'])
        <section class="border border-slate-300 bg-white px-3 py-3">
            <div class="flex flex-wrap items-baseline justify-between gap-2">
                <h2 class="text-sm font-black uppercase tracking-[0.08em] text-slate-700">Right now</h2>
                <p class="text-sm font-semibold text-slate-600">{{ $snapshot['now']['open_count'] }} open repair orders</p>
            </div>
            <dl class="mt-3 grid grid-cols-2 gap-3 md:grid-cols-5">
                @foreach ($snapshot['now']['counts'] as $count)
                    <div>
                        <dt class="text-[11px] font-bold uppercase tracking-wide text-slate-500">{{ $count['label'] }}</dt>
                        <dd class="{{ $wall ? 'text-3xl' : 'text-2xl' }} font-black text-slate-950">{{ $count['count'] }}</dd>
                    </div>
                @endforeach
            </dl>
        </section>
    @endif

    @if ($access['queues'])
        <section class="border border-slate-300 bg-white px-3 py-3">
            <h2 class="text-sm font-black uppercase tracking-[0.08em] text-slate-700">Needs a person</h2>
            <div class="mt-3 grid gap-3 md:grid-cols-2 xl:grid-cols-5">
                @foreach ($snapshot['queues'] as $queue)
                    @php $queueHref = $linkFor($queue['focus']); @endphp
                    <article class="min-w-0 border border-slate-200 px-3 py-2">
                        <p class="text-[11px] font-bold uppercase tracking-wide text-slate-500">{{ $queue['label'] }}</p>
                        @if ($queueHref)
                            <a href="{{ $queueHref }}" class="mt-1 block text-3xl font-black text-slate-950 hover:underline">{{ $queue['count'] }}</a>
                        @else
                            <p class="mt-1 text-3xl font-black text-slate-950">{{ $queue['count'] }}</p>
                        @endif
                        @unless ($wall)
                            <p class="mt-1 text-[11px] leading-4 text-slate-500">{{ $queue['hint'] }}</p>
                        @endunless
                    </article>
                @endforeach
            </div>
        </section>
    @endif

    @if ($access['operational'])
        <section class="border border-slate-300 bg-white px-3 py-3">
            <h2 class="text-sm font-black uppercase tracking-[0.08em] text-slate-700">Cycle</h2>
            <p class="mt-2 text-sm text-slate-700">Median <span class="font-black text-slate-950">{{ $snapshot['cycle']['median'] }}</span> · Average <span class="font-semibold">{{ $snapshot['cycle']['average'] }}</span></p>
            <div class="mt-3 grid grid-cols-2 gap-2 md:grid-cols-4">
                @foreach ($snapshot['cycle']['aging'] as $bucket)
                    @php $agingHref = $linkFor('aging', $bucket['days']); @endphp
                    <div class="border border-slate-200 px-3 py-2">
                        <p class="text-[11px] font-bold uppercase tracking-wide text-slate-500">Over {{ $bucket['days'] }} days</p>
                        @if ($agingHref)
                            <a href="{{ $agingHref }}" class="text-2xl font-black text-slate-950 hover:underline">{{ $bucket['count'] }}</a>
                        @else
                            <p class="text-2xl font-black text-slate-950">{{ $bucket['count'] }}</p>
                        @endif
                    </div>
                @endforeach
            </div>
        </section>
    @endif

    @if ($access['financial'] && ! $wall)
        <section class="grid gap-3 xl:grid-cols-3">
            <div class="border border-slate-300 bg-white px-3 py-3">
                <h2 class="text-sm font-black uppercase tracking-[0.08em] text-slate-700">Sales</h2>
                <dl class="mt-2 space-y-1">
                    @foreach ($snapshot['sales'] as $line)
                        <div class="flex items-baseline justify-between gap-3 text-sm">
                            <dt class="text-slate-500">{{ $line['label'] }}</dt>
                            <dd class="font-bold text-slate-950">{{ $line['value'] }}</dd>
                        </div>
                    @endforeach
                </dl>
                @php $lostHref = $linkFor('lost'); @endphp
                @if ($lostHref)
                    <a href="{{ $lostHref }}" class="mt-2 inline-block text-xs font-bold text-slate-700 underline">Lost repair orders</a>
                @endif
            </div>
            <div class="border border-slate-300 bg-white px-3 py-3">
                <h2 class="text-sm font-black uppercase tracking-[0.08em] text-slate-700">Production</h2>
                <dl class="mt-2 space-y-1">
                    @foreach ($snapshot['production']['lines'] as $line)
                        <div class="flex items-baseline justify-between gap-3 text-sm">
                            <dt class="text-slate-500">{{ $line['label'] }}</dt>
                            <dd class="font-bold text-slate-950">{{ $line['value'] }}</dd>
                        </div>
                    @endforeach
                </dl>
                @if ($snapshot['production']['technicians'] !== [])
                    <p class="mt-3 text-[11px] font-bold uppercase tracking-wide text-slate-500">Assigned on posted labor</p>
                    <ul class="mt-1 space-y-1 text-xs text-slate-700">
                        @foreach ($snapshot['production']['technicians'] as $technician)
                            <li class="flex justify-between gap-3">
                                <span>{{ $technician['name'] }}</span>
                                <span class="font-semibold">{{ $technician['hours'] }} h · {{ $technician['labor'] }}</span>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </div>
            <div class="border border-slate-300 bg-white px-3 py-3">
                <div class="flex items-baseline justify-between gap-3">
                    <h2 class="text-sm font-black uppercase tracking-[0.08em] text-slate-700">Parts</h2>
                    @php $partsHref = $linkFor('parts'); @endphp
                    @if ($partsHref)
                        <a href="{{ $partsHref }}" class="text-xs font-bold text-slate-700 underline">Lines</a>
                    @endif
                </div>
                <dl class="mt-2 space-y-1 text-sm">
                    <div class="flex justify-between gap-3"><dt class="text-slate-500">Sales</dt><dd class="font-bold">{{ $snapshot['parts']['sales_label'] }}</dd></div>
                    <div class="flex justify-between gap-3"><dt class="text-slate-500">Cost</dt><dd class="font-bold">{{ $snapshot['parts']['cost_label'] }}</dd></div>
                    <div class="flex justify-between gap-3"><dt class="text-slate-500">Gross profit</dt><dd class="font-bold">{{ $snapshot['parts']['gp_label'] }}</dd></div>
                    <div class="flex justify-between gap-3"><dt class="text-slate-500">Margin</dt><dd class="font-bold">{{ $snapshot['parts']['margin_label'] }}</dd></div>
                </dl>
                <ul class="mt-3 space-y-1 text-xs text-slate-700">
                    @foreach ($snapshot['parts']['buckets'] as $bucket)
                        <li class="flex justify-between gap-3">
                            <span>{{ $bucket['label'] }}</span>
                            <span class="font-semibold">{{ $bucket['count'] }} · {{ $bucket['sales_label'] }}</span>
                        </li>
                    @endforeach
                </ul>
            </div>
        </section>
    @endif
</div>
