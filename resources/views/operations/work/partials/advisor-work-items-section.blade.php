@props([
    'groups' => [],
    'empty' => 'Nothing scheduled.',
    'variant' => 'follow-up',
])

@php
    $totalCount = (int) ($groups['total_count'] ?? 0);
    $bucketLabels = [
        'overdue' => 'Overdue',
        'today' => 'Today',
        'tomorrow' => 'Tomorrow',
        'upcoming' => 'Upcoming',
    ];
    $isFollowUp = $variant === 'follow-up';
    $toneText = $isFollowUp ? 'text-purple' : 'text-green';
@endphp

@if ($totalCount === 0)
    <div class="card-body text-secondary">{{ $empty }}</div>
@else
    @foreach ($bucketLabels as $bucket => $label)
        @if (($groups[$bucket] ?? []) !== [])
            <div @class(['card-body pb-1 pt-2 subheader', $bucket === 'overdue' ? 'text-danger' : $toneText])>{{ $label }}</div>
            <div class="list-group list-group-flush" data-work-items="{{ $variant }}">
                @foreach ($groups[$bucket] as $row)
                    <div class="list-group-item">
                        <div class="row g-2 align-items-center">
                            <div class="col-12 col-md overflow-hidden">
                                @if ($isFollowUp && filled($row['customer_name'] ?? null))
                                    <div class="d-flex flex-wrap align-items-baseline column-gap-2">
                                        <span class="fw-bold">{{ $row['customer_name'] }}</span>
                                        @if (filled($row['dollars_at_risk_label'] ?? null))
                                            <span class="fw-bold ark-money {{ $toneText }}">{{ $row['dollars_at_risk_label'] }}</span>
                                        @endif
                                    </div>
                                @elseif (! $isFollowUp)
                                    <div class="subheader {{ $toneText }}">Shop task</div>
                                @endif
                                <div class="fw-medium">{{ $row['notes'] }}</div>
                                <div class="small text-secondary">
                                    @if (! empty($row['is_mine']))
                                        <span class="fw-medium text-body">Yours</span> ·
                                    @endif
                                    @if (filled($row['schedule_label'] ?? null))
                                        <span @class(['fw-medium', $bucket === 'overdue' ? 'text-danger' : $toneText])>{{ $row['schedule_label'] }}</span>
                                        @if (filled($row['due_time_label'] ?? null))
                                            at {{ $row['due_time_label'] }}
                                        @endif
                                        ·
                                    @endif
                                    Assigned to {{ $row['assigned_to_label'] }} · {{ $row['context_label'] }}
                                </div>
                            </div>
                            <div class="col-12 col-md-auto">
                                <div class="btn-list">
                                    @if (! empty($row['customer_url']))
                                        <a href="{{ $row['customer_url'] }}" class="btn btn-sm">Customer</a>
                                    @endif
                                    @if (! empty($row['repair_order_url']))
                                        <a href="{{ $row['repair_order_url'] }}" class="btn btn-sm">RO</a>
                                    @endif
                                    <form method="POST" action="{{ $row['complete_url'] }}">
                                        @csrf
                                        @method('PATCH')
                                        <input type="hidden" name="redirect" value="{{ url()->current() }}">
                                        <button type="submit" class="btn btn-sm btn-outline-secondary">Done</button>
                                    </form>
                                </div>
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
        @endif
    @endforeach
@endif
