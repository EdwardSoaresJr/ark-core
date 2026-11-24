@php
    /** @var \App\Ark\Operations\Today\AdvisorHomeCockpitProjection $cockpit */
    /** @var list<\App\Ark\Operations\Today\AdvisorHomeAttentionZone> $attentionZones */
    /** @var \App\Ark\Operations\Workboard\JobBoardLayout $jobBoardLayout */
    /** @var list<\App\Ark\Operations\Workboard\JobBoardListRow> $jobBoardListRows */
    /** @var list<array{key: string, label: string, color: string, count: int}> $jobBoardLaneTabs */
    /** @var \App\Ark\Operations\Workboard\JobBoardListPage|null $jobBoardListPage */
    $isListLayout = $jobBoardLayout->isList();
@endphp

<x-operations.app title="Job Board" ui="tabler">
    <x-tabler.page layout="fluid" title="Job Board">
        <section
            class="d-flex flex-column gap-3"
            data-job-board
            data-job-board-layout="{{ $jobBoardLayout->value }}"
            @if ($isListLayout)
                data-job-board-list-page="{{ $jobBoardListPage?->page }}"
                data-job-board-list-per-page="{{ $jobBoardListPage?->perPage }}"
                data-job-board-list-filtered-total="{{ $jobBoardListPage?->filteredTotal }}"
                data-job-board-list-queue-total="{{ $jobBoardListPage?->queueTotal }}"
            @endif
            x-data="jobBoardFilters(@js([
                'layout' => $jobBoardLayout->value,
                'laneKey' => $isListLayout ? ($jobBoardListPage?->laneKey ?? 'all') : 'all',
                'query' => $isListLayout ? ($jobBoardListPage?->query ?? '') : '',
                'techId' => $isListLayout ? ($jobBoardListPage?->techId ?? '') : '',
                'sortKey' => $isListLayout ? ($jobBoardListPage?->sortKey ?? 'updated') : 'updated',
                'sortDir' => $isListLayout ? ($jobBoardListPage?->sortDir ?? 'desc') : 'desc',
            ]))"
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
                    'activeRepairOrderCount' => $activeRepairOrderCount,
                    'jobBoardLayout' => $jobBoardLayout,
                    'jobBoardListPage' => $jobBoardListPage,
                ])

                @include('operations.workboard.partials.home-board-lane-tabs', [
                    'jobBoardLaneTabs' => $jobBoardLaneTabs,
                    'isListLayout' => $isListLayout,
                    'jobBoardListPage' => $jobBoardListPage,
                ])
            </div>

            @if ($isListLayout)
                @include('operations.workboard.partials.home-board-list', [
                    'jobBoardListRows' => $jobBoardListRows,
                    'jobBoardListPage' => $jobBoardListPage,
                ])
            @else
                <div class="ark-board pb-2" data-job-board-lanes>
                    @foreach ($homeBoardColumns as $column)
                        @include('operations.workboard.partials.home-column', [
                            'column' => $column,
                            'repairOrderTotals' => $repairOrderTotals,
                            'homeCardSurfaces' => $homeCardSurfaces,
                            'cockpit' => $cockpit,
                        ])
                    @endforeach
                </div>
            @endif
        </section>

        <script>
            function jobBoardFilters(initial = {}) {
                return {
                    layout: initial.layout || 'board',
                    query: initial.query || '',
                    techId: initial.techId || '',
                    laneKey: initial.laneKey || 'all',
                    sortKey: initial.sortKey || 'updated',
                    sortDir: initial.sortDir || 'desc',
                    isList() {
                        return this.layout === 'list';
                    },
                    cardMatches(el) {
                        if (this.isList()) {
                            return true;
                        }

                        if (this.techId !== '' && (el.dataset.homeTechId ?? '') !== this.techId) {
                            return false;
                        }

                        if (this.laneKey !== 'all' && (el.dataset.homeLaneKey ?? '') !== this.laneKey) {
                            return false;
                        }

                        const needle = this.query.trim().toLowerCase();

                        if (needle === '') {
                            return true;
                        }

                        return (el.dataset.homeSearch ?? '').includes(needle);
                    },
                    columnVisibleCount(columnKey) {
                        void this.query;
                        void this.techId;

                        const root = this.$root.querySelector('#ops-home-col-' + columnKey);

                        if (! root) {
                            return 0;
                        }

                        return [...root.querySelectorAll('[data-home-search]')]
                            .filter((el) => {
                                if (this.techId !== '' && (el.dataset.homeTechId ?? '') !== this.techId) {
                                    return false;
                                }

                                const needle = this.query.trim().toLowerCase();

                                return needle === '' || (el.dataset.homeSearch ?? '').includes(needle);
                            })
                            .length;
                    },
                };
            }
        </script>
    </x-tabler.page>
</x-operations.app>
