@php
    /** @var \App\Ark\Operations\Today\AdvisorHomeCockpitProjection $cockpit */
    /** @var list<\App\Ark\Operations\Today\AdvisorHomeAttentionZone> $attentionZones */
    /** @var list<\App\Ark\Operations\Workboard\JobBoardListRow> $jobBoardListRows */
    /** @var list<array{key: string, label: string, color: string, count: int}> $jobBoardLaneTabs */
    /** @var \App\Ark\Operations\Workboard\JobBoardListPage $jobBoardListPage */
    /** @var string $jobBoardLayoutMode */
    /** @var bool $jobBoardLayoutExplicit */
    /** @var array{tenant: string, user: string} $uiPreferenceScope */
    $cookieDomain = \App\Ark\Runtime\Preferences\EcosystemDisplayTheme::cookieDomain();
    $uiPreferenceScope ??= ['tenant' => 'local', 'user' => 'anon'];
@endphp

<x-operations.app title="Job Board" ui="tabler">
    <x-tabler.page layout="fluid" title="Job Board">
        <section
            class="d-flex flex-column gap-2"
            data-job-board
            data-job-board-layout="{{ $jobBoardLayoutMode }}"
            data-job-board-layout-explicit="{{ $jobBoardLayoutExplicit ? '1' : '0' }}"
            data-job-board-cookie-domain="{{ $cookieDomain }}"
            data-ark-ui-tenant="{{ $uiPreferenceScope['tenant'] }}"
            data-ark-ui-user="{{ $uiPreferenceScope['user'] }}"
            data-job-board-list-page="{{ $jobBoardListPage->page }}"
            data-job-board-list-per-page="{{ $jobBoardListPage->perPage }}"
            data-job-board-list-filtered-total="{{ $jobBoardListPage->filteredTotal }}"
            data-job-board-list-queue-total="{{ $jobBoardListPage->queueTotal }}"
        >
            @if ($errors->has('lifecycle'))
                <div class="alert alert-danger mb-0" role="alert">{{ $errors->first('lifecycle') }}</div>
            @endif
            @if (session('status'))
                <div class="alert alert-success mb-0" role="status">{{ session('status') }}</div>
            @endif

            <div class="sticky-top ark-sticky-below-workspace-tabs bg-body py-2 my-n2" data-job-board-chrome>
                @include('operations.workboard.partials.home-board-toolbar', [
                    'homeBoardTechnicians' => $homeBoardTechnicians,
                    'jobBoardListPage' => $jobBoardListPage,
                    'jobBoardLayoutMode' => $jobBoardLayoutMode,
                ])

                <div data-job-board-list-chrome>
                    @include('operations.workboard.partials.home-board-lane-tabs', [
                        'jobBoardLaneTabs' => $jobBoardLaneTabs,
                        'jobBoardListPage' => $jobBoardListPage,
                    ])
                </div>
            </div>

            <div class="d-flex flex-wrap align-items-center gap-2 mb-0" data-job-board-list-chrome data-job-board-list-summary>
                <p class="mb-0 text-secondary small" data-job-board-list-range>
                    @if ($jobBoardListPage->filteredTotal === 0)
                        No matching repair orders
                    @else
                        Showing {{ $jobBoardListPage->from() }}–{{ $jobBoardListPage->to() }}
                        of {{ $jobBoardListPage->filteredTotal }}
                        @if ($jobBoardListPage->hasFilters())
                            matching
                        @endif
                        · Queue total {{ $jobBoardListPage->queueTotal }}
                    @endif
                </p>
                @if ($jobBoardListPage->hasFilters())
                    <a href="{{ route('operations.index') }}" class="btn btn-sm btn-ghost-secondary" data-job-board-list-clear>Clear filters</a>
                @endif
            </div>

            <div data-job-board-view="list">
                @include('operations.workboard.partials.home-board-list', [
                    'jobBoardListRows' => $jobBoardListRows,
                    'jobBoardListPage' => $jobBoardListPage,
                ])
            </div>

            <div data-job-board-view="cards">
                @include('operations.workboard.partials.home-board-cards', [
                    'jobBoardListRows' => $jobBoardListRows,
                    'jobBoardListPage' => $jobBoardListPage,
                ])
            </div>

            @if ($jobBoardListPage->lastPage > 1)
                <nav class="d-flex justify-content-between align-items-center mt-1" aria-label="Job board pages" data-job-board-list-chrome data-job-board-list-pagination>
                    @if ($jobBoardListPage->page > 1)
                        <a class="btn btn-outline-secondary" href="{{ route('operations.index', $jobBoardListPage->queryParams(['page' => $jobBoardListPage->page - 1])) }}" data-job-board-list-prev>Previous</a>
                    @else
                        <span class="btn btn-outline-secondary disabled" aria-disabled="true">Previous</span>
                    @endif
                    <span class="text-secondary small">Page {{ $jobBoardListPage->page }} of {{ $jobBoardListPage->lastPage }}</span>
                    @if ($jobBoardListPage->page < $jobBoardListPage->lastPage)
                        <a class="btn btn-outline-secondary" href="{{ route('operations.index', $jobBoardListPage->queryParams(['page' => $jobBoardListPage->page + 1])) }}" data-job-board-list-next>Next</a>
                    @else
                        <span class="btn btn-outline-secondary disabled" aria-disabled="true">Next</span>
                    @endif
                </nav>
            @endif
        </section>
    </x-tabler.page>
</x-operations.app>
