@props([
    'groups' => [],
    'empty' => 'Nothing scheduled for a later day.',
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
            <div class="card-body pb-1 pt-2 subheader text-yellow">{{ $label }}</div>
            <div class="list-group list-group-flush" data-work-scheduled-decisions>
                @foreach ($groups[$bucket] as $row)
                    @php
                        $line2Parts = array_values(array_filter([
                            filled($row['notes'] ?? null) ? $row['notes'] : null,
                            filled($row['context_label'] ?? null) ? $row['context_label'] : null,
                            filled($row['returns_label'] ?? null) ? $row['returns_label'] : null,
                            (! ($row['is_mine'] ?? false) && filled($row['assigned_to_label'] ?? null))
                                ? $row['assigned_to_label']
                                : null,
                        ]));
                        $line2 = implode(' · ', $line2Parts);
                        $showScheduleOnLine1 = $bucket !== 'tomorrow' && filled($row['schedule_label'] ?? null);
                    @endphp
                    <div class="list-group-item">
                        <div class="row g-2 align-items-center">
                            <div class="col-12 col-md overflow-hidden">
                                <div class="d-flex flex-wrap align-items-baseline column-gap-2">
                                    @if (! empty($row['repair_order_url']))
                                        <a href="{{ $row['repair_order_url'] }}" class="fw-bold">{{ $row['customer_name'] }}</a>
                                    @else
                                        <span class="fw-bold">{{ $row['customer_name'] }}</span>
                                    @endif
                                    @if (filled($row['dollars_at_risk_label'] ?? null))
                                        <span class="fw-bold ark-money">{{ $row['dollars_at_risk_label'] }}</span>
                                    @endif
                                    @if ($showScheduleOnLine1)
                                        <span class="fw-medium text-yellow">{{ $row['schedule_label'] }}</span>
                                    @endif
                                    @if (! empty($row['is_mine']))
                                        <span class="subheader">Yours</span>
                                    @endif
                                </div>
                                @if ($line2 !== '')
                                    <div class="small text-secondary text-truncate" title="{{ $line2 }}">{{ $line2 }}</div>
                                @endif
                            </div>
                            <div class="col-12 col-md-auto">
                                <div class="btn-list">
                                    @if (! empty($row['text_url']))
                                        <a href="{{ $row['text_url'] }}" class="btn btn-sm">Text</a>
                                    @endif
                                    @if (! empty($row['callback_phone']))
                                        <button
                                            type="button"
                                            class="btn btn-sm btn-primary"
                                            onclick="window.arkInitiateTelephonyCallback?.({
                                                customerId: null,
                                                phone: @js($row['callback_phone']),
                                                button: this,
                                            })"
                                        >Call</button>
                                    @endif
                                    @if (! empty($row['clear_url']))
                                        <form method="POST" action="{{ $row['clear_url'] }}">
                                            @csrf
                                            @method('PATCH')
                                            <input type="hidden" name="redirect" value="{{ url()->current() }}">
                                            <button type="submit" class="btn btn-sm btn-outline-secondary">Clear</button>
                                        </form>
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
