@php
    $activeFilterCount = ($filters['channel'] !== 'all' ? 1 : 0)
        + ($filters['media'] !== 'recorded' ? 1 : 0)
        + ($filters['analysis'] !== '' ? 1 : 0);
@endphp

<x-operations.app title="Call & SMS Intelligence" ui="tabler">
    <x-tabler.page
        layout="fluid"
        title="Call & SMS intelligence"
        pretitle="Owner"
        subtitle="Recorded calls and SMS threads with AI summary, empathy scoring, missed-upsell flags, and advisor coaching. Owner-only - not advisor workflow."
    >
        @if (session('status'))
            <div class="alert alert-success mb-3">{{ session('status') }}</div>
        @endif

        @unless ($analysisEnabled)
            <div class="alert alert-warning mb-3" data-call-intelligence-disabled>
                Call and SMS summaries are not part of this shop installation. Recorded calls and messages stay on the repair order.
            </div>
        @endunless

        <div class="card mb-3">
            <div class="card-body p-2 p-md-3">
                <form method="GET" action="{{ route('operations.owner.call-intelligence') }}" class="row g-2 align-items-end" data-call-intelligence-filters>
                    <div class="col-6 col-md-4 col-lg-2">
                        <label class="form-label" for="call-intelligence-from">From</label>
                        <input id="call-intelligence-from" type="date" name="from" value="{{ $filters['from'] }}" class="form-control">
                    </div>
                    <div class="col-6 col-md-4 col-lg-2">
                        <label class="form-label" for="call-intelligence-to">To</label>
                        <input id="call-intelligence-to" type="date" name="to" value="{{ $filters['to'] }}" class="form-control">
                    </div>
                    <div class="col-12 d-md-none">
                        <button
                            type="button"
                            class="btn btn-sm btn-ghost-secondary"
                            data-bs-toggle="collapse"
                            data-bs-target="#call-intelligence-more-filters"
                            aria-controls="call-intelligence-more-filters"
                            aria-expanded="{{ $activeFilterCount > 0 ? 'true' : 'false' }}"
                        >
                            <x-tabler.icon name="adjustments-horizontal" />
                            Filters
                            @if ($activeFilterCount > 0)
                                <span class="badge bg-primary text-primary-fg ms-2">{{ $activeFilterCount }}</span>
                            @endif
                        </button>
                    </div>
                    <div @class(['col-12 col-lg collapse d-md-block', 'show' => $activeFilterCount > 0]) id="call-intelligence-more-filters">
                        <div class="row g-2">
                            <div class="col-12 col-md-4">
                                <label class="form-label" for="call-intelligence-channel">Channel</label>
                                <select id="call-intelligence-channel" name="channel" class="form-select">
                                    <option value="all" @selected($filters['channel'] === 'all')>Calls & SMS</option>
                                    <option value="calls" @selected($filters['channel'] === 'calls')>Calls only</option>
                                    <option value="sms" @selected($filters['channel'] === 'sms')>SMS only</option>
                                </select>
                            </div>
                            <div class="col-12 col-md-4">
                                <label class="form-label" for="call-intelligence-media">Media</label>
                                <select id="call-intelligence-media" name="media" class="form-select">
                                    <option value="" @selected($filters['media'] === '')>All calls</option>
                                    <option value="recorded" @selected($filters['media'] === 'recorded')>Recorded / voicemail</option>
                                </select>
                            </div>
                            <div class="col-12 col-md-4">
                                <label class="form-label" for="call-intelligence-analysis">Analysis</label>
                                <select id="call-intelligence-analysis" name="analysis" class="form-select">
                                    <option value="" @selected($filters['analysis'] === '')>Any status</option>
                                    <option value="ready" @selected($filters['analysis'] === 'ready')>Analysis ready</option>
                                    <option value="follow_up" @selected($filters['analysis'] === 'follow_up')>Follow-up flagged</option>
                                    <option value="missed_upsell" @selected($filters['analysis'] === 'missed_upsell')>Missed upsell</option>
                                    <option value="coaching" @selected($filters['analysis'] === 'coaching')>Needs coaching</option>
                                    <option value="pinned" @selected($filters['analysis'] === 'pinned')>Pinned follow-up</option>
                                </select>
                            </div>
                        </div>
                    </div>
                    <div class="col-12 col-md-auto">
                        <button type="submit" class="btn btn-primary w-100">Apply</button>
                    </div>
                </form>
            </div>
        </div>

        @include('operations.owner.partials.call-intelligence-coaching-queue', [
            'coachingRows' => $coachingRows,
            'filters' => $filters,
        ])

        <div data-call-intelligence-rows>
            @forelse ($rows as $row)
                <article class="card mb-3" data-call-intelligence-row="{{ $row['kind'] ?? 'call' }}">
                    <div class="card-body p-2 p-md-3">
                        <div class="row g-2 align-items-start">
                            <div class="col-12 col-md" style="min-width: 0">
                                <h3 class="card-title mb-0">
                                    @if (($row['kind'] ?? 'call') === 'sms')
                                        <span class="badge bg-azure-lt me-1">SMS</span>
                                    @endif
                                    {{ $row['display_phone'] }}
                                    @if ($row['customer_name'])
                                        <span class="fw-normal text-secondary">· {{ $row['customer_name'] }}</span>
                                    @endif
                                </h3>
                                <div class="small text-secondary mt-1">
                                    {{ $row['started_at_label'] }}
                                    · {{ $row['direction_label'] }}
                                    · {{ $row['status_label'] }}
                                    · {{ $row['duration_label'] }}
                                    @if ($row['staff_name'])
                                        · {{ $row['staff_name'] }}
                                    @endif
                                    @if ($row['repair_order_number'])
                                        · <a href="{{ $row['repair_order_url'] }}">RO #{{ $row['repair_order_number'] }}</a>
                                    @endif
                                </div>
                            </div>
                            <div class="col-12 col-md-auto">
                                <div class="btn-list align-items-center">
                                    @if ($row['recording_url'])
                                        <audio controls preload="none" class="mw-100" style="height: 2rem; width: 14rem">
                                            <source src="{{ $row['recording_url'] }}" type="audio/mpeg">
                                        </audio>
                                    @endif
                                    @include('operations.owner.partials.call-intelligence-follow-up-pin', ['row' => $row])
                                    @if ($row['coaching_pdf_url'])
                                        <a href="{{ $row['coaching_pdf_url'] }}" target="_blank" rel="noopener noreferrer" class="btn btn-sm">Handout</a>
                                    @endif
                                    <a href="{{ $row['show_url'] }}?{{ http_build_query($filters) }}" class="btn btn-sm btn-primary">{{ $row['open_label'] ?? 'Open call' }}</a>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="card-body border-top p-2 p-md-3">
                        @include('operations.owner.partials.call-intelligence-call-details', ['row' => $row, 'compact' => true])
                    </div>

                    <div class="card-body border-top p-2 p-md-3">
                        @if ($row['summary'])
                            <p class="mb-0">{{ $row['summary'] }}</p>
                        @elseif ($row['analysis_error'])
                            <p class="small fw-medium text-danger mb-0">{{ $row['analysis_error'] }}</p>
                        @else
                            <p class="small text-secondary mb-0">{{ $row['analysis_status_label'] }}@if ($row['analysis_status'] === 'pending' || $row['analysis_status'] === 'processing') - check back shortly @endif</p>
                        @endif

                        @if (! empty($row['topics']))
                            <div class="d-flex flex-wrap gap-1 mt-2">
                                @foreach (array_slice($row['topics'], 0, 4) as $topic)
                                    <span class="badge bg-secondary-lt">{{ $topic }}</span>
                                @endforeach
                                @if (count($row['topics']) > 4)
                                    <span class="badge badge-outline text-secondary">+{{ count($row['topics']) - 4 }} more</span>
                                @endif
                            </div>
                        @endif
                    </div>
                </article>
            @empty
                <div class="card mb-3">
                    <div class="card-body text-secondary">No calls match this range.</div>
                </div>
            @endforelse
        </div>

        @if ($paginator->hasPages())
            <div class="mb-3">
                {{ $paginator->links('pagination::bootstrap-5') }}
            </div>
        @endif

        <div class="btn-list" data-call-intelligence-links>
            <a href="{{ route('operations.owner.day-review') }}" class="btn">Day Review</a>
            <a href="{{ route('operations.reports.operational') }}" class="btn">Operational report</a>
        </div>
    </x-tabler.page>
</x-operations.app>
