@include('operations.work.partials.advisor-work-items-section', [
    'groups' => $tasks,
    'variant' => 'task',
    'empty' => 'No shop tasks scheduled.',
])

@if ($appointments_enabled ?? false)
    <div class="border-t border-slate-200">
        <div class="ops-schedule-section-header">
            <div>
                <p class="ops-schedule-bucket-label !px-0 !pt-0">Schedule</p>
                <p class="mt-0.5 text-[11px] leading-4 text-slate-500">Next seven days of appointments.</p>
            </div>
            <div class="flex shrink-0 flex-wrap items-center gap-2">
                <a href="{{ route('operations.appointments.index') }}" class="ops-page-link">Full schedule</a>
                <a href="{{ route('operations.appointments.create') }}" class="ops-page-link">Schedule</a>
            </div>
        </div>

        @include('operations.appointments.partials.schedule-list', [
            'groups' => $schedule,
        ])
    </div>
@endif
