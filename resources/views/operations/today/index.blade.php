@php
    $dashboard = $today->shopDashboard;
    $sections = $today->nonEmptySections();

    $rowsByKey = [];
    foreach ($dashboard?->statusRows ?? [] as $statusRow) {
        $rowsByKey[$statusRow['key']] = $statusRow;
    }

    $rowsFor = function (array $keys) use ($rowsByKey): array {
        $rows = [];
        foreach ($keys as $key) {
            if (isset($rowsByKey[$key])) {
                $rows[] = $rowsByKey[$key];
            }
        }

        return $rows;
    };

    $sumField = function (array $rows, string $field): int {
        $total = 0;
        foreach ($rows as $row) {
            $total += (int) $row[$field];
        }

        return $total;
    };

    $moneyLabel = function (int $cents): string {
        return \Brick\Money\Money::ofMinor($cents, 'USD')->formatTo('en_US');
    };

    $estimateKey = App\Ark\Operations\RepairOrders\RepairOrderStatus::Estimate->value;
    $decisionKey = App\Ark\Operations\RepairOrders\RepairOrderStatus::WaitingApproval->value;
    $approvedWorkKeys = [
        App\Ark\Operations\RepairOrders\RepairOrderStatus::Approved->value,
        App\Ark\Operations\RepairOrders\RepairOrderStatus::WaitingParts->value,
        App\Ark\Operations\RepairOrders\RepairOrderStatus::ReadyForWork->value,
        App\Ark\Operations\RepairOrders\RepairOrderStatus::InProgress->value,
        App\Ark\Operations\RepairOrders\RepairOrderStatus::QualityCheck->value,
    ];
    $pickupKeys = [
        App\Ark\Operations\RepairOrders\RepairOrderStatus::Completed->value,
        App\Ark\Operations\RepairOrders\RepairOrderStatus::Invoiced->value,
        App\Ark\Operations\RepairOrders\RepairOrderStatus::ReadyPickup->value,
    ];

    $estimateRow = $rowsByKey[$estimateKey] ?? null;
    $decisionRow = $rowsByKey[$decisionKey] ?? null;
    $approvedWorkRows = $rowsFor($approvedWorkKeys);
    $pickupRows = $rowsFor($pickupKeys);

    $approvedWorkCount = $sumField($approvedWorkRows, 'car_count');
    $approvedWorkCents = $sumField($approvedWorkRows, 'approved_cents');
    $pickupCount = $sumField($pickupRows, 'car_count');
    $pickupCents = $sumField($pickupRows, 'approved_cents');

    $estimateUrl = $estimateRow['status_url'] ?? route('operations.repair-orders.index', [
        'open' => '1',
        'status' => $estimateKey,
    ]);
    $decisionUrl = $decisionRow['status_url'] ?? route('operations.repair-orders.index', [
        'open' => '1',
        'status' => $decisionKey,
    ]);
    $pickupUrl = count($pickupRows) === 1
        ? $pickupRows[0]['status_url']
        : route('operations.repair-orders.index', ['pickup' => 'all']);

    $commandCards = $dashboard === null ? [] : [
        [
            'tone' => 'estimate',
            'title' => 'Estimates to finish',
            'count' => (int) ($estimateRow['car_count'] ?? 0),
            'money' => $estimateRow['pending_label'] ?? $moneyLabel(0),
            'hint' => 'Recommendations awaiting estimates',
            'cta' => 'Open estimates',
            'url' => $estimateUrl,
        ],
        [
            'tone' => 'decision',
            'title' => 'Awaiting decision',
            'count' => (int) ($decisionRow['car_count'] ?? 0),
            'money' => $decisionRow['pending_label'] ?? $moneyLabel(0),
            'hint' => 'Pending customer recommendations',
            'cta' => 'Review approvals',
            'url' => $decisionUrl,
        ],
        [
            'tone' => 'approved',
            'title' => 'Approved work',
            'count' => $approvedWorkCount,
            'money' => $moneyLabel($approvedWorkCents),
            'hint' => 'Approved invoiceable work in the shop',
            'cta' => 'View approved jobs',
            'url' => $dashboard->approvedUrl,
        ],
        [
            'tone' => 'pickup',
            'title' => 'Ready for pickup',
            'count' => $pickupCount,
            'money' => $moneyLabel($pickupCents),
            'hint' => 'Approved work on pickup-ready ROs. Not an amount due.',
            'cta' => 'View pickup queue',
            'url' => $pickupUrl,
        ],
    ];

    $headlineDate = $dashboard === null
        ? ''
        : \Illuminate\Support\Carbon::parse($dashboard->asOfDate)->format('l, F j');
    $openRepairOrdersLabel = $dashboard === null
        ? ''
        : ($dashboard->carCount === 1
            ? '1 open repair order'
            : $dashboard->carCount.' open repair orders');
