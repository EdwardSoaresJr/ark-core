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
    // Tabler's dark matches the dark theme's card, so a status in the closed tone would vanish from the bar and legend.
    $statusColor = function (string $key): string {
        $color = \App\Ark\Operations\RepairOrders\Status\RepairOrderStatusColor::tablerColorForChipTone(
            \App\Ark\Operations\RepairOrders\RepairOrderStatus::tryFrom($key)?->indexTone() ?? 'move',
        );

        return $color === 'dark' ? 'secondary' : $color;
    };
    $cardColors = [
        'estimate' => $statusColor($estimateKey),
        'decision' => $statusColor($decisionKey),
        'approved' => $statusColor(App\Ark\Operations\RepairOrders\RepairOrderStatus::Approved->value),
        'pickup' => $statusColor(App\Ark\Operations\RepairOrders\RepairOrderStatus::ReadyPickup->value),
    ];
    $flowTotal = max(1, array_sum(array_map(fn (array $row): int => max(1, (int) $row['car_count']), $dashboard?->statusRows ?? [])));
    $openRepairOrdersLabel = $dashboard === null
        ? ''
        : ($dashboard->carCount === 1
            ? '1 open repair order'
            : $dashboard->carCount.' open repair orders');
@endphp

<x-operations.app title="Today" ui="tabler">
    @if ($dashboard !== null)
        <x-tabler.page layout="fluid" title="Shop Dashboard" :pretitle="$headlineDate" actions-label="Dashboard shortcuts">
            <x-slot:actions>
                <x-tabler.page-action :href="$dashboard->openQueueUrl" icon="file-text" :label="$openRepairOrdersLabel" />
                <x-tabler.page-action :href="$dashboard->jobBoardUrl" icon="layout-grid" label="Job Board" />
                @can(App\Ark\Runtime\Authorization\ArkCapability::OperationsAccess->value)
                    <x-tabler.page-action :href="\App\Ark\Operations\Communications\CommunicationsNeedsYou::url()" icon="message" label="Comms" />
                @endcan
                @if (\App\Ark\Operations\Business\BusinessWorkspaceAccess::allows(auth()->user()))
                    <x-tabler.page-action :href="route('operations.business')" icon="gauge" label="Business" />
                @endif
            </x-slot:actions>

            @include('operations.dashboard.tabs', ['ui' => 'tabler'])

            <section class="row row-cards mb-3" aria-label="What needs you">
                @foreach ($commandCards as $card)
                    <div class="col-12 col-sm-6 col-xl-3">
                        <a
                            href="{{ $card['url'] }}"
                            class="card card-link card-link-pop h-100 text-reset"
                            data-dashboard-card="{{ $card['tone'] }}"
                            aria-label="{{ $card['title'] }}: {{ $card['count'] }} {{ $card['count'] === 1 ? 'repair order' : 'repair orders' }}, {{ $card['money'] }}. {{ rtrim($card['hint'], '.') }}. {{ $card['cta'] }}"
                        >
                            <div class="card-status-top bg-{{ $cardColors[$card['tone']] }}"></div>
                            <div class="card-body">
                                <div class="d-flex align-items-center">
                                    <div class="subheader">{{ $card['title'] }}</div>
                                    <span class="badge bg-{{ $cardColors[$card['tone']] }}-lt ms-auto">{{ $card['count'] === 1 ? '1 RO' : $card['count'].' ROs' }}</span>
                                </div>
                                <div class="h1 mb-1 mt-2">{{ $card['money'] }}</div>
                                <div class="text-secondary">{{ $card['hint'] }}</div>
                                <div class="mt-2 text-primary fw-medium">{{ $card['cta'] }} &rarr;</div>
                            </div>
                        </a>
                    </div>
                @endforeach
            </section>

            <section class="card mb-3" aria-label="Repair order workflow">
                <div class="card-header">
                    <div>
                        <h2 class="card-title">Repair order workflow</h2>
                        <div class="card-subtitle">{{ $openRepairOrdersLabel }} by current status</div>
                    </div>
                </div>
                <div class="card-body">
                    @if ($dashboard->statusRows === [])
                        <p class="text-secondary mb-0">No open repair orders.</p>
                    @else
                        <div class="progress progress-separated progress-lg mb-3" aria-hidden="true">
                            @foreach ($dashboard->statusRows as $row)
                                <a
                                    href="{{ $row['status_url'] }}"
                                    class="progress-bar bg-{{ $statusColor($row['key']) }}"
                                    style="width: {{ round(max(1, (int) $row['car_count']) / $flowTotal * 100, 2) }}%"
                                    tabindex="-1"
                                    title="{{ $row['label'] }}: {{ $row['car_count'] }}"
                                ></a>
                            @endforeach
                        </div>
                        <div class="d-flex flex-wrap gap-3">
                            @foreach ($dashboard->statusRows as $row)
                                <a
                                    href="{{ $row['status_url'] }}"
                                    class="d-inline-flex align-items-center text-reset"
                                    data-flow="{{ $row['key'] }}"
                                    aria-label="{{ $row['label'] }}: {{ $row['car_count'] }} {{ $row['car_count'] === 1 ? 'repair order' : 'repair orders' }}"
                                >
                                    <span class="legend me-2 bg-{{ $statusColor($row['key']) }}" aria-hidden="true"></span>
                                    {{ $row['label'] }}
                                    <span class="ms-2 fw-bold">{{ $row['car_count'] }}</span>
                                </a>
                            @endforeach
                        </div>
                    @endif
                </div>
            </section>

            <section class="mb-3" aria-label="Financial overview">
                <h2 class="mb-2">Financial overview</h2>
                <div class="row row-cards" role="list" aria-label="Shop KPIs">
                    @foreach ($dashboard->kpis as $kpi)
                        @continue($kpi['label'] === 'Car Count' || $kpi['label'] === 'Declined Sales')
                        <div class="col-6 col-md-4 col-xl" role="listitem">
                            @if (filled($kpi['url'] ?? null))
                                <a href="{{ $kpi['url'] }}" class="card card-sm card-link h-100 text-reset" aria-label="{{ $kpi['label'] }} {{ $kpi['value'] }}. {{ $kpi['hint'] }}">
                            @else
                                <div class="card card-sm h-100">
                            @endif
                                <div class="card-body">
                                    <div class="subheader">{{ $kpi['label'] }}</div>
                                    <div class="h2 mb-0 mt-1">{{ $kpi['value'] }}</div>
                                    @if (filled($kpi['hint'] ?? null))
                                        <div class="text-secondary small mt-1">{{ $kpi['hint'] }}</div>
                                    @endif
                                </div>
                            @if (filled($kpi['url'] ?? null))
                                </a>
                            @else
                                </div>
                            @endif
                        </div>
                    @endforeach
                </div>
                <p class="text-secondary small mt-2 mb-0">
                    Approved sales includes all approved dollars on open repair orders. The cards highlight approved work still in the shop and work on pickup-ready repair orders.
                </p>
            </section>

            <section class="card" aria-label="Sales by status">
                <div class="card-header">
                    <div>
                        <h2 class="card-title">Sales by status</h2>
                        <div class="card-subtitle">{{ $dashboard->footnote }}</div>
                    </div>
                </div>
                <div class="table-responsive">
                    <table class="table table-vcenter card-table">
                        <thead>
                            <tr>
                                <th scope="col">Status</th>
                                <th scope="col" class="text-end">Pending ($)</th>
                                <th scope="col" class="text-end">Declined ($)</th>
                                <th scope="col" class="text-end">Approved ($)</th>
                                <th scope="col" class="text-end" title="Approved sales divided by cars in this status">Approved / RO</th>
                                <th scope="col" class="text-end">Car Count</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr class="fw-bold">
                                <th scope="row">Totals</th>
                                <td @class(['text-end', 'text-secondary' => $dashboard->pendingCents === 0])>
                                    <a href="{{ $dashboard->pendingUrl }}" class="text-reset">{{ $dashboard->pendingLabel }}</a>
                                </td>
                                <td @class(['text-end', 'text-secondary' => $dashboard->declinedCents === 0])>
                                    <a href="{{ $dashboard->declinedUrl }}" class="text-reset">{{ $dashboard->declinedLabel }}</a>
                                </td>
                                <td @class(['text-end', 'text-secondary' => $dashboard->approvedCents === 0])>
                                    <a href="{{ $dashboard->approvedUrl }}" class="text-reset">{{ $dashboard->approvedLabel }}</a>
                                </td>
                                <td @class(['text-end', 'text-secondary' => $dashboard->aroCents === 0])>
                                    <a href="{{ $dashboard->approvedUrl }}" class="text-reset">{{ $dashboard->aroLabel }}</a>
                                </td>
                                <td class="text-end">
                                    <a href="{{ $dashboard->openQueueUrl }}" class="text-reset">{{ $dashboard->carCount }}</a>
                                </td>
                            </tr>
                            @forelse ($dashboard->statusRows as $row)
                                <tr @class(['table-active' => $row['peak']])>
                                    <th scope="row" class="fw-normal">
                                        <a href="{{ $row['status_url'] }}" class="text-reset">{{ $row['label'] }}</a>
                                    </th>
                                    <td @class(['text-end', 'text-secondary' => $row['pending_cents'] === 0])>
                                        <a href="{{ $row['pending_url'] }}" class="text-reset">{{ $row['pending_label'] }}</a>
                                    </td>
                                    <td @class(['text-end', 'text-secondary' => $row['declined_cents'] === 0])>
                                        <a href="{{ $row['declined_url'] }}" class="text-reset">{{ $row['declined_label'] }}</a>
                                    </td>
                                    <td @class(['text-end', 'text-secondary' => $row['approved_cents'] === 0])>
                                        <a href="{{ $row['approved_url'] }}" class="text-reset">{{ $row['approved_label'] }}</a>
                                    </td>
                                    <td @class(['text-end', 'text-secondary' => $row['aro_cents'] === 0])>
                                        <a href="{{ $row['approved_url'] }}" class="text-reset">{{ $row['aro_label'] }}</a>
                                    </td>
                                    <td class="text-end">
                                        <a href="{{ $row['status_url'] }}" class="text-reset">{{ $row['car_count'] }}</a>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="6" class="text-secondary">No open repair orders.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </section>
        </x-tabler.page>
    @else
        <x-tabler.page layout="fluid" title="Today" :title-count="$today->attentionCount" :subtitle="$today->greeting" actions-label="Today shortcuts">
            <x-slot:actions>
                <x-tabler.page-action :href="route('operations.workboard')" icon="layout-grid" label="Job Board" />
            </x-slot:actions>

            @include('operations.today.partials.technician-lanes', ['today' => $today, 'sections' => $sections])
        </x-tabler.page>
    @endif
</x-operations.app>
