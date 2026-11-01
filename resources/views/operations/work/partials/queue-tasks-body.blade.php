@include('operations.work.partials.advisor-work-items-section', [
    'groups' => $tasks,
    'variant' => 'task',
    'empty' => 'No shop tasks scheduled.',
])

@if ($appointments_enabled ?? false)
    <div class="card-body border-top d-flex flex-wrap align-items-center gap-2" data-work-schedule>
        <div class="me-auto">
            <div class="subheader">Schedule</div>
            <div class="small text-secondary">Next seven days of appointments.</div>
        </div>
        <div class="btn-list">
            <a href="{{ route('operations.appointments.index') }}" class="btn btn-sm">Full schedule</a>
            <a href="{{ route('operations.appointments.create') }}" class="btn btn-sm">Schedule</a>
        </div>
    </div>
    @include('operations.appointments.partials.schedule-list', [
        'groups' => $schedule,
    ])
@endif
