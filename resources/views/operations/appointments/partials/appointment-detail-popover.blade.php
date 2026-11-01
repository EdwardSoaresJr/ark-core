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

{{-- Shown by .is-open on the event and placed by arkScheduleAppointmentPopover through --ark-cal-pop-* on the board. --}}
<div class="card card-sm shadow ark-cal-popover" data-schedule-popover @click.stop role="dialog" aria-label="Appointment details">
    <div class="card-body">
        <div class="subheader">{{ $popoverTitle }}</div>
        <div class="fw-bold">{{ $card['customer_name'] }}</div>
        @if (filled($card['vehicle_label'] ?? null))
            <div class="small">{{ $card['vehicle_label'] }}</div>
        @endif
        @if (filled($card['concern'] ?? null))
            <div class="small mt-1">{{ $card['concern'] }}</div>
        @endif
        <div class="small text-secondary mt-1">
            {{ $card['time_label'] }}–{{ $card['ends_label'] }}
            · {{ $card['status_label'] }}
        </div>
        @if ($hasStaffLine)
            <div class="small text-secondary">
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
            </div>
        @endif

        <div class="d-flex flex-wrap gap-2 mt-2">
            @if (filled($card['repair_order_url'] ?? null))
                <a href="{{ $card['repair_order_url'] }}" class="btn btn-sm btn-primary">Open RO</a>
            @endif
            <a href="{{ $card['show_url'] }}?edit=1" class="btn btn-sm">Edit appointment</a>
            @if (! empty($card['call_url']))
                <a href="{{ $card['call_url'] }}" class="btn btn-sm">Call</a>
            @endif
            @if (! empty($card['text_url']))
                <a href="{{ $card['text_url'] }}" class="btn btn-sm">Text</a>
            @endif
        </div>

        @if ($showAssignment)
            <form
                method="POST"
                action="{{ route('operations.appointments.assign', $appointmentId) }}"
                class="border-top mt-3 pt-2"
                data-schedule-assign
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

                <div class="subheader mb-2">Assignment</div>

                <label class="form-label small mb-1" for="schedule-assign-tech-{{ $appointmentId }}">Technician</label>
                <select id="schedule-assign-tech-{{ $appointmentId }}" name="technician_user_id" class="form-select form-select-sm mb-2">
                    <option value="">Unassigned</option>
                    @foreach ($assignOptions['technicians'] as $technician)
                        <option
                            value="{{ $technician['id'] }}"
                            @selected((int) ($card['technician_user_id'] ?? 0) === (int) $technician['id'])
                        >{{ $technician['name'] }}</option>
                    @endforeach
                </select>

                <label class="form-label small mb-1" for="schedule-assign-bay-{{ $appointmentId }}">Bay</label>
                <select id="schedule-assign-bay-{{ $appointmentId }}" name="workstation_id" class="form-select form-select-sm mb-2">
                    <option value="">Unassigned</option>
                    @foreach ($assignOptions['workstations'] as $bay)
                        <option
                            value="{{ $bay['id'] }}"
                            @selected((int) ($card['workstation_id'] ?? 0) === (int) $bay['id'])
                        >{{ $bay['label'] }}</option>
                    @endforeach
                </select>

                <button type="submit" class="btn btn-sm btn-primary">Save assignment</button>
            </form>
        @endif
    </div>
</div>
