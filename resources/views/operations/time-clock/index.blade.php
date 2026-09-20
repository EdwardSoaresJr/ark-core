@php
    use App\Ark\Operations\Labor\TechnicianTimeClockProjection;
    use App\Ark\Operations\Settings\ShopDisplayTimezone;

    $p = $projection;
    $showStaffList = ($canManage || $canPunchForStaff) && $staffTechnicians->isNotEmpty();
@endphp

<x-operations.app title="Time Clock">
    <section class="mx-auto max-w-3xl space-y-4 px-3 py-4 sm:px-4">
        <div class="border-b border-slate-200 pb-3">
            <p class="text-[11px] font-bold uppercase tracking-[0.08em] text-slate-400">Time clock</p>
            <h1 class="text-2xl font-black text-slate-950">{{ $p['technician']->name ?? 'Staff punches' }}</h1>
            @if ($p)
                <p class="mt-1 text-sm font-semibold text-slate-700">{{ $p['status_label'] }}</p>
            @else
                <p class="mt-1 text-sm text-slate-600">Punch staff in and out when work starts and ends. Hours feed compensable time — not a paycheck.</p>
            @endif
        </div>

        @if (session('status'))
            <p class="border border-emerald-200 bg-emerald-50 px-3 py-2 text-sm font-semibold text-emerald-900">{{ session('status') }}</p>
        @endif

        @if ($errors->has('time_clock'))
            <p class="border border-red-300 bg-red-50 px-3 py-2 text-sm font-semibold text-red-900">{{ $errors->first('time_clock') }}</p>
        @endif

        @if (($canManage || $canPunchForStaff) && $needsResolution->isNotEmpty())
            <div class="border-2 border-amber-500 bg-amber-50 px-3 py-3 text-amber-950">
                <p class="text-sm font-black uppercase tracking-[0.06em]">Needs resolution</p>
                <p class="mt-1 text-sm">Open punches crossed shop midnight. Clock out or ask an admin to correct before totals are trusted.</p>
                <ul class="mt-2 space-y-1 text-sm">
                    @foreach ($needsResolution as $session)
                        <li>
                            <a href="{{ route('operations.time-clock.staff', $session->technician ?? $session->user_id) }}" class="font-bold underline">
                                {{ $session->technician?->name ?? 'Staff' }}
                            </a>
                            · in {{ ShopDisplayTimezone::format($session->clocked_in_at) }}
                        </li>
                    @endforeach
                </ul>
            </div>
        @endif

        @if ($p)
            @php
                $elapsedHours = number_format($p['today_compensable_hours'], 2);
                $elapsedLabel = sprintf('%d:%02d', intdiv($p['today_elapsed_seconds'], 3600), intdiv($p['today_elapsed_seconds'] % 3600, 60));
            @endphp
            <div class="grid gap-3 sm:grid-cols-2">
                <div class="border border-slate-300 bg-white px-4 py-4 text-center">
                    <p class="text-[10px] font-bold uppercase tracking-[0.08em] text-slate-500">Today elapsed</p>
                    <p class="mt-1 text-4xl font-black tabular-nums text-slate-950">{{ $elapsedLabel }}</p>
                    <p class="mt-1 text-xs text-slate-500">{{ $elapsedHours }} compensable hr</p>
                </div>
                <div class="flex flex-col justify-center gap-2">
                    @if ($p['is_clocked_in'])
                        <form method="POST" action="{{ route('operations.time-clock.out') }}">
                            @csrf
                            <input type="hidden" name="lunch" value="1" />
                            <button type="submit" class="w-full border-2 border-amber-600 bg-amber-500 px-4 py-3 text-base font-black uppercase tracking-[0.06em] text-white hover:bg-amber-600">
                                Out for Lunch
                            </button>
                        </form>
                        <form method="POST" action="{{ route('operations.time-clock.out') }}">
                            @csrf
                            <button type="submit" class="w-full border-2 border-slate-900 bg-slate-900 px-4 py-4 text-xl font-black uppercase tracking-[0.06em] text-white hover:bg-slate-800">
                                Clock Out
                            </button>
                        </form>
                    @else
                        <form method="POST" action="{{ route('operations.time-clock.in') }}">
                            @csrf
                            <button type="submit" class="w-full border-2 border-emerald-700 bg-emerald-600 px-4 py-5 text-xl font-black uppercase tracking-[0.06em] text-white hover:bg-emerald-700">
                                {{ $p['awaiting_lunch_return'] ? 'Back from Lunch' : 'Clock In' }}
                            </button>
                        </form>
                    @endif
                </div>
            </div>

            <div class="border border-slate-300 bg-white">
                <div class="border-b border-slate-200 bg-slate-50 px-3 py-2">
                    <p class="text-[11px] font-bold uppercase tracking-[0.08em] text-slate-500">Today punches</p>
                </div>
                @if ($p['today_punches']->isEmpty())
                    <p class="px-3 py-4 text-sm text-slate-600">No punches yet today.</p>
                @else
                    <ul class="divide-y divide-slate-200">
                        @foreach ($p['today_punches'] as $session)
                            <li class="flex flex-wrap items-center justify-between gap-2 px-3 py-2.5 text-sm">
                                <div>
                                    <p class="font-bold text-slate-900">
                                        {{ ShopDisplayTimezone::format($session->clocked_in_at, 'g:i A') }}
                                        →
                                        {{ $session->clocked_out_at ? ShopDisplayTimezone::format($session->clocked_out_at, 'g:i A') : 'open' }}
                                    </p>
                                    <p class="text-xs text-slate-500">
                                        {{ $session->statusEnum()->label() }}
                                        @if ($session->closeReasonEnum())
                                            · {{ $session->closeReasonEnum()->label() }}
                                        @endif
                                    </p>
                                </div>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </div>
        @endif

        @if ($showStaffList)
            <div class="border border-slate-200 bg-slate-50 px-3 py-3">
                <p class="text-[11px] font-bold uppercase tracking-[0.08em] text-slate-500">Staff punches</p>
                <ul class="mt-2 space-y-2">
                    @foreach ($staffTechnicians as $technician)
                        @php
                            $staffProjection = TechnicianTimeClockProjection::forTechnician($technician);
                        @endphp
                        <li class="flex flex-wrap items-center justify-between gap-2 border border-slate-200 bg-white px-3 py-2">
                            <div>
                                <p class="text-sm font-semibold text-slate-900">{{ $technician->name }}</p>
                                <p class="text-xs text-slate-500">{{ $staffProjection['status_label'] }}</p>
                            </div>
                            <div class="flex flex-wrap items-center gap-2">
                                @if ($canPunchForStaff)
                                    @if ($staffProjection['is_clocked_in'])
                                        <form method="POST" action="{{ route('operations.time-clock.staff.out', $technician) }}">
                                            @csrf
                                            <input type="hidden" name="lunch" value="1" />
                                            <button type="submit" class="ops-index-btn ops-index-btn--ghost text-xs bg-amber-500 text-white hover:bg-amber-600">Out for Lunch</button>
                                        </form>
                                        <form method="POST" action="{{ route('operations.time-clock.staff.out', $technician) }}">
                                            @csrf
                                            <button type="submit" class="ops-index-btn ops-index-btn--primary text-xs">Clock Out</button>
                                        </form>
                                    @else
                                        <form method="POST" action="{{ route('operations.time-clock.staff.in', $technician) }}">
                                            @csrf
                                            <button type="submit" class="ops-index-btn ops-index-btn--primary text-xs bg-emerald-700 hover:bg-emerald-800">
                                                {{ $staffProjection['awaiting_lunch_return'] ? 'Back from Lunch' : 'Clock In' }}
                                            </button>
                                        </form>
                                    @endif
                                @endif
                                <a href="{{ route('operations.time-clock.staff', $technician) }}" class="text-xs font-bold text-slate-700 underline">History</a>
                            </div>
                        </li>
                    @endforeach
                </ul>
            </div>
        @endif
    </section>
</x-operations.app>
