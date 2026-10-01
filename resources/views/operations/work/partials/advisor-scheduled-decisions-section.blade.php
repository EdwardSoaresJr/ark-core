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
    <p class="px-3 py-2 text-sm text-slate-600">{{ $empty }}</p>
@else
    @foreach ($bucketLabels as $bucket => $label)
        @if (($groups[$bucket] ?? []) !== [])
            <div class="border-t border-slate-100 first:border-t-0">
                <p class="px-3 pb-1 pt-2 text-[10px] font-bold uppercase tracking-wide text-amber-800">{{ $label }}</p>
                <ul class="divide-y divide-slate-100">
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
                        <li class="ops-work-item-row ops-work-item-row--scheduled ops-work-scheduled-row">
                            <div class="ops-work-scheduled-row__main">
                                <div class="ops-work-scheduled-row__content min-w-0">
                                    <p class="ops-work-scheduled-row__line1">
                                        @if (! empty($row['repair_order_url']))
                                            <a href="{{ $row['repair_order_url'] }}" class="font-black text-amber-950 hover:text-ops-accent-900">{{ $row['customer_name'] }}</a>
                                        @else
                                            <span class="font-black text-amber-950">{{ $row['customer_name'] }}</span>
                                        @endif
                                        @if (filled($row['dollars_at_risk_label'] ?? null))
                                            <span class="font-black tabular-nums text-amber-900">{{ $row['dollars_at_risk_label'] }}</span>
                                        @endif
                                        @if ($showScheduleOnLine1)
                                            <span class="font-semibold text-amber-800">{{ $row['schedule_label'] }}</span>
                                        @endif
                                        @if (! empty($row['is_mine']))
                                            <span class="text-[10px] font-bold uppercase tracking-wide text-slate-500">Yours</span>
                                        @endif
                                    </p>
                                    @if ($line2 !== '')
                                        <p class="ops-work-scheduled-row__line2" title="{{ $line2 }}">{{ $line2 }}</p>
                                    @endif
                                </div>
                                <div class="ops-work-scheduled-row__actions ops-call-queue__actions">
                                    @if (! empty($row['text_url']))
                                        <a href="{{ $row['text_url'] }}" class="ops-call-queue__action">Text</a>
                                    @endif
                                    @if (! empty($row['callback_phone']))
                                        <button
                                            type="button"
                                            class="ops-call-queue__action ops-call-queue__action--primary"
                                            onclick="window.arkInitiateTelephonyCallback?.({
                                                customerId: null,
                                                phone: @js($row['callback_phone']),
                                                button: this,
                                            })"
                                        >Call</button>
                                    @endif
                                    @if (! empty($row['clear_url']))
                                        <form method="POST" action="{{ $row['clear_url'] }}" class="inline">
                                            @csrf
                                            @method('PATCH')
                                            <input type="hidden" name="redirect" value="{{ url()->current() }}">
                                            <button type="submit" class="ops-call-queue__action ops-call-queue__action--ghost">Clear</button>
                                        </form>
                                    @endif
                                </div>
                            </div>
                        </li>
                    @endforeach
                </ul>
            </div>
        @endif
    @endforeach
@endif
