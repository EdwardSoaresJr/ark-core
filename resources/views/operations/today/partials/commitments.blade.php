@php
    /** @var \App\Ark\Operations\Today\TodayCommitmentsSummary $commitments */
@endphp

<section class="ops-today__section ops-today-commitments" aria-labelledby="ops-today-commitments">
    <div class="ops-today__section-header">
        <h2 id="ops-today-commitments" class="ops-today__section-title">Commitments</h2>
        <p class="ops-today__section-copy">What we promised, who owns it, and when it is due — not tasks, not hope.</p>
    </div>

    <div class="ops-today-commitments__summary">
        <p class="ops-today-commitments__count">
            <span class="ops-today-commitments__count-label">Due Today</span>
            <span class="ops-today-commitments__count-value">{{ $commitments->dueTodayCount }}</span>
        </p>
        <p @class([
            'ops-today-commitments__count',
            'ops-today-commitments__count--overdue' => $commitments->overdueCount > 0,
        ])>
            <span class="ops-today-commitments__count-label">Overdue</span>
            <span class="ops-today-commitments__count-value">{{ $commitments->overdueCount }}</span>
        </p>
    </div>

    @if ($commitments->rows === [])
        <p class="ops-today__empty">No open commitments due today or overdue.</p>
    @else
        <ul class="ops-today-commitments__list">
            @foreach ($commitments->rows as $row)
                <li @class([
                    'ops-today-commitments__item',
                    'ops-today-commitments__item--overdue' => $row->isOverdue,
                ])>
                    <div class="ops-today-commitments__item-main">
                        <a href="{{ $row->repairOrderUrl }}" class="ops-today-commitments__title">{{ $row->title }}</a>
                        <p class="ops-today-commitments__due">{{ $row->dueLabel }}</p>
                        <p class="ops-today-commitments__reason">
                            <span class="ops-today-commitments__reason-label">Reason</span>
                            {{ $row->reason }}
                        </p>
                        <p class="ops-today-commitments__meta">
                            RO #{{ $row->shopRepairOrderId }}
                            · {{ $row->ownerName }}
                        </p>
                    </div>
                    <form
                        method="POST"
                        action="{{ route('operations.commitments.fulfill', $row->id) }}"
                        class="ops-today-commitments__fulfill"
                    >
                        @csrf
                        <button type="submit" class="ops-btn ops-btn--secondary ops-btn--sm">Done</button>
                    </form>
                </li>
            @endforeach
        </ul>
    @endif
</section>
