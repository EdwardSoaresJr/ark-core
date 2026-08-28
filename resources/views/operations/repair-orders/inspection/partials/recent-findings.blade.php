<section class="ops-inspection-recent" aria-labelledby="recent-findings-heading">
    <div class="ops-inspection-recent__head">
        <h2 id="recent-findings-heading" class="ops-inspection-recent__title">Recent findings</h2>
        @if ($has_recorded_findings)
            <span class="ops-inspection-recent__count">{{ $total_recorded_finding_count ?? count($recent_finding_rows) }}</span>
        @endif
    </div>

    @if ($has_recorded_findings)
        <div class="ops-inspection-recent__list">
            @foreach ($recent_finding_rows as $row)
                @include('operations.repair-orders.inspection.partials.finding-card', [
                    'finding' => $row['finding'],
                    'item' => $row['item'],
                ])
            @endforeach
        </div>
        @if ($has_older_findings ?? false)
            <p class="ops-inspection-recent__more">
                <a href="#browse-findings" class="ops-inspection-recent__more-link">Browse older findings by category</a>
            </p>
        @endif
    @else
        <p class="ops-inspection-recent__empty">
            No findings yet.
            @if ($canEdit)
                Tap <strong>+ Finding</strong> when you see something worth recording.
            @endif
        </p>
    @endif
</section>
