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
    $rowClass = $isFollowUp ? 'ops-work-item-row ops-work-item-row--follow-up' : 'ops-work-item-row ops-work-item-row--task';
    $scheduleTone = $isFollowUp ? 'text-violet-800' : 'text-emerald-800';
@endphp

@if ($totalCount === 0)
    <p class="px-3 py-2 text-sm text-slate-600">{{ $empty }}</p>
@else
    @foreach ($bucketLabels as $bucket => $label)
        @if (($groups[$bucket] ?? []) !== [])
            <div class="border-t border-slate-100 first:border-t-0">
                <p class="px-3 pb-1 pt-2 text-[10px] font-bold uppercase tracking-wide {{ $isFollowUp ? 'text-violet-700' : 'text-emerald-800' }}">{{ $label }}</p>
                <ul class="divide-y divide-slate-100">
                    @foreach ($groups[$bucket] as $row)
                        <li class="{{ $rowClass }}">
                            <div class="flex flex-wrap items-start justify-between gap-2">
                                <div class="min-w-0 flex-1">
                                    @if ($isFollowUp && filled($row['customer_name'] ?? null))
                                        <div class="flex flex-wrap items-baseline gap-x-2 gap-y-0.5">
                                            <p class="text-sm font-black text-violet-950">{{ $row['customer_name'] }}</p>
                                            @if (filled($row['dollars_at_risk_label'] ?? null))
                                                <p class="text-sm font-black tabular-nums text-violet-900">{{ $row['dollars_at_risk_label'] }}</p>
                                            @endif
                                        </div>
                                    @elseif (! $isFollowUp)
                                        <p class="text-[10px] font-bold uppercase tracking-[0.08em] text-emerald-800">Shop task</p>
                                    @endif
                                    <p class="{{ ($isFollowUp && filled($row['customer_name'] ?? null)) || ! $isFollowUp ? 'mt-0.5' : '' }} text-sm font-bold text-slate-950">{{ $row['notes'] }}</p>
                                    <p class="mt-0.5 text-[11px] text-slate-500">
                                        @if (! empty($row['is_mine']))
                                            <span class="font-semibold text-slate-900">Yours</span>
                                            <span>·</span>
                                        @endif
                                        @if (filled($row['schedule_label'] ?? null))
                                            <span class="font-semibold {{ $bucket === 'overdue' ? 'text-rose-700' : $scheduleTone }}">{{ $row['schedule_label'] }}</span>
                                            @if (filled($row['due_time_label'] ?? null))
                                                <span>at {{ $row['due_time_label'] }}</span>
                                            @endif
                                            <span>·</span>
                                        @endif
                                        <span>Assigned to {{ $row['assigned_to_label'] }}</span>
                                        <span>·</span>
                                        <span>{{ $row['context_label'] }}</span>
                                    </p>
                                </div>
                                <div class="ops-call-queue__actions shrink-0">
                                    @if (! empty($row['customer_url']))
                                        <a href="{{ $row['customer_url'] }}" class="ops-call-queue__action">Customer</a>
                                    @endif
                                    @if (! empty($row['repair_order_url']))
                                        <a href="{{ $row['repair_order_url'] }}" class="ops-call-queue__action">RO</a>
                                    @endif
                                    <form method="POST" action="{{ $row['complete_url'] }}" class="inline">
                                        @csrf
                                        @method('PATCH')
                                        <input type="hidden" name="redirect" value="{{ url()->current() }}">
                                        <button type="submit" class="ops-call-queue__action ops-call-queue__action--ghost">Done</button>
                                    </form>
                                </div>
                            </div>
                        </li>
                    @endforeach
                </ul>
            </div>
        @endif
    @endforeach
@endif
