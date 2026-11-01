<x-operations.app title="Day Review" ui="tabler">
    <x-tabler.page layout="fluid" title="Day Review" pretitle="Owner">
        @if ($targetReviewStale)
            <div class="alert alert-warning mb-3" data-day-review-target-stale>
                @if ($lastTargetReview)
                    Last target review: {{ $lastTargetReview }} - overdue for quarterly refresh.
                @else
                    Shop excellence targets have not been reviewed yet.
                @endif
                <a href="{{ route('operations.settings.shop.edit', ['section' => 'excellence']) }}" class="alert-link ms-1">Update owner targets</a>
            </div>
        @endif

        @include('operations.reports.partials.end-of-day-report', ['eod' => $eod])

        <section class="card mb-3" data-day-review-queue>
            <div class="card-header">
                <div>
                    <h3 class="card-title">Tomorrow's queue pressure</h3>
                    <div class="card-subtitle">name the first move before you leave. Numbers above; unfinished work below.</div>
                </div>
            </div>
            <ul class="list-group list-group-flush">
                @forelse ($priorities as $priority)
                    <li class="list-group-item d-flex flex-wrap align-items-baseline justify-content-between gap-2">
                        <div>
                            <div class="fw-bold">{{ $priority['label'] }}</div>
                            <div class="small text-secondary mt-1">{{ $priority['hint'] }}</div>
                        </div>
                        <span @class([
                            'badge ark-money',
                            'bg-yellow-lt' => $priority['tone'] !== 'calm',
                            'bg-secondary-lt' => $priority['tone'] === 'calm',
                        ])>{{ $priority['count'] }}</span>
                    </li>
                @empty
                    <li class="list-group-item text-secondary">No queue pressure flagged - shop flow is clear.</li>
                @endforelse
            </ul>
        </section>

        @if (($nudgeInsight['acted'] ?? 0) + ($nudgeInsight['dismissed'] ?? 0) > 0)
            <details class="card mb-3" data-day-review-nudges>
                <summary class="card-body py-2 fw-bold">Comms nudge measurement · 7 days</summary>
                <div class="card-body border-top p-2 p-md-3">
                    <dl class="row g-3 mb-0">
                        <div class="col-4">
                            <dt class="subheader">Acted</dt>
                            <dd class="h3 mb-0 text-green ark-money">{{ $nudgeInsight['acted'] }}</dd>
                        </div>
                        <div class="col-4">
                            <dt class="subheader">Dismissed</dt>
                            <dd class="h3 mb-0 ark-money">{{ $nudgeInsight['dismissed'] }}</dd>
                        </div>
                        <div class="col-4">
                            <dt class="subheader">Action rate</dt>
                            <dd class="h3 mb-0 ark-money">{{ $nudgeInsight['action_rate'] !== null ? $nudgeInsight['action_rate'].'%' : '-' }}</dd>
                        </div>
                    </dl>
                </div>
            </details>
        @endif

        <div class="btn-list" data-day-review-links>
            <a href="{{ route('operations.reports.index') }}" class="btn">All reports</a>
            <a href="{{ $eod->reportUrl }}" class="btn">Operational Report</a>
            <a href="{{ route('operations.workboard') }}" class="btn">Job Board</a>
            <a href="{{ route('operations.learn.show', ['role' => 'owner', 'article' => 'bookend-walkthrough']) }}" class="btn">Day Review guide</a>
        </div>
    </x-tabler.page>
</x-operations.app>
