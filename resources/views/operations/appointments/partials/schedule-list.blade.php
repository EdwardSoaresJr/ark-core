@props([
    'groups' => [],
    'empty' => 'No appointments yet. Schedule the next drop-off.',
])

@php
    $totalCount = (int) ($groups['total_count'] ?? 0);
    $bucketLabels = [
        'today' => 'Today',
        'tomorrow' => 'Tomorrow',
        'upcoming' => 'Upcoming',
    ];
@endphp

@if ($totalCount === 0)
    <div class="card-body text-secondary">{{ $empty }}</div>
@else
    @foreach ($bucketLabels as $bucket => $label)
        @if (($groups[$bucket] ?? []) !== [])
            <div class="card-body pb-1 pt-2 subheader text-azure">{{ $label }}</div>
            <div class="list-group list-group-flush" data-work-schedule-list>
                @foreach ($groups[$bucket] as $row)
                    @php
                        $meta = array_values(array_filter([
                            $row['concern'] ?? null,
                            $row['technician_label'] ?? null,
                            $row['workstation_label'] ?? null,
                            $row['estimated_labor_label'] ?? null,
                            $row['arrival_type_label'] ?? null,
                            $row['advisor_label'] ?? null,
                        ], fn ($value) => filled($value)));
                    @endphp
                    <div class="list-group-item">
                        <div class="row g-2 align-items-center">
                            <div class="col-auto text-nowrap">
                                @if ($bucket === 'upcoming' && ! empty($row['day_label']))
                                    <div class="small text-secondary">{{ $row['day_label'] }}</div>
                                @endif
                                <div class="fw-bold">{{ $row['time_label'] }}</div>
                            </div>
                            <div class="col overflow-hidden">
                                <a href="{{ $row['show_url'] }}" class="fw-bold">{{ $row['customer_name'] }}</a>
                                <div @class(['small', 'text-secondary' => (bool) $row['vehicle_label'], 'text-muted' => ! $row['vehicle_label']])>{{ $row['vehicle_label'] ?: 'Vehicle not set yet' }}</div>
                                <div class="small text-secondary">
                                    @if (! empty($row['is_mine']))
                                        <span class="fw-medium text-body">Yours</span> ·
                                    @endif
                                    {{ implode(' · ', $meta) }}
                                </div>
                            </div>
                            <div class="col-12 col-md-auto">
                                <div class="btn-list">
                                    <span class="badge bg-secondary-lt">{{ $row['status_label'] }}</span>
                                    @if (! empty($row['call_url']))
                                        <a href="{{ $row['call_url'] }}" class="btn btn-sm">Call</a>
                                    @endif
                                    @if (! empty($row['text_url']))
                                        <a href="{{ $row['text_url'] }}" class="btn btn-sm">Text</a>
                                    @endif
                                    @if (! empty($row['customer_url']))
                                        <a href="{{ $row['customer_url'] }}" class="btn btn-sm">Customer</a>
                                    @endif
                                    @if (! empty($row['repair_order_url']))
                                        <a href="{{ $row['repair_order_url'] }}" class="btn btn-sm">RO</a>
                                    @endif
                                </div>
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
        @endif
    @endforeach
@endif
