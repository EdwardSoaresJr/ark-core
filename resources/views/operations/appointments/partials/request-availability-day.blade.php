@php
    /** @var array{requestable: bool, weekly_enabled: bool, exception: \App\Ark\Operations\Appointments\AppointmentRequestException|null} $requestDayStatus */
    $exception = $requestDayStatus['exception'] ?? null;
    $requestable = (bool) ($requestDayStatus['requestable'] ?? false);
    $weeklyEnabled = (bool) ($requestDayStatus['weekly_enabled'] ?? false);
    $focusDate = $w['focus_date'] ?? null;
    $lens = ($w['lens'] ?? 'agenda') !== 'agenda' ? ($w['lens'] ?? null) : null;
    $boardView = filled($w['view'] ?? null) ? ($w['view'] ?? null) : null;
@endphp

@if (filled($focusDate))
    <div class="card card-sm" data-schedule-request-availability>
        <div class="card-body d-flex flex-wrap align-items-start justify-content-between gap-3">
            <div class="min-w-0">
                <div class="subheader">Appointment requests</div>
                <div class="fw-medium">
                    @if ($requestable)
                        Accepting public requests this day
                    @else
                        Not accepting public requests this day
                    @endif
                </div>
                <div class="small text-secondary">
                    Controls appointment request availability. Does not change Business Hours or staff scheduling.
                    @if ($exception)
                        · Override: {{ $exception->mode }}@if (filled($exception->reason)) ({{ $exception->reason }})@endif
                    @elseif (! $weeklyEnabled)
                        · Weekly default: off
                    @else
                        · Weekly default: on
                    @endif
                </div>
            </div>

            <div class="d-flex flex-wrap align-items-end gap-2">
                @if ($requestable)
                    <form method="POST" action="{{ route('operations.appointments.request-availability') }}" class="d-flex flex-wrap align-items-end gap-2">
                        @csrf
                        <input type="hidden" name="date" value="{{ $focusDate }}">
                        <input type="hidden" name="mode" value="disable">
                        @if ($lens)
                            <input type="hidden" name="lens" value="{{ $lens }}">
                        @endif
                        @if ($boardView)
                            <input type="hidden" name="view" value="{{ $boardView }}">
                        @endif
                        <div>
                            <label class="form-label small mb-1" for="schedule-request-reason">Reason (optional)</label>
                            <input
                                id="schedule-request-reason"
                                type="text"
                                name="reason"
                                maxlength="255"
                                placeholder="Fully booked, short staffed…"
                                class="form-control form-control-sm"
                            >
                        </div>
                        <button type="submit" class="btn btn-sm btn-warning">Disable appointment requests</button>
                    </form>
                @else
                    <form method="POST" action="{{ route('operations.appointments.request-availability') }}">
                        @csrf
                        <input type="hidden" name="date" value="{{ $focusDate }}">
                        <input type="hidden" name="mode" value="enable">
                        @if ($lens)
                            <input type="hidden" name="lens" value="{{ $lens }}">
                        @endif
                        @if ($boardView)
                            <input type="hidden" name="view" value="{{ $boardView }}">
                        @endif
                        <button type="submit" class="btn btn-sm btn-success">Accept appointment requests this day</button>
                    </form>
                @endif

                @if ($exception)
                    <form method="POST" action="{{ route('operations.appointments.request-availability') }}">
                        @csrf
                        <input type="hidden" name="date" value="{{ $focusDate }}">
                        <input type="hidden" name="mode" value="clear">
                        @if ($lens)
                            <input type="hidden" name="lens" value="{{ $lens }}">
                        @endif
                        @if ($boardView)
                            <input type="hidden" name="view" value="{{ $boardView }}">
                        @endif
                        <button type="submit" class="btn btn-sm">Clear override</button>
                    </form>
                @endif
            </div>
        </div>
    </div>
@endif
