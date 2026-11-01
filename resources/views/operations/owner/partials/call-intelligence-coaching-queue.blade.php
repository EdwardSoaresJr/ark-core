@if (count($coachingRows) > 0)
    <section class="card mb-3" data-call-intelligence-coaching-queue>
        <div class="card-status-top bg-yellow"></div>
        <div class="card-header">
            <div>
                <h3 class="card-title">Most needed coaching</h3>
                <div class="card-subtitle">Pinned follow-ups stay at top. Remaining slots ranked by coaching priority, empathy gaps, and missed upsells.</div>
            </div>
            <div class="card-actions">
                <a href="{{ route('operations.owner.call-intelligence', array_merge($filters, ['analysis' => 'coaching'])) }}">View all coaching</a>
            </div>
        </div>

        <ol class="list-group list-group-flush">
            @foreach ($coachingRows as $index => $row)
                <li class="list-group-item">
                    <div class="row g-2 align-items-start">
                        <div class="col-12 col-md">
                            <div class="d-flex flex-wrap align-items-center gap-2">
                                <span class="fw-bold text-warning ark-money">{{ $index + 1 }}.</span>
                                <span class="fw-bold">
                                    @if (($row['kind'] ?? 'call') === 'sms')
                                        <span class="badge bg-azure-lt me-1">SMS</span>
                                    @endif
                                    {{ $row['display_phone'] }}
                                    @if ($row['customer_name'])
                                        <span class="fw-normal text-secondary">· {{ $row['customer_name'] }}</span>
                                    @endif
                                </span>
                                @if ($row['coaching_follow_up_pinned'])
                                    <span class="badge bg-indigo-lt">Pinned</span>
                                @endif
                            </div>
                            <div class="small text-secondary mt-1">
                                {{ $row['started_at_label'] }}
                                @if ($row['staff_name'])
                                    · {{ $row['staff_name'] }}
                                @endif
                                · {{ $row['direction_label'] }}
                            </div>
                            @include('operations.owner.partials.call-intelligence-signal-groups', [
                                'row' => $row,
                                'compact' => true,
                                'showMeta' => false,
                            ])
                            @if ($row['coaching_headline'])
                                <p class="mt-2 mb-0">{{ $row['coaching_headline'] }}</p>
                            @elseif ($row['summary'])
                                <p class="text-secondary mt-2 mb-0">{{ $row['summary'] }}</p>
                            @endif
                        </div>
                        <div class="col-12 col-md-auto">
                            <div class="btn-list">
                                @include('operations.owner.partials.call-intelligence-follow-up-pin', ['row' => $row])
                                @if ($row['coaching_pdf_url'])
                                    <a href="{{ $row['coaching_pdf_url'] }}" target="_blank" rel="noopener noreferrer" class="btn btn-sm">Handout</a>
                                @endif
                                <a href="{{ $row['show_url'] }}?{{ http_build_query($filters) }}" class="btn btn-sm btn-primary">{{ $row['open_label'] ?? 'Open call' }}</a>
                            </div>
                        </div>
                    </div>
                </li>
            @endforeach
        </ol>
    </section>
@endif