@endphp

<x-operations.app title="Today">
    @include('operations.dashboard.tabs')
    @if ($dashboard !== null)
        <section class="ops-today ops-today--dashboard">
            <div class="ops-today__body">
                <div class="ops-shop-dash-toolbar">
                    <div class="ops-shop-dash-toolbar__brand">
                        <h1 class="ops-shop-dash-toolbar__title">Shop Dashboard</h1>
                        <p class="ops-shop-dash-toolbar__meta">
                            <time class="ops-shop-dash-toolbar__date" datetime="{{ $dashboard->asOfDate }}">{{ $headlineDate }}</time>
                            <span class="ops-shop-dash-toolbar__sep" aria-hidden="true">·</span>
                            <a href="{{ $dashboard->openQueueUrl }}" class="ops-shop-dash-toolbar__queue">{{ $openRepairOrdersLabel }}</a>
                        </p>
                    </div>
                    <div class="ops-shop-dash-toolbar__aside">
                        <span class="ops-shop-dash-toolbar__live">Open queue</span>
                        <nav class="ops-shop-dash-toolbar__tools" aria-label="Dashboard shortcuts">
                            <a href="{{ $dashboard->jobBoardUrl }}" class="ops-page-link">Job Board</a>
                            @can(App\Ark\Runtime\Authorization\ArkCapability::OperationsAccess->value)
                                <a href="{{ \App\Ark\Operations\Communications\CommunicationsNeedsYou::url() }}" class="ops-page-link">Comms</a>
                            @endcan
                            @if (\App\Ark\Operations\Business\BusinessWorkspaceAccess::allows(auth()->user()))
                                <a href="{{ route('operations.business') }}" class="ops-page-link">Business</a>
                            @endif
                        </nav>
                    </div>
                </div>

                <section class="ops-shop-dash-focus" aria-label="What needs you">
                    <div class="ops-shop-dash-next">
                        @foreach ($commandCards as $card)
                            <a
                                href="{{ $card['url'] }}"
                                class="ops-shop-dash-next__item"
                                data-tone="{{ $card['tone'] }}"
                                aria-label="{{ $card['title'] }}: {{ $card['count'] }} {{ $card['count'] === 1 ? 'repair order' : 'repair orders' }}, {{ $card['money'] }}. {{ rtrim($card['hint'], '.') }}. {{ $card['cta'] }}"
                            >
                                <span class="ops-shop-dash-next__top">
                                    <span class="ops-shop-dash-next__kicker">{{ $card['title'] }}</span>
                                    <span class="ops-shop-dash-next__count">{{ $card['count'] === 1 ? '1 RO' : $card['count'].' ROs' }}</span>
                                </span>
                                <span class="ops-shop-dash-next__money">{{ $card['money'] }}</span>
                                <span class="ops-shop-dash-next__hint">{{ $card['hint'] }}</span>
                                <span class="ops-shop-dash-next__cta">
                                    {{ $card['cta'] }}
                                    <span class="ops-shop-dash-next__go" aria-hidden="true">→</span>
                                </span>
                            </a>
                        @endforeach
                    </div>
                </section>

                <section class="ops-shop-dash-flow" aria-label="Repair order workflow">
                    <div class="ops-shop-dash-flow__head">
                        <h2 class="ops-shop-dash-flow__title">Repair order workflow</h2>
                        <p class="ops-shop-dash-flow__meta">{{ $openRepairOrdersLabel }} by current status</p>
                    </div>
                    @if ($dashboard->statusRows === [])
                        <p class="ops-shop-dash-flow__empty">No open repair orders.</p>
                    @else
                        <div class="ops-shop-dash-flow__bar" aria-hidden="true">
                            @foreach ($dashboard->statusRows as $row)
                                <a
                                    href="{{ $row['status_url'] }}"
                                    class="ops-shop-dash-flow__seg"
                                    data-flow="{{ $row['key'] }}"
                                    style="flex-grow: {{ max(1, (int) $row['car_count']) }}"
                                    tabindex="-1"
                                    title="{{ $row['label'] }}: {{ $row['car_count'] }}"
                                ></a>
                            @endforeach
                        </div>
                        <div class="ops-shop-dash-flow__legend">
                            @foreach ($dashboard->statusRows as $row)
                                <a
                                    href="{{ $row['status_url'] }}"
                                    class="ops-shop-dash-flow__item"
                                    aria-label="{{ $row['label'] }}: {{ $row['car_count'] }} {{ $row['car_count'] === 1 ? 'repair order' : 'repair orders' }}"
                                >
                                    <span class="ops-shop-dash-flow__dot" data-flow="{{ $row['key'] }}" aria-hidden="true"></span>
                                    <span class="ops-shop-dash-flow__label">{{ $row['label'] }}</span>
                                    <span class="ops-shop-dash-flow__count">{{ $row['car_count'] }}</span>
                                </a>
                            @endforeach
                        </div>
                    @endif
                </section>

                <section class="ops-shop-dash-finance" aria-label="Financial overview">
                    <div class="ops-shop-dash-finance__head">
                        <h2 class="ops-shop-dash-finance__title">Financial overview</h2>
                    </div>
                    <div class="ops-shop-dash-kpis" role="list" aria-label="Shop KPIs">
                        @foreach ($dashboard->kpis as $kpi)
                            @continue($kpi['label'] === 'Car Count' || $kpi['label'] === 'Declined Sales')
                            @if (filled($kpi['url'] ?? null))
                                <a
                                    href="{{ $kpi['url'] }}"
                                    class="ops-shop-dash-kpi ops-shop-dash-kpi--link"
                                    role="listitem"
                                    aria-label="{{ $kpi['label'] }} {{ $kpi['value'] }}. {{ $kpi['hint'] }}"
                                >
                                    <span class="ops-shop-dash-kpi__label">{{ $kpi['label'] }}</span>
                                    <span class="ops-shop-dash-kpi__value">{{ $kpi['value'] }}</span>
                                    @if (filled($kpi['hint'] ?? null))
                                        <span class="ops-shop-dash-kpi__hint">{{ $kpi['hint'] }}</span>
                                    @endif
                                </a>
                            @else
                                <div class="ops-shop-dash-kpi" role="listitem">
                                    <span class="ops-shop-dash-kpi__label">{{ $kpi['label'] }}</span>
                                    <span class="ops-shop-dash-kpi__value">{{ $kpi['value'] }}</span>
                                    @if (filled($kpi['hint'] ?? null))
                                        <span class="ops-shop-dash-kpi__hint">{{ $kpi['hint'] }}</span>
                                    @endif
                                </div>
                            @endif
                        @endforeach
                    </div>
                    <p class="ops-shop-dash-finance__note">
                        Approved sales includes all approved dollars on open repair orders. The cards highlight approved work still in the shop and work on pickup-ready repair orders.
                    </p>

                    <div class="ops-shop-dash-table-wrap">
                    <h2 class="ops-shop-dash-table-wrap__title">Sales by status</h2>
                    <p class="ops-shop-dash-table-wrap__note">{{ $dashboard->footnote }}</p>
                    <table class="ops-shop-dash-table">
                        <thead>
                            <tr>
                                <th scope="col">Status</th>
                                <th scope="col" class="ops-shop-dash-table__num">Pending ($)</th>
                                <th scope="col" class="ops-shop-dash-table__num">Declined ($)</th>
                                <th scope="col" class="ops-shop-dash-table__num">Approved ($)</th>
                                <th scope="col" class="ops-shop-dash-table__num" title="Approved sales divided by cars in this status">Approved / RO</th>
                                <th scope="col" class="ops-shop-dash-table__num">Car Count</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr class="ops-shop-dash-table__totals">
                                <th scope="row">Totals</th>
                                <td @class(['ops-shop-dash-table__num', 'ops-shop-dash-table__num--zero' => $dashboard->pendingCents === 0])>
                                    <a href="{{ $dashboard->pendingUrl }}" class="ops-shop-dash-table__link">{{ $dashboard->pendingLabel }}</a>
                                </td>
                                <td @class(['ops-shop-dash-table__num', 'ops-shop-dash-table__num--zero' => $dashboard->declinedCents === 0])>
                                    <a href="{{ $dashboard->declinedUrl }}" class="ops-shop-dash-table__link">{{ $dashboard->declinedLabel }}</a>
                                </td>
                                <td @class(['ops-shop-dash-table__num', 'ops-shop-dash-table__num--zero' => $dashboard->approvedCents === 0])>
                                    <a href="{{ $dashboard->approvedUrl }}" class="ops-shop-dash-table__link">{{ $dashboard->approvedLabel }}</a>
                                </td>
                                <td @class(['ops-shop-dash-table__num', 'ops-shop-dash-table__num--zero' => $dashboard->aroCents === 0])>
                                    <a href="{{ $dashboard->approvedUrl }}" class="ops-shop-dash-table__link">{{ $dashboard->aroLabel }}</a>
                                </td>
                                <td class="ops-shop-dash-table__num">
                                    <a href="{{ $dashboard->openQueueUrl }}" class="ops-shop-dash-table__link">{{ $dashboard->carCount }}</a>
                                </td>
                            </tr>
                            @forelse ($dashboard->statusRows as $row)
                                <tr @class(['ops-shop-dash-table__row', 'ops-shop-dash-table__row--peak' => $row['peak']])>
                                    <th scope="row">
                                        <a href="{{ $row['status_url'] }}" class="ops-shop-dash-table__link">{{ $row['label'] }}</a>
                                    </th>
                                    <td @class(['ops-shop-dash-table__num', 'ops-shop-dash-table__num--zero' => $row['pending_cents'] === 0])>
                                        <a href="{{ $row['pending_url'] }}" class="ops-shop-dash-table__link">{{ $row['pending_label'] }}</a>
                                    </td>
                                    <td @class(['ops-shop-dash-table__num', 'ops-shop-dash-table__num--zero' => $row['declined_cents'] === 0])>
                                        <a href="{{ $row['declined_url'] }}" class="ops-shop-dash-table__link">{{ $row['declined_label'] }}</a>
                                    </td>
                                    <td @class(['ops-shop-dash-table__num', 'ops-shop-dash-table__num--zero' => $row['approved_cents'] === 0])>
                                        <a href="{{ $row['approved_url'] }}" class="ops-shop-dash-table__link">{{ $row['approved_label'] }}</a>
                                    </td>
                                    <td @class(['ops-shop-dash-table__num', 'ops-shop-dash-table__num--zero' => $row['aro_cents'] === 0])>
                                        <a href="{{ $row['approved_url'] }}" class="ops-shop-dash-table__link">{{ $row['aro_label'] }}</a>
                                    </td>
                                    <td class="ops-shop-dash-table__num">
                                        <a href="{{ $row['status_url'] }}" class="ops-shop-dash-table__link">{{ $row['car_count'] }}</a>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="6" class="ops-shop-dash-table__empty">No open repair orders.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                    </div>
                </section>
            </div>
        </section>
    @else
        {{-- Technician assigned-work lanes --}}
        @include('operations.today.partials.technician-lanes', ['today' => $today, 'sections' => $sections])
    @endif
</x-operations.app>
