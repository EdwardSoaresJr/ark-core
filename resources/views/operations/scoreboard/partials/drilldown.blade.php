<section class="card mt-3" data-scoreboard-drilldown>
    <div class="card-header">
        <div>
            <h3 class="card-title">{{ $drilldown['title'] }}</h3>
            @if ($drilldown['note'])
                <div class="card-subtitle">{{ $drilldown['note'] }}</div>
            @endif
        </div>
        <div class="card-actions">
            <a href="{{ route('operations.owner.scoreboard', ['period' => $periodKey]) }}" class="btn btn-sm btn-ghost-secondary">Close</a>
        </div>
    </div>
    @if ($drilldown['rows'] === [])
        <div class="card-body text-secondary">Nothing in this list.</div>
    @else
        <div class="list-group list-group-flush">
            @foreach ($drilldown['rows'] as $row)
                <div class="list-group-item">
                    <div class="d-flex flex-wrap align-items-baseline justify-content-between gap-2">
                        @if ($row['url'])
                            <a href="{{ $row['url'] }}" class="fw-bold">{{ $row['primary'] }}</a>
                        @else
                            <span class="fw-bold">{{ $row['primary'] }}</span>
                        @endif
                        <span class="small text-secondary">{{ $row['meta'] }}</span>
                    </div>
                    <div>{{ $row['secondary'] }}</div>
                </div>
            @endforeach
        </div>
    @endif
</section>
