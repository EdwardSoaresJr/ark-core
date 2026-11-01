@php
    /** @var \App\Ark\Operations\Today\Surface\TodayProjection $today */
    /** @var list<\App\Ark\Operations\Today\Surface\TodaySection> $sections */
@endphp

<div class="d-flex flex-column gap-3" data-today-lanes>
    @forelse ($sections as $section)
        <section class="card" aria-labelledby="today-lane-{{ $section->key }}" data-today-lane="{{ $section->key }}">
            <div class="card-header">
                <h2 id="today-lane-{{ $section->key }}" class="card-title">
                    {{ $section->title }}
                    <span class="badge bg-secondary-lt ms-1" data-today-lane-count>{{ $section->totalCount }}</span>
                </h2>
                @if (filled($section->viewAllUrl))
                    <div class="card-actions">
                        <a href="{{ $section->viewAllUrl }}" class="btn btn-sm">{{ $section->viewAllLabel ?: 'View all' }}</a>
                    </div>
                @endif
            </div>
            <div class="list-group list-group-flush">
                @foreach ($section->actions as $action)
                    <a href="{{ $action->url }}" class="list-group-item list-group-item-action" data-today-action>
                        <div class="row g-2 align-items-center">
                            <div class="col-auto">
                                <span class="badge bg-green-lt">Work</span>
                            </div>
                            <div class="col text-truncate">
                                <div class="fw-medium text-truncate">{{ $action->title }}</div>
                                @if (filled($action->reason))
                                    <div class="small text-secondary text-truncate">{{ $action->reason }}</div>
                                @endif
                            </div>
                            <div class="col-auto d-none d-md-block small text-secondary">{{ $action->ownerLabel }}</div>
                            <div class="col-auto d-none d-sm-block small text-primary" aria-hidden="true">Open RO</div>
                        </div>
                    </a>
                @endforeach
            </div>
        </section>
    @empty
        <div class="card" data-today-caught-up>
            <div class="card-body">
                <h2 class="card-title mb-1">You&apos;re caught up.</h2>
                @if (filled($today->caughtUpDetail))
                    <p class="text-secondary mb-3">{{ $today->caughtUpDetail }}</p>
                @endif
                <div class="subheader">Today&apos;s focus</div>
                <div class="fw-medium">{{ $today->caughtUpFocus }}</div>
            </div>
        </div>
    @endforelse
</div>
