@php
    /** @var \App\Ark\Operations\Today\Surface\TodayProjection $today */
    /** @var list<\App\Ark\Operations\Today\Surface\TodaySection> $sections */
@endphp

<section class="ops-today" x-data="{ whyTodayOpen: false }">
    <div class="ops-today__sticky">
        <header class="ops-today-cockpit" aria-label="Today orientation">
            <div class="ops-today-cockpit__row">
                <div class="ops-today-cockpit__brand">
                    <h1 class="ops-today-cockpit__title">Today</h1>
                    @if ($today->attentionCount > 0)
                        <span class="ops-today-cockpit__badge">{{ $today->attentionCount }}</span>
                    @endif
                    <p class="ops-today-cockpit__greeting">{{ $today->greeting }}</p>
                </div>
                <div class="ops-today-cockpit__tools">
                    <a href="{{ route('operations.workboard') }}" class="ops-today-tool">Workboard</a>
                </div>
            </div>
        </header>
    </div>

    <div class="ops-today__body">
        @forelse ($sections as $section)
            <section class="ops-today-lane" aria-labelledby="today-lane-{{ $section->key }}">
                <div class="ops-today-lane__header">
                    <div class="ops-today-lane__title-wrap">
                        <h2 id="today-lane-{{ $section->key }}" class="ops-today-lane__title">{{ $section->title }}</h2>
                        <span class="ops-today-lane__count">({{ $section->totalCount }})</span>
                    </div>
                    @if (filled($section->viewAllUrl))
                        <a href="{{ $section->viewAllUrl }}" class="ops-today-lane__view-all">
                            {{ $section->viewAllLabel ?: 'View all' }}
                        </a>
                    @endif
                </div>
                <ul class="ops-today-rows">
                    @foreach ($section->actions as $action)
                        <li class="ops-today-row">
                            <a href="{{ $action->url }}" class="ops-today-row__main">
                                <span class="ops-today-chip ops-today-chip--work">Work</span>
                                <span class="ops-today-row__identity">
                                    <span class="ops-today-row__title">{{ $action->title }}</span>
                                    @if (filled($action->reason))
                                        <span class="ops-today-row__meta">{{ $action->reason }}</span>
                                    @endif
                                </span>
                                <span class="ops-today-row__owner">{{ $action->ownerLabel }}</span>
                                <span class="ops-today-row__open" aria-hidden="true">Open RO</span>
                            </a>
                        </li>
                    @endforeach
                </ul>
            </section>
        @empty
            <div class="ops-today-empty">
                <p class="ops-today-empty__title">You&apos;re caught up.</p>
                @if (filled($today->caughtUpDetail))
                    <p class="ops-today-empty__detail">{{ $today->caughtUpDetail }}</p>
                @endif
                <p class="ops-today-empty__focus-label">Today&apos;s focus</p>
                <p class="ops-today-empty__focus">{{ $today->caughtUpFocus }}</p>
            </div>
        @endforelse
    </div>
</section>
