@php
    /** @var array<string, mixed> $library */
    $filters = $library['filters'];
    $counts = $library['counts'];
    $rows = $library['rows'];
    $paginator = $library['paginator'];
    $activeFilterCount = count(array_filter([
        $filters['filter'] !== 'all',
        $filters['handled'] !== 'all',
        filled($filters['from']),
        filled($filters['to']),
    ]));
@endphp

<x-operations.app title="Calls & Voicemail" ui="tabler">
    <x-tabler.page
        layout="fluid"
        title="Calls & Voicemail"
        title-id="ops-call-library"
        :title-count="$paginator->total()"
        subtitle="Inbound and outbound phone calls - listen to voicemails and recordings here."
        actions-label="Calls commands"
    >
        <x-slot:actions>
            <x-tabler.page-action :href="\App\Ark\Operations\Communications\CommunicationsNeedsYou::url()" icon="message" label="Comms" />
            <x-tabler.page-action :href="route('operations.index')" icon="layout-grid" label="Work" />
        </x-slot:actions>

        <div data-call-library>
            @include('operations.communications.workspace.partials.section-nav', ['section' => 'calls'])

            <div class="card mb-3">
                <div class="card-body p-2 p-md-3">
                    <form method="get" action="{{ route('operations.communications.calls') }}" role="search" aria-label="Filter calls">
                        <div class="row g-2 align-items-end">
                            <div class="col-12 d-md-none order-3">
                                <button
                                    type="button"
                                    class="btn btn-sm btn-ghost-secondary"
                                    data-bs-toggle="collapse"
                                    data-bs-target="#calls-filters"
                                    aria-controls="calls-filters"
                                    aria-expanded="{{ $activeFilterCount > 0 ? 'true' : 'false' }}"
                                >
                                    <x-tabler.icon name="adjustments-horizontal" />
                                    Filters
                                    @if ($activeFilterCount > 0)
                                        <span class="badge bg-primary text-primary-fg ms-2">{{ $activeFilterCount }}</span>
                                    @endif
                                </button>
                            </div>
                            <div @class(['col-12 col-xl collapse d-md-block order-4 order-md-1', 'show' => $activeFilterCount > 0]) id="calls-filters">
                                <div class="row g-2">
                                    <div class="col-6 col-md-3">
                                        <label class="form-label" for="calls-filter-show">Show</label>
                                        <select id="calls-filter-show" name="filter" class="form-select">
                                            <option value="all" @selected($filters['filter'] === 'all')>All calls</option>
                                            <option value="voicemail" @selected($filters['filter'] === 'voicemail')>With voicemail ({{ $counts['voicemail'] }})</option>
                                            <option value="recording" @selected($filters['filter'] === 'recording')>With recording ({{ $counts['recording'] }})</option>
                                            <option value="missed" @selected($filters['filter'] === 'missed')>Missed / no answer</option>
                                        </select>
                                    </div>
                                    <div class="col-6 col-md-3">
                                        <label class="form-label" for="calls-filter-handled">Handled</label>
                                        <select id="calls-filter-handled" name="handled" class="form-select">
                                            <option value="all" @selected($filters['handled'] === 'all')>Any</option>
                                            <option value="unhandled" @selected($filters['handled'] === 'unhandled')>Needs review</option>
                                            <option value="handled" @selected($filters['handled'] === 'handled')>Handled</option>
                                        </select>
                                    </div>
                                    <div class="col-6 col-md-3">
                                        <label class="form-label" for="calls-filter-from">From</label>
                                        <input id="calls-filter-from" type="date" name="from" value="{{ $filters['from'] }}" class="form-control">
                                    </div>
                                    <div class="col-6 col-md-3">
                                        <label class="form-label" for="calls-filter-to">To</label>
                                        <input id="calls-filter-to" type="date" name="to" value="{{ $filters['to'] }}" class="form-control">
                                    </div>
                                </div>
                            </div>
                            <div class="col col-xl-3 order-1 order-md-2">
                                <label class="form-label d-none d-md-block" for="calls-filter-q">Search</label>
                                <div class="input-icon">
                                    <span class="input-icon-addon"><x-tabler.icon name="search" /></span>
                                    <input
                                        id="calls-filter-q"
                                        type="search"
                                        name="q"
                                        value="{{ $filters['q'] }}"
                                        placeholder="Phone or customer name"
                                        aria-label="Search calls"
                                        class="form-control"
                                    >
                                </div>
                            </div>
                            <div class="col-auto order-2 order-md-3">
                                <button type="submit" class="btn btn-primary">Apply</button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>

            @if ($rows === [])
                <x-tabler.empty-state icon="phone">No calls match these filters.</x-tabler.empty-state>
            @else
                <div class="card">
                    <div class="list-group list-group-flush" role="list">
                        @foreach ($rows as $row)
                            @php
                                $voicemailState = $row['voicemail_state'] ?? (! empty($row['has_voicemail']) ? 'playable' : 'none');
                                $recordingState = $row['recording_state'] ?? (! empty($row['has_recording']) ? 'playable' : 'none');
                            @endphp
                            <article class="list-group-item" role="listitem" data-call-row>
                                <div class="row g-2 align-items-center">
                                    <div class="col-12 col-lg-4">
                                        <div class="fw-bold">{{ $row['headline'] }}</div>
                                        <div class="small text-secondary">
                                            {{ $row['phone'] }}
                                            · {{ $row['direction'] }}
                                            · {{ $row['status'] }}
                                            · {{ $row['started_label'] }}
                                            <span class="text-nowrap">({{ $row['age_label'] }})</span>
                                        </div>
                                        @if (filled($row['owner']))
                                            <div class="small text-secondary">Owned by {{ $row['owner'] }}</div>
                                        @endif
                                    </div>

                                    <div class="col-12 col-lg d-flex flex-column gap-2">
                                        @if ($voicemailState === 'playable' && filled($row['voicemail_url'] ?? null))
                                            @include('operations.communications.calls.player', [
                                                'src' => $row['voicemail_url'],
                                                'seconds' => $row['voicemail_duration'] ?? 0,
                                                'kind' => 'Voicemail',
                                            ])
                                        @elseif ($voicemailState === 'unavailable')
                                            <span class="small text-secondary fst-italic">Voicemail unavailable</span>
                                        @endif
                                        @if ($recordingState === 'playable' && filled($row['recording_url'] ?? null))
                                            @include('operations.communications.calls.player', [
                                                'src' => $row['recording_url'],
                                                'seconds' => $row['recording_duration'] ?? 0,
                                                'kind' => 'Recording',
                                            ])
                                        @elseif ($recordingState === 'unavailable')
                                            <span class="small text-secondary fst-italic">Recording unavailable</span>
                                        @endif
                                        @if ($voicemailState === 'none' && $recordingState === 'none')
                                            <span class="small text-secondary fst-italic">No recording</span>
                                        @endif
                                    </div>

                                    <div class="col-12 col-lg-auto">
                                        <div class="btn-list justify-content-lg-end">
                                            @if ($row['customer_url'])
                                                <a href="{{ $row['customer_url'] }}" class="btn btn-sm">Customer</a>
                                            @endif
                                            @if ($row['text_url'] ?? null)
                                                <a href="{{ $row['text_url'] }}" class="btn btn-sm">Text</a>
                                            @endif
                                            @if ($row['repair_order_url'])
                                                <a href="{{ $row['repair_order_url'] }}" class="btn btn-sm">RO</a>
                                            @endif
                                            @if ($row['lookup_url'])
                                                <a href="{{ $row['lookup_url'] }}" class="btn btn-sm">Lookup</a>
                                            @endif
                                            @if (! $row['handled'])
                                                <form method="post" action="{{ $row['mark_handled_url'] }}" class="d-inline-flex">
                                                    @csrf
                                                    <button type="submit" class="btn btn-sm btn-ghost-secondary">Handled</button>
                                                </form>
                                            @else
                                                <span class="badge bg-green-lt">Handled</span>
                                            @endif
                                        </div>
                                    </div>
                                </div>
                            </article>
                        @endforeach
                    </div>
                    @if ($paginator->hasPages())
                        <div class="card-footer">
                            {{ $paginator->withQueryString()->links('pagination::bootstrap-5') }}
                        </div>
                    @endif
                </div>
            @endif
        </div>

        <script>
            (function () {
                const root = document.querySelector('[data-call-library]');
                if (!root) {
                    return;
                }

                const clock = (total) => {
                    const seconds = Math.max(0, Math.round(Number(total) || 0));
                    const hours = Math.floor(seconds / 3600);
                    const minutes = Math.floor((seconds % 3600) / 60);
                    const remain = seconds % 60;
                    const pad = (value) => String(value).padStart(2, '0');

                    if (hours > 0) {
                        return hours + ':' + pad(minutes) + ':' + pad(remain);
                    }

                    return minutes + ':' + pad(remain);
                };

                const lengthOf = (audio) => {
                    const stored = Number(audio.dataset.duration);
                    if (Number.isFinite(audio.duration) && audio.duration > 0 && audio.duration !== Infinity) {
                        return audio.duration;
                    }

                    return Number.isFinite(stored) ? stored : 0;
                };

                const paint = (player) => {
                    const audio = player.querySelector('audio');
                    const readout = player.querySelector('[data-clock]');
                    const scrub = player.querySelector('[data-scrub]');
                    const length = lengthOf(audio);
                    const position = audio.currentTime || 0;
                    readout.textContent = clock(position) + ' / ' + clock(length);
                    if (!scrub || document.activeElement === scrub) {
                        return;
                    }
                    scrub.max = String(length > 0 ? length : 0);
                    scrub.value = String(Math.min(position, length > 0 ? length : 0));
                };

                root.addEventListener('click', (event) => {
                    const button = event.target.closest('[data-play]');
                    if (!button || !root.contains(button)) {
                        return;
                    }
                    const player = button.closest('[data-call-player]');
                    const audio = player.querySelector('audio');
                    root.querySelectorAll('audio').forEach((other) => {
                        if (other === audio) {
                            return;
                        }
                        other.pause();
                        const otherButton = other.closest('[data-call-player]')?.querySelector('[data-play]');
                        if (otherButton) {
                            otherButton.textContent = 'Play';
                        }
                    });
                    if (audio.paused) {
                        audio.play().then(() => {
                            button.textContent = 'Pause';
                        }).catch(() => {
                            button.textContent = 'Play';
                        });
                        return;
                    }
                    audio.pause();
                    button.textContent = 'Play';
                });

                root.addEventListener('input', (event) => {
                    const scrub = event.target.closest('[data-scrub]');
                    if (!scrub || !root.contains(scrub)) {
                        return;
                    }
                    const player = scrub.closest('[data-call-player]');
                    const audio = player.querySelector('audio');
                    const length = lengthOf(audio);
                    const next = Math.min(Number(scrub.value) || 0, length > 0 ? length : 0);
                    if (length > 0) {
                        audio.currentTime = next;
                    }
                    player.querySelector('[data-clock]').textContent = clock(next) + ' / ' + clock(length);
                });

                const onMedia = (event) => {
                    const audio = event.target;
                    if (!(audio instanceof HTMLAudioElement) || !root.contains(audio)) {
                        return;
                    }
                    const player = audio.closest('[data-call-player]');
                    if (!player) {
                        return;
                    }
                    if (event.type === 'ended' || event.type === 'pause') {
                        const button = player.querySelector('[data-play]');
                        if (button && audio.paused) {
                            button.textContent = 'Play';
                        }
                    }
                    paint(player);
                };

                root.addEventListener('timeupdate', onMedia, true);
                root.addEventListener('loadedmetadata', onMedia, true);
                root.addEventListener('ended', onMedia, true);
                root.addEventListener('pause', onMedia, true);
            })();
        </script>
    </x-tabler.page>
</x-operations.app>
