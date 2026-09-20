@props([
    'row',
    'showDayLabel' => false,
])

<li class="ops-schedule-row">
    <div class="flex flex-wrap items-start justify-between gap-2">
        <div class="flex min-w-0 flex-1 items-start gap-3">
            <div class="w-16 shrink-0">
                @if ($showDayLabel && ! empty($row['day_label']))
                    <p class="ops-schedule-row__day">{{ $row['day_label'] }}</p>
                @endif
                <p class="ops-schedule-row__time">{{ $row['time_label'] }}</p>
            </div>
            <div class="min-w-0">
                <a href="{{ $row['show_url'] }}" class="ops-schedule-row__customer">{{ $row['customer_name'] }}</a>
                @if ($row['vehicle_label'])
                    <p class="ops-schedule-row__vehicle">{{ $row['vehicle_label'] }}</p>
                @else
                    <p class="ops-schedule-row__vehicle text-slate-400">Vehicle not set yet</p>
                @endif
                <p class="ops-schedule-row__meta">
                    @if (! empty($row['is_mine']))
                        <span class="font-semibold text-slate-900">Yours</span>
                        <span>·</span>
                    @endif
                    <span>{{ $row['concern'] }}</span>
                    @if (! empty($row['technician_label']))
                        <span>·</span>
                        <span>{{ $row['technician_label'] }}</span>
                    @endif
                    @if (! empty($row['workstation_label']))
                        <span>·</span>
                        <span>{{ $row['workstation_label'] }}</span>
                    @endif
                    @if (! empty($row['estimated_labor_label']))
                        <span>·</span>
                        <span>{{ $row['estimated_labor_label'] }}</span>
                    @endif
                    @if (! empty($row['arrival_type_label']))
                        <span>·</span>
                        <span>{{ $row['arrival_type_label'] }}</span>
                    @endif
                    @if ($row['advisor_label'])
                        <span>·</span>
                        <span>{{ $row['advisor_label'] }}</span>
                    @endif
                </p>
            </div>
        </div>
        <div class="ops-call-queue__actions shrink-0">
            <span class="ops-call-queue__action ops-call-queue__action--ghost pointer-events-none">{{ $row['status_label'] }}</span>
            @if (! empty($row['call_url']))
                <a href="{{ $row['call_url'] }}" class="ops-call-queue__action">Call</a>
            @endif
            @if (! empty($row['text_url']))
                <a href="{{ $row['text_url'] }}" class="ops-call-queue__action">Text</a>
            @endif
            @if (! empty($row['customer_url']))
                <a href="{{ $row['customer_url'] }}" class="ops-call-queue__action">Customer</a>
            @endif
            @if (! empty($row['repair_order_url']))
                <a href="{{ $row['repair_order_url'] }}" class="ops-call-queue__action">RO</a>
            @endif
        </div>
    </div>
</li>
