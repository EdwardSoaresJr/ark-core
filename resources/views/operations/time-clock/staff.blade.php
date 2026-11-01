@php
    use App\Ark\Operations\Settings\ShopDisplayTimezone;

    $p = $projection;
@endphp

<x-operations.app :title="'Time Clock · '.$technician->name" ui="tabler">
    <x-tabler.page layout="focused" pretitle="Staff time clock" :title="$technician->name" stack-actions>
        <x-slot:actions>
            <x-tabler.page-action :href="route('operations.time-clock.index')" icon="arrow-left" label="Time clock" />
            @if ($canManage)
                <x-tabler.page-action :href="route('operations.owner.technician-production.show', ['user' => $technician])" icon="chart-bar" label="Tech production" />
            @endif
        </x-slot:actions>

        <div class="d-flex flex-column gap-3" x-data="{ panelId: null, panelMode: null }" data-time-clock-history>
            @if (session('status'))
                <div class="alert alert-success mb-0" role="status">{{ session('status') }}</div>
            @endif

            @if ($errors->has('time_clock'))
                <div class="alert alert-danger mb-0" role="alert">{{ $errors->first('time_clock') }}</div>
            @endif

            <div class="card" data-time-clock-staff-status>
                <div class="card-body p-3">
                    <div class="row g-3 align-items-center">
                        <div class="col-12 col-sm">
                            <div class="subheader">Status</div>
                            <div class="h3 mb-0" data-time-clock-status>{{ $p['status_label'] }}</div>
                        </div>
                        @if ($canPunchForStaff)
                            <div class="col-12 col-sm-auto">
                                <div class="btn-list">
                                    @if ($p['is_clocked_in'])
                                        <form method="POST" action="{{ route('operations.time-clock.staff.out', $technician) }}" class="flex-fill">
                                            @csrf
                                            <input type="hidden" name="lunch" value="1" />
                                            <button type="submit" class="btn btn-warning w-100">Out for Lunch</button>
                                        </form>
                                        <form method="POST" action="{{ route('operations.time-clock.staff.out', $technician) }}" class="flex-fill">
                                            @csrf
                                            <button type="submit" class="btn btn-primary w-100">Clock Out</button>
                                        </form>
                                    @else
                                        <form method="POST" action="{{ route('operations.time-clock.staff.in', $technician) }}" class="flex-fill">
                                            @csrf
                                            <button type="submit" class="btn btn-success w-100">
                                                {{ $p['awaiting_lunch_return'] ? 'Back from Lunch' : 'Clock In' }}
                                            </button>
                                        </form>
                                    @endif
                                </div>
                            </div>
                        @endif
                    </div>
                </div>
            </div>

            @if ($canManage)
                <div class="card" data-time-clock-auto-clock>
                    <div class="card-header">
                        <div>
                            <h2 class="card-title">Auto clock</h2>
                            <div class="card-subtitle">Assign this staff member's day to Business Hours instead of requiring a punch. Materializes at open, closes at close. Lunch punches still win.</div>
                        </div>
                    </div>
                    <form method="POST" action="{{ route('operations.time-clock.staff.auto-clock', $technician) }}" class="card-body p-3">
                        @csrf
                        <div class="row g-3 align-items-end">
                            <div class="col-12 col-sm">
                                <label class="form-check form-switch mb-0">
                                    <input type="checkbox" class="form-check-input" name="auto_clock_enabled" value="1" {{ $p['auto_clock_enabled'] ? 'checked' : '' }} />
                                    <span class="form-check-label">Auto clock enabled</span>
                                </label>
                            </div>
                            <div class="col-6 col-sm-auto">
                                <label class="form-label" for="time-clock-auto-lunch">Auto lunch (minutes)</label>
                                <input
                                    type="number"
                                    id="time-clock-auto-lunch"
                                    name="auto_lunch_minutes"
                                    min="0"
                                    max="240"
                                    value="{{ $p['auto_lunch_minutes'] }}"
                                    class="form-control"
                                    style="width: 8rem"
                                    placeholder="0"
                                />
                            </div>
                            <div class="col-6 col-sm-auto">
                                <button type="submit" class="btn btn-primary w-100">Save auto clock</button>
                            </div>
                        </div>
                    </form>
                </div>
            @endif

            <div class="card" data-time-clock-punch-history>
                <div class="card-header">
                    <h2 class="card-title">Punch history</h2>
                </div>
                @if ($sessions->isEmpty())
                    <div class="card-body text-secondary">No punches recorded.</div>
                @else
                    <div class="list-group list-group-flush">
                        @foreach ($sessions as $session)
                            @php
                                $isDeleted = $session->isDeleted();
                                $inBy = $session->clockedInBy;
                                $outBy = $session->clockedOutBy;
                                $proxyIn = $inBy && (int) $inBy->id !== (int) $session->user_id;
                                $proxyOut = $outBy && (int) $outBy->id !== (int) $session->user_id;
                            @endphp
                            <div @class(['list-group-item', 'opacity-50' => $isDeleted]) data-time-clock-session="{{ $session->id }}">
                                <div class="d-flex flex-wrap align-items-start justify-content-between gap-2">
                                    <div style="min-width: 0">
                                        <div @class(['fw-bold', 'text-decoration-line-through' => $isDeleted]) style="font-variant-numeric: tabular-nums">
                                            {{ ShopDisplayTimezone::format($session->clocked_in_at) }}
                                            →
                                            {{ $session->clocked_out_at ? ShopDisplayTimezone::format($session->clocked_out_at) : 'Open' }}
                                        </div>
                                        <div class="small text-secondary">
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
                                        </div>
                                    </div>
                                    @if ($canManage && ! $isDeleted)
                                        <div class="btn-list">
                                            <button
                                                type="button"
                                                class="btn btn-sm"
                                                aria-controls="time-clock-correct-{{ $session->id }}"
                                                :aria-expanded="panelId === {{ $session->id }} && panelMode === 'correct' ? 'true' : 'false'"
                                                @click="panelId === {{ $session->id }} && panelMode === 'correct' ? (panelId = null, panelMode = null) : (panelId = {{ $session->id }}, panelMode = 'correct')"
                                            >
                                                Correct
                                            </button>
                                            <button
                                                type="button"
                                                class="btn btn-sm btn-ghost-danger"
                                                aria-controls="time-clock-delete-{{ $session->id }}"
                                                :aria-expanded="panelId === {{ $session->id }} && panelMode === 'delete' ? 'true' : 'false'"
                                                @click="panelId === {{ $session->id }} && panelMode === 'delete' ? (panelId = null, panelMode = null) : (panelId = {{ $session->id }}, panelMode = 'delete')"
                                            >
                                                Delete
                                            </button>
                                        </div>
                                    @endif
                                </div>

                                @if ($canManage && ! $isDeleted)
                                    <form
                                        id="time-clock-correct-{{ $session->id }}"
                                        x-show="panelId === {{ $session->id }} && panelMode === 'correct'"
                                        x-cloak
                                        method="POST"
                                        action="{{ route('operations.time-clock.correct', $session) }}"
                                        class="card card-sm mt-3"
                                        data-time-clock-correct-form
                                    >
                                        @csrf
                                        <div class="card-body">
                                            <div class="row g-2">
                                                <div class="col-12 col-sm-6">
                                                    <label class="form-label" for="time-clock-correct-{{ $session->id }}-in">Clocked in</label>
                                                    <input
                                                        type="datetime-local"
                                                        id="time-clock-correct-{{ $session->id }}-in"
                                                        name="clocked_in_at"
                                                        value="{{ ShopDisplayTimezone::present($session->clocked_in_at)->format('Y-m-d\TH:i') }}"
                                                        class="form-control"
                                                    />
                                                </div>
                                                <div class="col-12 col-sm-6">
                                                    <label class="form-label" for="time-clock-correct-{{ $session->id }}-out">Clocked out</label>
                                                    <input
                                                        type="datetime-local"
                                                        id="time-clock-correct-{{ $session->id }}-out"
                                                        name="clocked_out_at"
                                                        value="{{ $session->clocked_out_at ? ShopDisplayTimezone::present($session->clocked_out_at)->format('Y-m-d\TH:i') : '' }}"
                                                        class="form-control"
                                                    />
                                                </div>
                                                <div class="col-12">
                                                    <label class="form-label" for="time-clock-correct-{{ $session->id }}-reason">Reason (required)</label>
                                                    <input
                                                        type="text"
                                                        id="time-clock-correct-{{ $session->id }}-reason"
                                                        name="reason"
                                                        required
                                                        maxlength="500"
                                                        class="form-control"
                                                        placeholder="Why is this punch being corrected?"
                                                    />
                                                </div>
                                                <div class="col-12">
                                                    <button type="submit" class="btn btn-primary">Save correction</button>
                                                </div>
                                            </div>
                                        </div>
                                    </form>

                                    <form
                                        id="time-clock-delete-{{ $session->id }}"
                                        x-show="panelId === {{ $session->id }} && panelMode === 'delete'"
                                        x-cloak
                                        method="POST"
                                        action="{{ route('operations.time-clock.delete', $session) }}"
                                        class="card card-sm border-danger mt-3"
                                        onsubmit="return confirm('Delete this punch? It will no longer count toward compensable hours.')"
                                        data-time-clock-delete-form
                                    >
                                        @csrf
                                        <div class="card-body">
                                            <p class="text-danger mb-2">Removes this punch from compensable hours. The row stays for audit.</p>
                                            <label class="form-label" for="time-clock-delete-{{ $session->id }}-reason">Reason (required)</label>
                                            <input
                                                type="text"
                                                id="time-clock-delete-{{ $session->id }}-reason"
                                                name="reason"
                                                required
                                                maxlength="500"
                                                class="form-control"
                                                placeholder="Why is this punch being deleted?"
                                            />
                                            <button type="submit" class="btn btn-danger mt-2">Delete punch</button>
                                        </div>
                                    </form>
                                @endif
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>
        </div>
    </x-tabler.page>
</x-operations.app>
