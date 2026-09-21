@php
    $dashboard = $today->shopDashboard;
    $sections = $today->nonEmptySections();
@endphp

<x-operations.app title="Today">
    @if ($dashboard !== null)
        <section class="ops-today ops-today--dashboard">
            <div class="ops-today__body">
                <div class="ops-shop-dash-toolbar">
                    <div class="ops-shop-dash-toolbar__brand">
                        <h1 class="ops-shop-dash-toolbar__title">Shop Dashboard</h1>
                        <p class="ops-shop-dash-toolbar__meta">
                            <span class="ops-shop-dash-toolbar__context">{{ $dashboard->rangeLabel }}</span>
                            <span class="ops-shop-dash-toolbar__sep" aria-hidden="true">·</span>
                            <time class="ops-shop-dash-toolbar__date" datetime="{{ $dashboard->asOfDate }}">{{ $dashboard->asOfLabel }}</time>
                        </p>
                    </div>
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

                <div class="ops-shop-dash-kpis" role="list" aria-label="Shop KPIs">
                    @foreach ($dashboard->kpis as $kpi)
                        @if (filled($kpi['url'] ?? null))
                            <a
                                href="{{ $kpi['url'] }}"
                                class="ops-shop-dash-kpi ops-shop-dash-kpi--link"
                                role="listitem"
                                aria-label="{{ $kpi['label'] }} {{ $kpi['value'] }}. Open matching repair orders"
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

                <section class="ops-shop-dash-chart" aria-label="Car count by status">
                    <div class="ops-shop-dash-chart__head">
                        <h2 class="ops-shop-dash-chart__title">Car count by status</h2>
                        <p class="ops-shop-dash-chart__note">{{ $dashboard->footnote }}</p>
                    </div>
                    @if ($dashboard->chartRows === [])
                        <p class="ops-shop-dash-chart__empty">No open cars on the board.</p>
                    @else
                        <div class="ops-shop-dash-lanes">
                            @foreach ($dashboard->chartRows as $row)
                                <a
                                    href="{{ $row['status_url'] }}"
                                    @class([
                                        'ops-shop-dash-lane',
                                        'ops-shop-dash-lane--peak' => $row['peak'],
                                    ])
                                    aria-label="{{ $row['label'] }}: {{ $row['car_count'] }} {{ $row['car_count'] === 1 ? 'car' : 'cars' }}"
                                >
                                    <span class="ops-shop-dash-lane__label">{{ $row['label'] }}</span>
                                    <span class="ops-shop-dash-lane__count">{{ $row['car_count'] }}</span>
                                    <span class="ops-shop-dash-lane__track">
                                        <span
                                            class="ops-shop-dash-lane__fill"
                                            style="width: {{ max(3, $row['bar_pct']) }}%"
                                        ></span>
                                    </span>
                                </a>
                            @endforeach
                        </div>
                    @endif
                </section>

                <section class="ops-shop-dash-table-wrap" aria-label="Sales by status">
                    <table class="ops-shop-dash-table">
                        <thead>
                            <tr>
                                <th scope="col">Status</th>
                                <th scope="col" class="ops-shop-dash-table__num">Pending ($)</th>
                                <th scope="col" class="ops-shop-dash-table__num">Declined ($)</th>
                                <th scope="col" class="ops-shop-dash-table__num">Approved ($)</th>
                                <th scope="col" class="ops-shop-dash-table__num">ARO ($)</th>
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
                </section>
            </div>
        </section>
    @else
        {{-- Technician assigned-work lanes --}}
        @include('operations.today.partials.technician-lanes', ['today' => $today, 'sections' => $sections])
    @endif
</x-operations.app>
