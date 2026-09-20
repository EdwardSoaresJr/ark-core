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
    <div class="ops-board-shell px-3 py-2.5">
        <div class="flex flex-wrap items-start justify-between gap-3">
            <div class="min-w-0">
                <p class="text-[10px] font-bold uppercase tracking-wide text-slate-400">Appointment requests</p>
                <p class="mt-0.5 text-sm font-semibold text-slate-900">
                    @if ($requestable)
                        Accepting public requests this day
                    @else
                        Not accepting public requests this day
                    @endif
                </p>
                <p class="mt-0.5 text-xs text-slate-500">
                    Controls appointment request availability. Does not change Business Hours or staff scheduling.
                    @if ($exception)
                        · Override: {{ $exception->mode }}@if (filled($exception->reason)) ({{ $exception->reason }})@endif
                    @elseif (! $weeklyEnabled)
                        · Weekly default: off
                    @else
                        · Weekly default: on
                    @endif
                </p>
            </div>

            <div class="flex flex-wrap items-center gap-2">
                @if ($requestable)
                    <form method="POST" action="{{ route('operations.appointments.request-availability') }}" class="flex flex-wrap items-end gap-2">
                        @csrf
                        <input type="hidden" name="date" value="{{ $focusDate }}">
                        <input type="hidden" name="mode" value="disable">
                        @if ($lens)
                            <input type="hidden" name="lens" value="{{ $lens }}">
                        @endif
                        @if ($boardView)
                            <input type="hidden" name="view" value="{{ $boardView }}">
                        @endif
                        <label class="block">
                            <span class="text-[10px] font-bold uppercase tracking-wide text-slate-400">Reason (optional)</span>
                            <input
                                type="text"
                                name="reason"
                                maxlength="255"
                                placeholder="Fully booked, short staffed…"
                                class="mt-0.5 h-8 w-48 rounded-sm border border-slate-300 px-2 text-xs"
                            >
                        </label>
                        <button type="submit" class="h-8 rounded-sm border border-amber-700 bg-amber-600 px-3 text-xs font-semibold text-white hover:bg-amber-700">
                            Disable appointment requests
                        </button>
                    </form>
                @else
                    <form method="POST" action="{{ route('operations.appointments.request-availability') }}" class="flex flex-wrap items-center gap-2">
                        @csrf
                        <input type="hidden" name="date" value="{{ $focusDate }}">
                        <input type="hidden" name="mode" value="enable">
                        @if ($lens)
                            <input type="hidden" name="lens" value="{{ $lens }}">
                        @endif
                        @if ($boardView)
                            <input type="hidden" name="view" value="{{ $boardView }}">
                        @endif
                        <button type="submit" class="h-8 rounded-sm border border-emerald-800 bg-emerald-700 px-3 text-xs font-semibold text-white hover:bg-emerald-800">
                            Accept appointment requests this day
                        </button>
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
                        <button type="submit" class="h-8 rounded-sm border border-slate-300 bg-white px-3 text-xs font-semibold text-slate-700 hover:bg-slate-50">
                            Clear override
                        </button>
                    </form>
                @endif
            </div>
        </div>
    </div>
@endif
