@php
    use App\Ark\Operations\Settings\ShopDisplayTimezone;

    $p = $projection;
@endphp

<x-operations.app :title="'Time Clock · '.$technician->name">
    <section class="mx-auto max-w-4xl space-y-4 px-3 py-4 sm:px-4" x-data="{ panelId: null, panelMode: null }">
        <div class="flex flex-wrap items-start justify-between gap-3 border-b border-slate-200 pb-3">
            <div>
                <p class="text-[11px] font-bold uppercase tracking-[0.08em] text-slate-400">Staff time clock</p>
                <h1 class="text-xl font-black text-slate-950">{{ $technician->name }}</h1>
                <p class="mt-1 text-sm font-semibold text-slate-700">{{ $p['status_label'] }}</p>
                <a href="{{ route('operations.time-clock.index') }}" class="mt-2 inline-block text-xs font-bold text-slate-700 underline">← Time clock</a>
            </div>
            <div class="flex flex-wrap items-center gap-2">
                @if ($canPunchForStaff)
                    @if ($p['is_clocked_in'])
                        <form method="POST" action="{{ route('operations.time-clock.staff.out', $technician) }}">
                            @csrf
                            <input type="hidden" name="lunch" value="1" />
                            <button type="submit" class="ops-index-btn ops-index-btn--ghost bg-amber-500 text-white hover:bg-amber-600">Out for Lunch</button>
                        </form>
                        <form method="POST" action="{{ route('operations.time-clock.staff.out', $technician) }}">
                            @csrf
                            <button type="submit" class="ops-index-btn ops-index-btn--primary">Clock Out</button>
                        </form>
                    @else
                        <form method="POST" action="{{ route('operations.time-clock.staff.in', $technician) }}">
                            @csrf
                            <button type="submit" class="ops-index-btn ops-index-btn--primary bg-emerald-700 hover:bg-emerald-800">
                                {{ $p['awaiting_lunch_return'] ? 'Back from Lunch' : 'Clock In' }}
                            </button>
                        </form>
                    @endif
                @endif
                @if ($canManage)
                    <a href="{{ route('operations.owner.technician-production.show', ['user' => $technician]) }}" class="ops-index-btn ops-index-btn--ghost">
                        Tech production
                    </a>
                @endif
            </div>
        </div>

        @if (session('status'))
            <p class="border border-emerald-200 bg-emerald-50 px-3 py-2 text-sm font-semibold text-emerald-900">{{ session('status') }}</p>
        @endif

        @if ($errors->has('time_clock'))
            <p class="border border-red-300 bg-red-50 px-3 py-2 text-sm font-semibold text-red-900">{{ $errors->first('time_clock') }}</p>
        @endif

        @if ($canManage)
            <div class="border border-slate-300 bg-white px-3 py-3">
                <p class="text-[11px] font-bold uppercase tracking-[0.08em] text-slate-500">Auto clock</p>
                <p class="mt-1 text-xs text-slate-500">Assign this staff member's day to Business Hours instead of requiring a punch. Materializes at open, closes at close. Lunch punches still win.</p>
                <form method="POST" action="{{ route('operations.time-clock.staff.auto-clock', $technician) }}" class="mt-3 flex flex-wrap items-end gap-3">
                    @csrf
                    <label class="flex items-center gap-2 text-sm font-semibold text-slate-800">
                        <input type="checkbox" name="auto_clock_enabled" value="1" {{ $p['auto_clock_enabled'] ? 'checked' : '' }} />
                        Auto clock enabled
                    </label>
                    <label class="block text-xs font-semibold text-slate-700">
                        Auto lunch (minutes)
                        <input
                            type="number"
                            name="auto_lunch_minutes"
                            min="0"
                            max="240"
                            value="{{ $p['auto_lunch_minutes'] }}"
                            class="mt-1 w-28 border border-slate-300 px-2 py-1.5 text-sm"
                            placeholder="0"
                        />
                    </label>
                    <button type="submit" class="ops-index-btn ops-index-btn--primary text-xs">Save auto clock</button>
                </form>
            </div>
        @endif

        <div class="border border-slate-300 bg-white">
            <div class="border-b border-slate-200 bg-slate-50 px-3 py-2">
                <p class="text-[11px] font-bold uppercase tracking-[0.08em] text-slate-500">Punch history</p>
            </div>
            @if ($sessions->isEmpty())
                <p class="px-3 py-4 text-sm text-slate-600">No punches recorded.</p>
            @else
                <ul class="divide-y divide-slate-200">
                    @foreach ($sessions as $session)
                        @php
                            $isDeleted = $session->isDeleted();
                            $inBy = $session->clockedInBy;
                            $outBy = $session->clockedOutBy;
                            $proxyIn = $inBy && (int) $inBy->id !== (int) $session->user_id;
                            $proxyOut = $outBy && (int) $outBy->id !== (int) $session->user_id;
                        @endphp
                        <li class="px-3 py-3 {{ $isDeleted ? 'opacity-60' : '' }}">
                            <div class="flex flex-wrap items-start justify-between gap-2">
                                <div>
                                    <p class="text-sm font-bold text-slate-900 {{ $isDeleted ? 'line-through' : '' }}">
                                        {{ ShopDisplayTimezone::format($session->clocked_in_at) }}
                                        →
                                        {{ $session->clocked_out_at ? ShopDisplayTimezone::format($session->clocked_out_at) : 'Open' }}
                                    </p>
                                    <p class="text-xs text-slate-500">
                                        {{ $session->statusEnum()->label() }} · {{ number_format($session->elapsedSeconds() / 3600, 2) }} hr
                                        @if ($session->closeReasonEnum())
                                            · {{ $session->closeReasonEnum()->label() }}
                                        @endif
                                        @if ($session->originEnum() === \App\Ark\Operations\Labor\TechnicianTimeSessionOrigin::Auto)
                                            · Auto
                                        @endif
                                        @if ($proxyIn)
                                            · in by {{ $inBy->name }}
                                        @endif
                                        @if ($proxyOut)
                                            · out by {{ $outBy->name }}
                                        @endif
                                    </p>
                                </div>
                                @if ($canManage && ! $isDeleted)
                                    <div class="flex flex-wrap gap-2">
                                        <button
                                            type="button"
                                            class="ops-index-btn ops-index-btn--ghost text-xs"
                                            @click="panelId === {{ $session->id }} && panelMode === 'correct' ? (panelId = null, panelMode = null) : (panelId = {{ $session->id }}, panelMode = 'correct')"
                                        >
                                            Correct
                                        </button>
                                        <button
                                            type="button"
                                            class="ops-index-btn ops-index-btn--ghost text-xs text-red-800"
                                            @click="panelId === {{ $session->id }} && panelMode === 'delete' ? (panelId = null, panelMode = null) : (panelId = {{ $session->id }}, panelMode = 'delete')"
                                        >
                                            Delete
                                        </button>
                                    </div>
                                @endif
                            </div>

                            @if ($canManage && ! $isDeleted)
                                <form
                                    x-show="panelId === {{ $session->id }} && panelMode === 'correct'"
                                    x-cloak
                                    method="POST"
                                    action="{{ route('operations.time-clock.correct', $session) }}"
                                    class="mt-3 grid gap-2 border border-slate-200 bg-slate-50 p-3 sm:grid-cols-2"
                                >
                                    @csrf
                                    <label class="block text-xs font-semibold text-slate-700">
                                        Clocked in
                                        <input
                                            type="datetime-local"
                                            name="clocked_in_at"
                                            value="{{ ShopDisplayTimezone::present($session->clocked_in_at)->format('Y-m-d\TH:i') }}"
                                            class="mt-1 w-full border border-slate-300 px-2 py-1.5 text-sm"
                                        />
                                    </label>
                                    <label class="block text-xs font-semibold text-slate-700">
                                        Clocked out
                                        <input
                                            type="datetime-local"
                                            name="clocked_out_at"
                                            value="{{ $session->clocked_out_at ? ShopDisplayTimezone::present($session->clocked_out_at)->format('Y-m-d\TH:i') : '' }}"
                                            class="mt-1 w-full border border-slate-300 px-2 py-1.5 text-sm"
                                        />
                                    </label>
                                    <label class="block text-xs font-semibold text-slate-700 sm:col-span-2">
                                        Reason (required)
                                        <input
                                            type="text"
                                            name="reason"
                                            required
                                            maxlength="500"
                                            class="mt-1 w-full border border-slate-300 px-2 py-1.5 text-sm"
                                            placeholder="Why is this punch being corrected?"
                                        />
                                    </label>
                                    <div class="sm:col-span-2">
                                        <button type="submit" class="ops-index-btn ops-index-btn--primary">Save correction</button>
                                    </div>
                                </form>

                                <form
                                    x-show="panelId === {{ $session->id }} && panelMode === 'delete'"
                                    x-cloak
                                    method="POST"
                                    action="{{ route('operations.time-clock.delete', $session) }}"
                                    class="mt-3 grid gap-2 border border-red-200 bg-red-50 p-3"
                                    onsubmit="return confirm('Delete this punch? It will no longer count toward compensable hours.')"
                                >
                                    @csrf
                                    <p class="text-xs text-red-900">Removes this punch from compensable hours. The row stays for audit.</p>
                                    <label class="block text-xs font-semibold text-slate-700">
                                        Reason (required)
                                        <input
                                            type="text"
                                            name="reason"
                                            required
                                            maxlength="500"
                                            class="mt-1 w-full border border-slate-300 bg-white px-2 py-1.5 text-sm"
                                            placeholder="Why is this punch being deleted?"
                                        />
                                    </label>
                                    <div>
                                        <button type="submit" class="ops-index-btn ops-index-btn--primary bg-red-800 hover:bg-red-900">Delete punch</button>
                                    </div>
                                </form>
                            @endif
                        </li>
                    @endforeach
                </ul>
            @endif
        </div>
    </section>
</x-operations.app>
