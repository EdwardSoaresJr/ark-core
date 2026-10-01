@php
    /** @var array<string, mixed> $card */
    $showAssignment = ! empty($showAssignment);
    $assignOptions = $assignOptions ?? ['technicians' => [], 'workstations' => []];
    $assignContext = $assignContext ?? [];
    $appointmentId = (int) ($card['id'] ?? 0);
    $roNumber = $card['repair_order_number'] ?? null;
    $popoverTitle = filled($roNumber) ? 'RO #'.$roNumber : 'No RO yet';
    $hasStaffLine = filled($card['advisor_label'] ?? null)
        || filled($card['technician_label'] ?? null)
        || filled($card['workstation_label'] ?? null);
@endphp

<div
    class="ops-cal-month__popover"
    :class="{ 'ops-cal-month__popover--above': openAbove && openAppointmentId === {{ $appointmentId }} }"
    @click.stop
    role="dialog"
    aria-label="Appointment details"
>
    <p class="ops-cal-month__popover-ro">{{ $popoverTitle }}</p>
    <p class="ops-cal-month__popover-name">{{ $card['customer_name'] }}</p>
    @if (filled($card['vehicle_label'] ?? null))
        <p class="ops-cal-month__popover-line">{{ $card['vehicle_label'] }}</p>
    @endif
    @if (filled($card['concern'] ?? null))
        <p class="ops-cal-month__popover-concern">{{ $card['concern'] }}</p>
    @endif
    <p class="ops-cal-month__popover-line">
        {{ $card['time_label'] }}–{{ $card['ends_label'] }}
        · {{ $card['status_label'] }}
    </p>
    @if ($hasStaffLine)
        <p class="ops-cal-month__popover-line">
            @if (filled($card['advisor_label'] ?? null))
                Advisor {{ $card['advisor_label'] }}
            @endif
            @if (filled($card['advisor_label'] ?? null) && filled($card['technician_label'] ?? null))
                ·
            @endif
            @if (filled($card['technician_label'] ?? null))
                Tech {{ $card['technician_label'] }}
            @endif
            @if (filled($card['workstation_label'] ?? null))
                @if (filled($card['advisor_label'] ?? null) || filled($card['technician_label'] ?? null))
                    ·
                @endif
                Bay {{ $card['workstation_label'] }}
            @endif
        </p>
    @endif

    <div class="ops-cal-month__popover-actions">
        @if (filled($card['repair_order_url'] ?? null))
            <a href="{{ $card['repair_order_url'] }}" class="ops-cal-month__popover-action ops-cal-month__popover-action--primary">
                Open RO
            </a>
        @endif
        <a href="{{ $card['show_url'] }}?edit=1" class="ops-cal-month__popover-action">
            Edit appointment
        </a>
        @if (! empty($card['call_url']))
            <a href="{{ $card['call_url'] }}" class="ops-cal-month__popover-action">Call</a>
        @endif
        @if (! empty($card['text_url']))
            <a href="{{ $card['text_url'] }}" class="ops-cal-month__popover-action">Text</a>
        @endif
    </div>

    @if ($showAssignment)
        <form
            method="POST"
            action="{{ route('operations.appointments.assign', $appointmentId) }}"
            class="ops-cal-appt-assign"
        >
            @csrf
            @method('PATCH')
            <input type="hidden" name="day" value="{{ $assignContext['day'] ?? '' }}">
            <input type="hidden" name="view" value="{{ $assignContext['view'] ?? 'week' }}">
            @if (filled($assignContext['allocate'] ?? null))
                <input type="hidden" name="allocate" value="{{ $assignContext['allocate'] }}">
            @endif
            @if (filled($assignContext['lens'] ?? null) && ($assignContext['lens'] ?? 'agenda') !== 'agenda')
                <input type="hidden" name="lens" value="{{ $assignContext['lens'] }}">
            @endif

            <p class="ops-cal-appt-assign__title">Assignment</p>

            <label class="ops-cal-appt-assign__field">
                <span>Technician</span>
                <select name="technician_user_id">
                    <option value="">Unassigned</option>
                    @foreach ($assignOptions['technicians'] as $technician)
                        <option
                            value="{{ $technician['id'] }}"
                            @selected((int) ($card['technician_user_id'] ?? 0) === (int) $technician['id'])
                        >{{ $technician['name'] }}</option>
                    @endforeach
                </select>
            </label>

            <label class="ops-cal-appt-assign__field">
                <span>Bay</span>
                <select name="workstation_id">
                    <option value="">Unassigned</option>
                    @foreach ($assignOptions['workstations'] as $bay)
                        <option
                            value="{{ $bay['id'] }}"
                            @selected((int) ($card['workstation_id'] ?? 0) === (int) $bay['id'])
                        >{{ $bay['label'] }}</option>
                    @endforeach
                </select>
            </label>

            <div class="ops-cal-month__popover-actions">
                <button type="submit" class="ops-cal-month__popover-action ops-cal-month__popover-action--primary">
                    Save assignment
                </button>
            </div>
        </form>
    @endif
</div>
