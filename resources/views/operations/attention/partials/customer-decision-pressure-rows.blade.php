@props([
    'rows' => [],
    'showScheduleActions' => true,
    'compactActions' => false,
])

@foreach ($rows as $row)
    @php
        $schedule = is_array($row['schedule'] ?? null) ? $row['schedule'] : null;
        $hasActiveSchedule = filled($schedule['id'] ?? null);
        $isReminder = (bool) ($schedule['is_reminder'] ?? false);
        $ageLabel = ($row['age_context'] ?? null) === 'since_estimate_sent'
            ? ($row['age_label'] ?? '').' since sent'
            : ($row['age_label'] ?? '');
    @endphp
    <li class="ops-decision-pressure-row group {{ $compactActions ? 'ops-decision-pressure-row--dispatch' : '' }} {{ $isReminder ? 'ops-decision-pressure-row--reminder' : '' }}">
        @if ($compactActions)
            <div class="ops-decision-pressure-dispatch">
                <a href="{{ $row['url'] }}" class="ops-decision-pressure-dispatch-identity">
                    <span class="ops-decision-pressure-dollars">{{ $row['dollars_at_risk_label'] }}</span>
                    <span class="ops-decision-pressure-dispatch-names">
                        <span class="ops-decision-pressure-dispatch-customer">{{ $row['customer_name'] }}</span>
                        <span class="ops-decision-pressure-dispatch-vehicle">{{ $row['vehicle_label'] }}</span>
                    </span>
                </a>

                <div class="ops-decision-pressure-dispatch-meta">
                    @if ($isReminder)
                        <span class="ops-decision-pressure-dispatch-tag">Reminder</span>
                    @endif
                    @if (filled($ageLabel))
                        <span class="ops-decision-pressure-dispatch-age">{{ $ageLabel }}</span>
                    @endif
                    @if (filled($row['detail'] ?? null))
                        <span class="ops-decision-pressure-dispatch-detail">{{ $row['detail'] }}</span>
                    @endif
                    @if (filled($row['last_customer_activity'] ?? null))
                        <span class="ops-decision-pressure-dispatch-activity">{{ $row['last_customer_activity'] }}</span>
                    @endif
                </div>

                <div class="ops-decision-pressure-dispatch-actions ops-call-queue__actions">
                    @if (! empty($row['text_url']))
                        <a href="{{ $row['text_url'] }}" class="ops-call-queue__action">Text</a>
                    @endif
                    @if (! empty($row['callback_phone']))
                        <button
                            type="button"
                            class="ops-call-queue__action ops-call-queue__action--primary"
                            onclick="window.arkInitiateTelephonyCallback?.({
                                customerId: {{ $row['customer_id'] ? (int) $row['customer_id'] : 'null' }},
                                phone: @js($row['callback_phone']),
                                button: this,
                            })"
                        >Call</button>
                    @elseif (! empty($row['customer_url']))
                        <a href="{{ $row['customer_url'] }}" class="ops-call-queue__action">Call</a>
                    @endif
                    <a href="{{ $row['url'] }}" class="ops-call-queue__action">RO</a>
                </div>
            </div>
        @else
            <div class="ops-decision-pressure-row-top">
                <div class="ops-decision-pressure-row-main">
                    <a href="{{ $row['url'] }}" class="ops-decision-pressure-row-link">
                        <p class="ops-decision-pressure-dollars">{{ $row['dollars_at_risk_label'] }}</p>
                        <div class="ops-decision-pressure-row-identity">
                            <p class="ops-queue-row__identity group-hover:text-ops-accent-900">{{ $row['customer_name'] }}</p>
                            <p class="text-[11px] text-slate-500">{{ $row['vehicle_label'] }}</p>
                        </div>
                    </a>
                </div>
                <div class="ops-decision-pressure-row-meta">
                    @if ($isReminder)
                        <p class="text-[11px] font-bold uppercase tracking-wide text-amber-800">Reminder</p>
                    @endif
                    <p class="text-xs font-semibold tabular-nums text-slate-700">
                        @if (($row['age_context'] ?? null) === 'since_estimate_sent')
                            {{ $row['age_label'] }} since sent
                        @else
                            {{ $row['age_label'] }}
                        @endif
                    </p>
                    <p class="text-[11px] leading-4 text-slate-500">{{ $row['detail'] }}</p>
                    @if ($hasActiveSchedule && filled($schedule['assigned_to_label'] ?? null))
                        <p class="text-[11px] leading-4 text-slate-500">Scheduled by {{ $schedule['assigned_to_label'] }}</p>
                    @endif
                    @if (filled($row['last_customer_activity'] ?? null))
                        <p class="text-[11px] font-semibold leading-4 text-indigo-800">{{ $row['last_customer_activity'] }}</p>
                    @endif
                </div>
            </div>
            <div class="ops-decision-pressure-row-foot">
                <div class="ops-decision-pressure-row-actions ops-call-queue__actions">
                    @if (! empty($row['text_url']))
                        <a href="{{ $row['text_url'] }}" class="ops-call-queue__action">Text</a>
                    @endif
                    @if (! empty($row['callback_phone']))
                        <button
                            type="button"
                            class="ops-call-queue__action ops-call-queue__action--primary"
                            onclick="window.arkInitiateTelephonyCallback?.({
                                customerId: {{ $row['customer_id'] ? (int) $row['customer_id'] : 'null' }},
                                phone: @js($row['callback_phone']),
                                button: this,
                            })"
                        >Call</button>
                    @elseif (! empty($row['customer_url']))
                        <a href="{{ $row['customer_url'] }}" class="ops-call-queue__action">Call</a>
                    @endif
                    @if (! empty($row['customer_url']))
                        <a href="{{ $row['customer_url'] }}" class="ops-call-queue__action">Customer</a>
                    @endif
                    <a href="{{ $row['url'] }}" class="ops-call-queue__action">RO</a>
                    @include('operations.work.partials.advisor-work-item-quick-add', [
                        'kind' => 'follow-up',
                        'storeRoute' => route('operations.work.follow-ups.store'),
                        'row' => $row,
                    ])
                    @include('operations.work.partials.advisor-work-item-quick-add', [
                        'kind' => 'task',
                        'storeRoute' => route('operations.work.tasks.store'),
                        'row' => $row,
                    ])
                    @if ($showScheduleActions && $schedule !== null)
                        @if ($hasActiveSchedule)
                            <form method="POST" action="{{ $schedule['clear_url'] }}" class="inline">
                                @csrf
                                @method('PATCH')
                                <button type="submit" class="ops-call-queue__action ops-call-queue__action--ghost">Clear</button>
                            </form>
                        @else
                            @php
                                $scheduleQuickAddId = 'ops-decision-quick-add-'.$schedule['repair_order_shop_number'].'-schedule';
                            @endphp
                            <details class="ops-decision-schedule-form" id="{{ $scheduleQuickAddId }}">
                                <summary class="ops-call-queue__action ops-call-queue__action--ghost cursor-pointer list-none">Schedule</summary>
                                <div
                                    class="ops-work-item-quick-add-panel ops-decision-schedule-panel"
                                    data-decision-quick-add-owner="{{ $scheduleQuickAddId }}"
                                >
                                    <div class="ops-work-item-quick-add-head">
                                        <p class="ops-work-item-quick-add-context">
                                            Schedule · {{ $row['customer_name'] }}
                                            <span class="text-slate-400">·</span>
                                            RO #{{ $schedule['repair_order_shop_number'] }}
                                        </p>
                                        <button
                                            type="button"
                                            class="ops-work-item-quick-add-close"
                                            data-work-item-quick-add-cancel
                                            aria-label="Cancel schedule"
                                        >×</button>
                                    </div>
                                    <form method="POST" action="{{ $schedule['store_url'] }}" class="ops-decision-schedule-form-form space-y-2">
                                        @csrf
                                        <input type="hidden" name="repair_order_shop_number" value="{{ $schedule['repair_order_shop_number'] }}">
                                        <label class="block">
                                            <span class="text-[10px] font-bold uppercase tracking-wide text-slate-400">Check back on</span>
                                            <input type="date" name="scheduled_for" required min="{{ now()->toDateString() }}" class="mt-0.5 h-8 w-full rounded-sm border border-slate-300 bg-white px-2 text-sm text-slate-950">
                                        </label>
                                        <label class="block">
                                            <span class="text-[10px] font-bold uppercase tracking-wide text-slate-400">Why wait? (optional)</span>
                                            <input type="text" name="notes" maxlength="500" placeholder="Payday, spouse approval, out of town…" class="mt-0.5 h-8 w-full rounded-sm border border-slate-300 bg-white px-2 text-sm text-slate-950 placeholder:text-slate-400">
                                        </label>
                                        @if (filled($schedule['customer_name'] ?? null))
                                            <label class="ops-decision-schedule-scope flex items-start gap-2">
                                                <input type="hidden" name="schedule_customer" value="0">
                                                <input type="checkbox" name="schedule_customer" value="1" class="mt-0.5 rounded border-slate-300">
                                                <span>
                                                    <span class="block text-[11px] font-semibold leading-4 text-slate-800">Every open decision for {{ $schedule['customer_name'] }}</span>
                                                    <span class="mt-0.5 block text-[10px] leading-4 text-slate-500">Same follow-up day for all their waiting estimates. Off = only RO #{{ $schedule['repair_order_shop_number'] }}.</span>
                                                </span>
                                            </label>
                                        @else
                                            <p class="text-[10px] leading-4 text-slate-500">Snoozes only RO #{{ $schedule['repair_order_shop_number'] }}.</p>
                                        @endif
                                        <p class="text-[10px] leading-4 text-slate-500">Leaves Work until the day before — then returns as a reminder.</p>
                                        <div class="ops-work-item-quick-add-actions">
                                            <button type="button" class="ops-work-item-quick-add-cancel" data-work-item-quick-add-cancel>Cancel</button>
                                            <button type="submit" class="ops-work-item-quick-add-save">Save schedule</button>
                                        </div>
                                    </form>
                                </div>
                            </details>
                        @endif
                    @endif
                </div>
                <div class="ops-decision-pressure-row-panel-slot"></div>
            </div>
        @endif
    </li>
@endforeach
