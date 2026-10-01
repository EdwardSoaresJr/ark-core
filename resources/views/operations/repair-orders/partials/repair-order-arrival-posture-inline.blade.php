@php
    /** @var \App\Ark\Operations\Appointments\ArrivalPosture|null $arrivalPosture */
    $arrivalPosture = $arrivalPosture ?? null;
    $variant = $variant ?? 'band';
@endphp

@if ($arrivalPosture?->present)
    @if ($variant === 'toolbar')
        @php
            $arrivalTitle = collect([
                $arrivalPosture->headline,
                $arrivalPosture->whenLabel,
                $arrivalPosture->subtitle,
            ])->filter()->implode(' · ');
        @endphp
        <div
            class="ops-visit-signal ops-visit-signal--appointment"
            data-arrival-posture="{{ $arrivalPosture->posture }}"
            title="{{ $arrivalTitle }}"
        >
            <span class="ops-visit-signal__label">Appt</span>
            <span class="ops-visit-signal__value">
                <span class="ops-visit-signal__mark" aria-hidden="true">✓</span>
                @if ($arrivalPosture->appointmentUrl)
                    <a href="{{ $arrivalPosture->appointmentUrl }}" class="ops-visit-signal__link">{{ $arrivalPosture->headline }}</a>
                @else
                    {{ $arrivalPosture->headline }}
                @endif
            </span>
            @if (filled($arrivalPosture->whenLabel))
                <span class="ops-visit-signal__meta">{{ $arrivalPosture->whenLabel }}</span>
            @endif
        </div>
    @else
        <div class="ops-arrival-posture mt-1.5">
            <p class="text-[10px] font-bold uppercase tracking-[0.08em] text-slate-500">Appointment</p>
            <p class="mt-0.5 text-[13px] font-extrabold leading-4 tracking-tight text-slate-950">
                @if ($arrivalPosture->appointmentUrl)
                    <a href="{{ $arrivalPosture->appointmentUrl }}" class="text-slate-950 underline decoration-slate-300 underline-offset-2 hover:text-slate-700">{{ $arrivalPosture->headline }}</a>
                @else
                    {{ $arrivalPosture->headline }}
                @endif
            </p>
            @if (filled($arrivalPosture->whenLabel))
                <p class="mt-0.5 text-[11px] font-semibold leading-4 text-slate-700">{{ $arrivalPosture->whenLabel }}</p>
            @endif
            @if (filled($arrivalPosture->subtitle))
                <p class="mt-0.5 text-[11px] font-semibold leading-4 text-slate-500">{{ $arrivalPosture->subtitle }}</p>
            @endif
            @if ($arrivalPosture->appointmentId && $arrivalPosture->sourceStatus?->isUpcoming())
                <div class="mt-1.5 flex flex-wrap gap-1">
                    <form method="POST" action="{{ route('operations.appointments.status', $arrivalPosture->appointmentId) }}">
                        @csrf
                        @method('PATCH')
                        <input type="hidden" name="status" value="arrived">
                        <input type="hidden" name="redirect" value="{{ url()->current() }}">
                        <button class="h-7 rounded-sm border border-slate-300 bg-white px-2 text-[11px] font-semibold">Mark arrived</button>
                    </form>
                    <form method="POST" action="{{ route('operations.appointments.status', $arrivalPosture->appointmentId) }}">
                        @csrf
                        @method('PATCH')
                        <input type="hidden" name="status" value="no_show">
                        <input type="hidden" name="redirect" value="{{ url()->current() }}">
                        <button class="h-7 rounded-sm border border-slate-300 bg-white px-2 text-[11px] font-semibold">No show</button>
                    </form>
                    <form method="POST" action="{{ route('operations.appointments.status', $arrivalPosture->appointmentId) }}">
                        @csrf
                        @method('PATCH')
                        <input type="hidden" name="status" value="canceled">
                        <input type="hidden" name="redirect" value="{{ url()->current() }}">
                        <button class="h-7 rounded-sm border border-slate-300 bg-white px-2 text-[11px] font-semibold">Cancel appointment</button>
                    </form>
                </div>
            @endif
        </div>
    @endif
@elseif ($variant !== 'toolbar' && isset($repairOrder) && \App\Ark\Operations\OperationsFeatures::appointmentsEnabled())
    <form method="POST" action="{{ route('operations.repair-orders.appointments.store', $repairOrder) }}" class="ops-arrival-posture mt-2 space-y-1">
        @csrf
        <p class="text-[10px] font-bold uppercase tracking-[0.08em] text-slate-500">Schedule visit</p>
        <div class="flex flex-wrap items-end gap-1.5">
            <label class="text-[11px] font-semibold text-slate-600">
                When
                <input type="datetime-local" name="starts_at" required class="mt-0.5 block h-8 rounded-sm border border-slate-300 px-2 text-xs">
            </label>
            <label class="text-[11px] font-semibold text-slate-600">
                Minutes
                <input type="number" name="duration_minutes" value="60" min="15" max="480" class="mt-0.5 block h-8 w-16 rounded-sm border border-slate-300 px-2 text-xs">
            </label>
            <button type="submit" class="h-8 rounded-sm border border-slate-800 bg-slate-900 px-2 text-[11px] font-semibold text-white">Save appointment</button>
        </div>
        <p class="text-[10px] text-slate-500">Does not change repair status.</p>
    </form>
@endif
