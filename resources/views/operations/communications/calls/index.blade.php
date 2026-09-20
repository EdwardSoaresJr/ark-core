@php
    /** @var array<string, mixed> $library */
    $filters = $library['filters'];
    $counts = $library['counts'];
    $rows = $library['rows'];
    $paginator = $library['paginator'];
@endphp

<x-operations.app title="Calls & Voicemail">
    <style>
        .ops-call-library__filters { margin: 0 0 1rem; padding: 0 1rem; }
        .ops-call-library__filter-row { display: flex; flex-wrap: wrap; gap: 0.75rem; align-items: flex-end; }
        .ops-call-library__filter { display: flex; flex-direction: column; gap: 0.25rem; min-width: 8rem; }
        .ops-call-library__filter--grow { flex: 1; min-width: 12rem; }
        .ops-call-library__filter-label { font-size: 0.65rem; font-weight: 700; letter-spacing: 0.04em; text-transform: uppercase; color: rgb(100 116 139); }
        .ops-call-library__list { display: flex; flex-direction: column; gap: 0.5rem; padding: 0 1rem 1rem; }
        .ops-call-library__row {
            display: grid;
            grid-template-columns: minmax(12rem, 1.2fr) minmax(16rem, 1.5fr) auto;
            gap: 1rem;
            align-items: center;
            padding: 0.75rem 1rem;
            border: 1px solid rgb(226 232 240);
            border-radius: 0.375rem;
            background: #fff;
        }
        .ops-call-library__headline { font-weight: 600; color: rgb(15 23 42); margin: 0; }
        .ops-call-library__meta { font-size: 0.8125rem; color: rgb(71 85 105); margin: 0.25rem 0 0; }
        .ops-call-library__age { color: rgb(148 163 184); }
        .ops-call-library__owner { font-size: 0.75rem; color: rgb(100 116 139); margin: 0.25rem 0 0; }
        .ops-call-library__media { display: flex; flex-direction: column; gap: 0.5rem; min-width: 0; }
        .ops-call-library__audio-block { display: flex; flex-direction: column; gap: 0.25rem; }
        .ops-call-library__audio-label { font-size: 0.65rem; font-weight: 700; text-transform: uppercase; color: rgb(100 116 139); }
        .ops-call-library__audio { width: 100%; max-width: 320px; height: 2rem; }
        .ops-call-library__no-media { font-size: 0.8125rem; color: rgb(148 163 184); font-style: italic; }
        .ops-call-library__actions { display: flex; flex-wrap: wrap; gap: 0.5rem; justify-content: flex-end; }
        .ops-call-library__empty { padding: 2rem 1rem; color: rgb(100 116 139); }
        @media (max-width: 900px) {
            .ops-call-library__row { grid-template-columns: 1fr; }
            .ops-call-library__actions { justify-content: flex-start; }
        }
    </style>
    <section class="ops-comms-workspace ops-call-library">
        <x-operations.queue-page-header
            id="ops-call-library"
            title="Calls & Voicemail"
            description="Inbound and outbound phone calls — listen to voicemails and recordings here."
            :count="$paginator->total() > 0 ? $paginator->total() : null"
            :show-back="false"
        >
            <x-slot:actions>
                <a href="{{ \App\Ark\Operations\Communications\CommunicationsNeedsYou::url() }}" class="ops-page-link">Comms</a>
                <a href="{{ route('operations.index') }}" class="ops-page-link">Work</a>
            </x-slot:actions>
        </x-operations.queue-page-header>

        @include('operations.communications.workspace.partials.section-nav', ['section' => 'calls'])

        <form method="get" action="{{ route('operations.communications.calls') }}" class="ops-call-library__filters">
            <div class="ops-call-library__filter-row">
                <label class="ops-call-library__filter">
                    <span class="ops-call-library__filter-label">Show</span>
                    <select name="filter" class="ops-input ops-input--dense">
                        <option value="all" @selected($filters['filter'] === 'all')>All calls</option>
                        <option value="voicemail" @selected($filters['filter'] === 'voicemail')>With voicemail ({{ $counts['voicemail'] }})</option>
                        <option value="recording" @selected($filters['filter'] === 'recording')>With recording ({{ $counts['recording'] }})</option>
                        <option value="missed" @selected($filters['filter'] === 'missed')>Missed / no answer</option>
                    </select>
                </label>
                <label class="ops-call-library__filter">
                    <span class="ops-call-library__filter-label">Handled</span>
                    <select name="handled" class="ops-input ops-input--dense">
                        <option value="all" @selected($filters['handled'] === 'all')>Any</option>
                        <option value="unhandled" @selected($filters['handled'] === 'unhandled')>Needs review</option>
                        <option value="handled" @selected($filters['handled'] === 'handled')>Handled</option>
                    </select>
                </label>
                <label class="ops-call-library__filter">
                    <span class="ops-call-library__filter-label">From</span>
                    <input type="date" name="from" value="{{ $filters['from'] }}" class="ops-input ops-input--dense">
                </label>
                <label class="ops-call-library__filter">
                    <span class="ops-call-library__filter-label">To</span>
                    <input type="date" name="to" value="{{ $filters['to'] }}" class="ops-input ops-input--dense">
                </label>
                <label class="ops-call-library__filter ops-call-library__filter--grow">
                    <span class="ops-call-library__filter-label">Search</span>
                    <input type="search" name="q" value="{{ $filters['q'] }}" placeholder="Phone or customer name" class="ops-input ops-input--dense">
                </label>
                <button type="submit" class="ops-btn ops-btn--secondary ops-btn--dense">Apply</button>
            </div>
        </form>

        @if ($rows === [])
            <p class="ops-call-library__empty">No calls match these filters.</p>
        @else
            <div class="ops-call-library__list" role="list">
                @foreach ($rows as $row)
                    <article class="ops-call-library__row" role="listitem">
                        <div class="ops-call-library__identity">
                            <p class="ops-call-library__headline">{{ $row['headline'] }}</p>
                            <p class="ops-call-library__meta">
                                {{ $row['phone'] }}
                                · {{ $row['direction'] }}
                                · {{ $row['status'] }}
                                · {{ $row['started_label'] }}
                                <span class="ops-call-library__age">({{ $row['age_label'] }})</span>
                            </p>
                            @if (filled($row['owner']))
                                <p class="ops-call-library__owner">Owned by {{ $row['owner'] }}</p>
                            @endif
                        </div>

                        <div class="ops-call-library__media">
                            @if ($row['has_voicemail'] && filled($row['voicemail_url']))
                                <div class="ops-call-library__audio-block">
                                    <span class="ops-call-library__audio-label">Voicemail</span>
                                    <audio controls preload="none" class="ops-call-library__audio" src="{{ $row['voicemail_url'] }}"></audio>
                                </div>
                            @endif
                            @if ($row['has_recording'] && filled($row['recording_url']))
                                <div class="ops-call-library__audio-block">
                                    <span class="ops-call-library__audio-label">Recording</span>
                                    <audio controls preload="none" class="ops-call-library__audio" src="{{ $row['recording_url'] }}"></audio>
                                </div>
                            @endif
                            @if (! $row['has_voicemail'] && ! $row['has_recording'])
                                <span class="ops-call-library__no-media">No audio</span>
                            @endif
                        </div>

                        <div class="ops-call-library__actions">
                            @if ($row['customer_url'])
                                <a href="{{ $row['customer_url'] }}" class="ops-call-queue__action">Customer</a>
                            @endif
                            @if ($row['text_url'] ?? null)
                                <a href="{{ $row['text_url'] }}" class="ops-call-queue__action">Text</a>
                            @endif
                            @if ($row['repair_order_url'])
                                <a href="{{ $row['repair_order_url'] }}" class="ops-call-queue__action">RO</a>
                            @endif
                            @if ($row['lookup_url'])
                                <a href="{{ $row['lookup_url'] }}" class="ops-call-queue__action">Lookup</a>
                            @endif
                            @if (! $row['handled'])
                                <form method="post" action="{{ $row['mark_handled_url'] }}" class="inline">
                                    @csrf
                                    <button type="submit" class="ops-call-queue__action ops-call-queue__action--ghost">Handled</button>
                                </form>
                            @else
                                <span class="ops-call-queue__action ops-call-queue__action--owned">Handled</span>
                            @endif
                        </div>
                    </article>
                @endforeach
            </div>

            <div class="ops-comms-workspace__pagination">
                {{ $paginator->withQueryString()->links() }}
            </div>
        @endif
    </section>
</x-operations.app>
