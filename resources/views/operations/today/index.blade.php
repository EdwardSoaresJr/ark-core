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
                        <p class="ops-shop-dash-toolbar__meta">{{ $dashboard->rangeLabel }}</p>
                    </div>
                    <div class="ops-shop-dash-toolbar__tools">
                        <a href="{{ $dashboard->jobBoardUrl }}" class="ops-page-link">Job Board</a>
                        @can(App\Ark\Runtime\Authorization\ArkCapability::OperationsAccess->value)
                            <a href="{{ \App\Ark\Operations\Communications\CommunicationsNeedsYou::url() }}" class="ops-page-link">Comms</a>
                        @endcan
                        @if (\App\Ark\Operations\Business\BusinessWorkspaceAccess::allows(auth()->user()))
                            <a href="{{ route('operations.business') }}" class="ops-page-link">Business</a>
                        @endif
                    </div>
                </div>

                <div class="ops-shop-dash-kpis" role="list" aria-label="Shop KPIs">
                    @foreach ($dashboard->kpis as $kpi)
                        @if (filled($kpi['url'] ?? null))
                            <a href="{{ $kpi['url'] }}" class="ops-shop-dash-kpi ops-shop-dash-kpi--link" role="listitem" title="Open matching repair orders">
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
                        <h2 class="ops-shop-dash-chart__title">Car Count</h2>
                        <p class="ops-shop-dash-chart__note">{{ $dashboard->footnote }}</p>
                    </div>
                    @if ($dashboard->statusRows === [])
                        <p class="ops-shop-dash-chart__empty">No open cars on the board.</p>
                    @else
                        <div class="ops-shop-dash-bars">
                            @foreach ($dashboard->statusRows as $row)
                                <a
                                    href="{{ $row['status_url'] }}"
                                    class="ops-shop-dash-bar ops-shop-dash-bar--link"
                                    title="Open {{ $row['label'] }} repair orders ({{ $row['car_count'] }})"
                                >
                                    <div class="ops-shop-dash-bar__track">
                                        <div
                                            class="ops-shop-dash-bar__fill ops-shop-dash-bar__fill--{{ $row['key'] }}"
                                            style="height: {{ max(8, $row['bar_pct']) }}%"
                                        ></div>
                                    </div>
                                    <span class="ops-shop-dash-bar__count">{{ $row['car_count'] }}</span>
                                    <span class="ops-shop-dash-bar__label">{{ $row['label'] }}</span>
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
                                <td class="ops-shop-dash-table__num">
                                    <a href="{{ $dashboard->pendingUrl }}" class="ops-shop-dash-table__link">{{ $dashboard->pendingLabel }}</a>
                                </td>
                                <td class="ops-shop-dash-table__num">
                                    <a href="{{ $dashboard->declinedUrl }}" class="ops-shop-dash-table__link">{{ $dashboard->declinedLabel }}</a>
                                </td>
                                <td class="ops-shop-dash-table__num">
                                    <a href="{{ $dashboard->approvedUrl }}" class="ops-shop-dash-table__link">{{ $dashboard->approvedLabel }}</a>
                                </td>
                                <td class="ops-shop-dash-table__num">
                                    <a href="{{ $dashboard->approvedUrl }}" class="ops-shop-dash-table__link">{{ $dashboard->aroLabel }}</a>
                                </td>
                                <td class="ops-shop-dash-table__num">
                                    <a href="{{ $dashboard->openQueueUrl }}" class="ops-shop-dash-table__link">{{ $dashboard->carCount }}</a>
                                </td>
                            </tr>
                            @forelse ($dashboard->statusRows as $row)
                                <tr>
                                    <th scope="row">
                                        <a href="{{ $row['status_url'] }}" class="ops-shop-dash-table__link">{{ $row['label'] }}</a>
                                    </th>
                                    <td class="ops-shop-dash-table__num">
                                        <a href="{{ $row['pending_url'] }}" class="ops-shop-dash-table__link">{{ $row['pending_label'] }}</a>
                                    </td>
                                    <td class="ops-shop-dash-table__num">
                                        <a href="{{ $row['declined_url'] }}" class="ops-shop-dash-table__link">{{ $row['declined_label'] }}</a>
                                    </td>
                                    <td class="ops-shop-dash-table__num">
                                        <a href="{{ $row['approved_url'] }}" class="ops-shop-dash-table__link">{{ $row['approved_label'] }}</a>
                                    </td>
                                    <td class="ops-shop-dash-table__num">
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
