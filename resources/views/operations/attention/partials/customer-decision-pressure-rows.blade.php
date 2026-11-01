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
    @if ($compactActions)
    {{-- The dispatch variant is only rendered by operations/partials/work-tab-queues, which no route reaches. --}}
    <li class="ops-decision-pressure-row group ops-decision-pressure-row--dispatch {{ $isReminder ? 'ops-decision-pressure-row--reminder' : '' }}">
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
    </li>
    @else
    <div @class(['list-group-item', 'bg-warning-subtle' => $isReminder]) data-decision-row>
        <div class="d-flex flex-wrap align-items-start gap-2">
            <a href="{{ $row['url'] }}" class="d-flex align-items-start gap-2 flex-fill text-reset" style="min-width: 9rem">
                <span class="fw-bold ark-money">{{ $row['dollars_at_risk_label'] }}</span>
                <span class="min-w-0">
                    <span class="d-block fw-medium text-truncate">{{ $row['customer_name'] }}</span>
                    <span class="d-block small text-secondary text-truncate">{{ $row['vehicle_label'] }}</span>
                </span>
            </a>
            <div class="ms-auto text-end small">
                @if ($isReminder)
                    <span class="badge bg-yellow text-yellow-fg">Reminder</span>
                @endif
                <div class="fw-medium ark-money">{{ $ageLabel }}</div>
                <div class="text-secondary">{{ $row['detail'] }}</div>
                @if ($hasActiveSchedule && filled($schedule['assigned_to_label'] ?? null))
                    <div class="text-secondary">Scheduled by {{ $schedule['assigned_to_label'] }}</div>
                @endif
                @if (filled($row['last_customer_activity'] ?? null))
                    <div class="fw-medium text-purple">{{ $row['last_customer_activity'] }}</div>
                @endif
            </div>
        </div>
        <div class="mt-2" data-decision-row-foot>
            <div class="d-flex flex-wrap align-items-start gap-1" data-decision-row-actions>
                @if (! empty($row['text_url']))
                    <a href="{{ $row['text_url'] }}" class="btn btn-sm">Text</a>
                @endif
                @if (! empty($row['callback_phone']))
                    <button
                        type="button"
                        class="btn btn-sm btn-primary"
                        onclick="window.arkInitiateTelephonyCallback?.({
                            customerId: {{ $row['customer_id'] ? (int) $row['customer_id'] : 'null' }},
                            phone: @js($row['callback_phone']),
                            button: this,
                        })"
                    >Call</button>
                @elseif (! empty($row['customer_url']))
                    <a href="{{ $row['customer_url'] }}" class="btn btn-sm">Call</a>
                @endif
                @if (! empty($row['customer_url']))
                    <a href="{{ $row['customer_url'] }}" class="btn btn-sm">Customer</a>
                @endif
                <a href="{{ $row['url'] }}" class="btn btn-sm">RO</a>
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
                        <form method="POST" action="{{ $schedule['clear_url'] }}" class="d-inline">
                            @csrf
                            @method('PATCH')
                            <button type="submit" class="btn btn-sm btn-ghost-secondary">Clear</button>
                        </form>
                    @else
                        @php
                            $scheduleQuickAddId = 'ops-decision-quick-add-'.$schedule['repair_order_shop_number'].'-schedule';
                        @endphp
                        <details id="{{ $scheduleQuickAddId }}" data-decision-quick-add="schedule">
                            <summary class="btn btn-sm btn-ghost-secondary">Schedule</summary>
                            <div class="card card-sm mt-2" data-decision-quick-add-owner="{{ $scheduleQuickAddId }}">
                                <div class="card-body">
                                    <div class="d-flex align-items-center gap-2 mb-2">
                                        <div class="small fw-medium text-truncate">
                                            Schedule · {{ $row['customer_name'] }} · RO #{{ $schedule['repair_order_shop_number'] }}
                                        </div>
                                        <button type="button" class="btn-close ms-auto" data-work-item-quick-add-cancel aria-label="Cancel schedule"></button>
                                    </div>
                                    <form method="POST" action="{{ $schedule['store_url'] }}" data-decision-schedule-form>
                                        @csrf
                                        <input type="hidden" name="repair_order_shop_number" value="{{ $schedule['repair_order_shop_number'] }}">
                                        <div class="mb-2">
                                            <label class="form-label small mb-1" for="{{ $scheduleQuickAddId }}-date">Check back on</label>
                                            <input id="{{ $scheduleQuickAddId }}-date" type="date" name="scheduled_for" required min="{{ now()->toDateString() }}" class="form-control form-control-sm">
                                        </div>
                                        <div class="mb-2">
                                            <label class="form-label small mb-1" for="{{ $scheduleQuickAddId }}-notes">Why wait? (optional)</label>
                                            <input id="{{ $scheduleQuickAddId }}-notes" type="text" name="notes" maxlength="500" placeholder="Payday, spouse approval, out of town…" class="form-control form-control-sm">
                                        </div>
                                        @if (filled($schedule['customer_name'] ?? null))
                                            <input type="hidden" name="schedule_customer" value="0">
                                            <label class="form-check mb-2">
                                                <input type="checkbox" name="schedule_customer" value="1" class="form-check-input">
                                                <span class="form-check-label small">
                                                    <span class="d-block fw-medium">Every open decision for {{ $schedule['customer_name'] }}</span>
                                                    <span class="d-block text-secondary">Same follow-up day for all their waiting estimates. Off = only RO #{{ $schedule['repair_order_shop_number'] }}.</span>
                                                </span>
                                            </label>
                                        @else
                                            <p class="small text-secondary mb-2">Snoozes only RO #{{ $schedule['repair_order_shop_number'] }}.</p>
                                        @endif
                                        <p class="small text-secondary mb-2">Leaves Work until the day before - then returns as a reminder.</p>
                                        <div class="btn-list justify-content-end">
                                            <button type="button" class="btn btn-sm" data-work-item-quick-add-cancel>Cancel</button>
                                            <button type="submit" class="btn btn-sm btn-primary">Save schedule</button>
                                        </div>
                                    </form>
                                </div>
                            </div>
                        </details>
                    @endif
                @endif
            </div>
            <div data-decision-row-panel-slot></div>
        </div>
    </div>
    @endif
@endforeach
