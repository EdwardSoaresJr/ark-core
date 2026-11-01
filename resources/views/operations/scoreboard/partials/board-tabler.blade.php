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
    $segmentColor = fn (string $key): string => match ($key) {
        'recommended' => 'yellow',
        'approved' => 'green',
        'declined' => 'secondary',
        default => 'orange',
    };
@endphp

<div data-scoreboard-board>
    <div class="d-flex flex-wrap align-items-end justify-content-between gap-2 mb-3">
        <h2 class="mb-0">{{ $snapshot['period']['label'] }}</h2>
        <div class="text-secondary small">Updated {{ $snapshot['generated_at'] }}</div>
    </div>

    @if ($access['operational'] || $access['financial'])
        <div class="row row-cards mb-3" role="list" aria-label="Scoreboard KPIs">
            @foreach ($snapshot['kpis'] as $kpi)
                @continue(! $visibleKpi($kpi))
                @php
                    $href = $linkFor($kpi['focus']);
                    $tone = match ($kpi['tone']) {
                        'good' => 'green',
                        'warn' => 'yellow',
                        default => null,
                    };
                @endphp
                <div class="col-12 col-sm-6 col-xl" role="listitem">
                    <article class="card card-sm h-100" data-scoreboard-kpi="{{ $kpi['focus'] }}">
                        @if ($tone)
                            <div class="card-status-top bg-{{ $tone }}"></div>
                        @endif
                        <div class="card-body">
                            <div class="subheader">{{ $kpi['label'] }}</div>
                            @if ($href)
                                <a href="{{ $href }}" class="h1 d-block mb-0 mt-1 text-reset">{{ $kpi['value'] }}</a>
                            @else
                                <div class="h1 mb-0 mt-1">{{ $kpi['value'] }}</div>
                            @endif
                            <div class="small fw-medium mt-2">{{ $kpi['target_label'] }}</div>
                            <div class="small text-secondary mt-1">{{ $kpi['hint'] }}</div>
                            @if ($kpi['trend'] !== null)
                                <div class="small text-secondary fw-medium mt-1">{{ $kpi['trend'] }} {{ $kpi['trend_caption'] }}</div>
                            @endif
                        </div>
                    </article>
                </div>
            @endforeach
        </div>
    @endif

    @if ($access['financial'])
        <section class="card mb-3" data-scoreboard-money>
            <div class="card-header">
                <h3 class="card-title">Money flow</h3>
                <div class="card-actions h3 mb-0">{{ $snapshot['money']['presented_label'] }} presented</div>
            </div>
            <div class="card-body p-2 p-md-3">
                <p class="fw-medium text-warning mb-3">{{ $snapshot['money']['waiting_label'] }} awaiting a decision</p>
                @php $presentedShare = array_sum(array_column($snapshot['money']['segments'], 'share')); @endphp
                @if ($presentedShare > 0)
                    <div class="progress progress-separated progress-lg mb-3" aria-hidden="true">
                        @foreach ($snapshot['money']['segments'] as $segment)
                            @if ($segment['share'] > 0)
                                <div class="progress-bar bg-{{ $segmentColor($segment['key']) }}" style="width: {{ $segment['share'] }}%"></div>
                            @endif
                        @endforeach
                    </div>
                @else
                    <p class="small text-secondary mb-3">No customer-facing work on repair orders opened in this period.</p>
                @endif
                <dl class="datagrid mb-0">
                    @foreach ($snapshot['money']['segments'] as $segment)
                        <div class="datagrid-item">
                            <dt class="datagrid-title">
                                <span class="legend me-1 bg-{{ $segmentColor($segment['key']) }}" aria-hidden="true"></span>{{ $segment['label'] }}
                            </dt>
                            <dd class="datagrid-content fw-bold mb-0">{{ $segment['label_money'] }}</dd>
                        </div>
                    @endforeach
                    <div class="datagrid-item">
                        <dt class="datagrid-title">Draft</dt>
                        <dd class="datagrid-content fw-bold mb-0">{{ $snapshot['money']['draft_label'] }}</dd>
                    </div>
                </dl>
                <details class="small text-secondary mt-3">
                    <summary class="fw-medium text-body">How dollar close is counted</summary>
                    <p class="mt-1 mb-0">{{ $snapshot['explanation'] }}</p>
                </details>
            </div>
        </section>

        <div class="row row-cards mb-3">
            <div class="col-12 col-lg-6">
                <div class="card card-sm h-100" data-scoreboard-authorized>
                    <div class="card-status-start bg-green"></div>
                    <div class="card-body">
                        <div class="subheader text-green">Authorized backlog</div>
                        @php $authorizedHref = $linkFor('authorized'); @endphp
                        @if ($authorizedHref)
                            <a href="{{ $authorizedHref }}" class="h1 d-block mb-0 mt-1 text-reset">{{ $snapshot['now']['authorized_label'] }}</a>
                        @else
                            <div class="h1 mb-0 mt-1">{{ $snapshot['now']['authorized_label'] }}</div>
                        @endif
                        <div class="small text-secondary mt-1">{{ $snapshot['now']['authorized_count'] }} open repair orders with approved work</div>
                    </div>
                </div>
            </div>
            <div class="col-12 col-lg-6">
                <div class="card card-sm h-100" data-scoreboard-not-authorized>
                    <div class="card-status-start bg-secondary"></div>
                    <div class="card-body">
                        <div class="subheader">Not authorized</div>
                        @php $notAuthorizedHref = $linkFor('not_authorized'); @endphp
                        @if ($notAuthorizedHref)
                            <a href="{{ $notAuthorizedHref }}" class="h1 d-block mb-0 mt-1 text-reset">{{ $snapshot['now']['not_authorized_label'] }}</a>
                        @else
                            <div class="h1 mb-0 mt-1">{{ $snapshot['now']['not_authorized_label'] }}</div>
                        @endif
                        <div class="small text-secondary mt-1">{{ $snapshot['now']['not_authorized_count'] }} open repair orders with draft, recommended, or deferred dollars</div>
                    </div>
                </div>
            </div>
        </div>
    @endif

    @if ($access['operational'] || $access['queues'])
        <section class="card mb-3" data-scoreboard-now>
            <div class="card-header">
                <div>
                    <h3 class="card-title">Right now</h3>
                    <div class="card-subtitle">Every repair order still open. The period rates above keep the reporting floor.</div>
                </div>
                <div class="card-actions text-secondary fw-medium">{{ $snapshot['now']['open_count'] }} open repair orders</div>
            </div>
            <div class="card-body p-2 p-md-3">
                <dl class="datagrid mb-0">
                    @foreach ($snapshot['now']['counts'] as $count)
                        <div class="datagrid-item">
                            <dt class="datagrid-title">{{ $count['label'] }}</dt>
                            <dd class="datagrid-content h2 mb-0">{{ $count['count'] }}</dd>
                        </div>
                    @endforeach
                </dl>
            </div>
        </section>
    @endif

    @if ($access['queues'])
        <section class="card mb-3" data-scoreboard-queues>
            <div class="card-header">
                <h3 class="card-title">Needs a person</h3>
            </div>
            <div class="card-body p-2 p-md-3">
                <div class="datagrid">
                    @foreach ($snapshot['queues'] as $queue)
                        @php $queueHref = $linkFor($queue['focus']); @endphp
                        <div class="datagrid-item" data-scoreboard-queue="{{ $queue['focus'] }}">
                            <div class="datagrid-title">{{ $queue['label'] }}</div>
                            @if ($queueHref)
                                <a href="{{ $queueHref }}" class="datagrid-content h1 d-block mb-0 text-reset">{{ $queue['count'] === null ? 'n/a' : $queue['count'] }}</a>
                            @else
                                <div class="datagrid-content h1 mb-0">{{ $queue['count'] === null ? 'n/a' : $queue['count'] }}</div>
                            @endif
                            <div class="small text-secondary mt-1">{{ $queue['hint'] }}</div>
                        </div>
                    @endforeach
                </div>
            </div>
        </section>
    @endif

    @if ($access['operational'])
        <section class="card mb-3" data-scoreboard-cycle>
            <div class="card-header">
                <h3 class="card-title">Cycle</h3>
            </div>
            <div class="card-body p-2 p-md-3">
                <p class="mb-3">Median <span class="fw-bold">{{ $snapshot['cycle']['median'] }}</span> · Average <span class="fw-medium">{{ $snapshot['cycle']['average'] }}</span></p>
                <div class="datagrid">
                    @foreach ($snapshot['cycle']['aging'] as $bucket)
                        @php $agingHref = $linkFor('aging', $bucket['days']); @endphp
                        <div class="datagrid-item">
                            <div class="datagrid-title">Over {{ $bucket['days'] }} days</div>
                            @if ($agingHref)
                                <a href="{{ $agingHref }}" class="datagrid-content h2 d-block mb-0 text-reset">{{ $bucket['count'] }}</a>
                            @else
                                <div class="datagrid-content h2 mb-0">{{ $bucket['count'] }}</div>
                            @endif
                        </div>
                    @endforeach
                </div>
            </div>
        </section>
    @endif

    @if ($access['financial'] && ! $wall)
        <div class="row row-cards">
            <div class="col-12 col-xl-4">
                <section class="card h-100" data-scoreboard-sales>
                    <div class="card-header">
                        <h3 class="card-title">Sales</h3>
                        @php $lostHref = $linkFor('lost'); @endphp
                        @if ($lostHref)
                            <div class="card-actions">
                                <a href="{{ $lostHref }}">Lost repair orders</a>
                            </div>
                        @endif
                    </div>
                    <div class="card-body p-2 p-md-3">
                        <dl class="mb-0">
                            @foreach ($snapshot['sales'] as $line)
                                <div class="d-flex align-items-baseline justify-content-between gap-3 py-1">
                                    <dt class="fw-normal text-secondary">{{ $line['label'] }}</dt>
                                    <dd class="fw-bold mb-0">{{ $line['value'] }}</dd>
                                </div>
                            @endforeach
                        </dl>
                    </div>
                </section>
            </div>
            <div class="col-12 col-xl-4">
                <section class="card h-100" data-scoreboard-production>
                    <div class="card-header">
                        <h3 class="card-title">Production</h3>
                    </div>
                    <div class="card-body p-2 p-md-3">
                        <dl class="mb-0">
                            @foreach ($snapshot['production']['lines'] as $line)
                                <div class="d-flex align-items-baseline justify-content-between gap-3 py-1">
                                    <dt class="fw-normal text-secondary">{{ $line['label'] }}</dt>
                                    <dd class="fw-bold mb-0">{{ $line['value'] }}</dd>
                                </div>
                            @endforeach
                        </dl>
                        @if ($snapshot['production']['technicians'] !== [])
                            <div class="subheader mt-3">Assigned on posted labor</div>
                            <ul class="list-unstyled small mt-1 mb-0">
                                @foreach ($snapshot['production']['technicians'] as $technician)
                                    <li class="d-flex justify-content-between gap-3 py-1">
                                        <span>{{ $technician['name'] }}</span>
                                        <span class="fw-medium">{{ $technician['hours'] }} h · {{ $technician['labor'] }}</span>
                                    </li>
                                @endforeach
                            </ul>
                        @endif
                    </div>
                </section>
            </div>
            <div class="col-12 col-xl-4">
                <section class="card h-100" data-scoreboard-parts>
                    <div class="card-header">
                        <h3 class="card-title">Parts</h3>
                        @php $partsHref = $linkFor('parts'); @endphp
                        @if ($partsHref)
                            <div class="card-actions">
                                <a href="{{ $partsHref }}">Lines</a>
                            </div>
                        @endif
                    </div>
                    <div class="card-body p-2 p-md-3">
                        <dl class="mb-0">
                            <div class="d-flex justify-content-between gap-3 py-1"><dt class="fw-normal text-secondary">Sales</dt><dd class="fw-bold mb-0">{{ $snapshot['parts']['sales_label'] }}</dd></div>
                            <div class="d-flex justify-content-between gap-3 py-1"><dt class="fw-normal text-secondary">Cost</dt><dd class="fw-bold mb-0">{{ $snapshot['parts']['cost_label'] }}</dd></div>
                            <div class="d-flex justify-content-between gap-3 py-1"><dt class="fw-normal text-secondary">Gross profit</dt><dd class="fw-bold mb-0">{{ $snapshot['parts']['gp_label'] }}</dd></div>
                            <div class="d-flex justify-content-between gap-3 py-1"><dt class="fw-normal text-secondary">Margin</dt><dd class="fw-bold mb-0">{{ $snapshot['parts']['margin_label'] }}</dd></div>
                        </dl>
                        <ul class="list-unstyled small border-top mt-2 pt-2 mb-0">
                            @foreach ($snapshot['parts']['buckets'] as $bucket)
                                <li class="d-flex justify-content-between gap-3 py-1">
                                    <span>{{ $bucket['label'] }}</span>
                                    <span class="fw-medium">{{ $bucket['count'] }} · {{ $bucket['sales_label'] }}</span>
                                </li>
                            @endforeach
                        </ul>
                    </div>
                </section>
            </div>
        </div>
    @endif
</div>
