@php
    /** @var \App\Ark\Operations\Today\AdvisorHomeCockpitProjection $cockpit */
    /** @var list<\App\Ark\Operations\Today\AdvisorHomeAttentionZone> $attentionZones */
    /** @var \App\Ark\Operations\Workboard\JobBoardLayout $jobBoardLayout */
    /** @var list<\App\Ark\Operations\Workboard\JobBoardListRow> $jobBoardListRows */
    /** @var list<array{key: string, label: string, color: string, count: int}> $jobBoardLaneTabs */
    $isListLayout = $jobBoardLayout->isList();
@endphp

<x-operations.app title="Job Board" ui="tabler">
    <x-tabler.page layout="fluid" title="Job Board">
        <section
            class="d-flex flex-column gap-3"
            data-job-board
            data-job-board-layout="{{ $jobBoardLayout->value }}"
            x-data="jobBoardFilters()"
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
                ])

                @include('operations.workboard.partials.home-board-lane-tabs', [
                    'jobBoardLaneTabs' => $jobBoardLaneTabs,
                    'isListLayout' => $isListLayout,
                ])
            </div>

            @if ($isListLayout)
                @include('operations.workboard.partials.home-board-list', [
                    'jobBoardListRows' => $jobBoardListRows,
                    'homeCardSurfaces' => $homeCardSurfaces,
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
            function jobBoardFilters() {
                return {
                    query: '',
                    techId: '',
                    laneKey: 'all',
                    sortKey: 'pressure',
                    sortDir: 'desc',
                    cardMatches(el) {
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
                    filteredLaneCount(tabKey) {
                        void this.query;
                        void this.techId;

                        const rows = [...this.$root.querySelectorAll('[data-job-board-list-body] [data-job-board-row]')];

                        return rows.filter((el) => {
                            if (tabKey !== 'all' && (el.dataset.homeLaneKey ?? '') !== tabKey) {
                                return false;
                            }

                            if (this.techId !== '' && (el.dataset.homeTechId ?? '') !== this.techId) {
                                return false;
                            }

                            const needle = this.query.trim().toLowerCase();

                            return needle === '' || (el.dataset.homeSearch ?? '').includes(needle);
                        }).length;
                    },
                    visibleRowCount() {
                        void this.query;
                        void this.techId;
                        void this.laneKey;

                        return [...this.$root.querySelectorAll('[data-job-board-list-body] [data-job-board-row]')]
                            .filter((el) => this.cardMatches(el))
                            .length;
                    },
                    sortValue(el, key) {
                        return el.getAttribute('data-sort-' + key) ?? '';
                    },
                    setSort(key) {
                        if (this.sortKey === key) {
                            this.sortDir = this.sortDir === 'asc' ? 'desc' : 'asc';
                        } else {
                            this.sortKey = key;
                            this.sortDir = ['ro', 'customer', 'vehicle', 'status', 'technician'].includes(key) ? 'asc' : 'desc';
                        }

                        this.$nextTick(() => this.applySort());
                    },
                    applySort() {
                        const tbody = this.$root.querySelector('[data-job-board-list-body]');
                        const mobile = this.$root.querySelector('[data-job-board-list-mobile]');

                        if (! tbody) {
                            return;
                        }

                        const dir = this.sortDir === 'asc' ? 1 : -1;
                        const key = this.sortKey;
                        const rows = [...tbody.querySelectorAll(':scope > [data-job-board-row]')];

                        rows.sort((a, b) => {
                            const av = this.sortValue(a, key);
                            const bv = this.sortValue(b, key);
                            const an = Number(av);
                            const bn = Number(bv);

                            if (av !== '' && bv !== '' && ! Number.isNaN(an) && ! Number.isNaN(bn)) {
                                return (an - bn) * dir;
                            }

                            return String(av).localeCompare(String(bv), undefined, { sensitivity: 'base', numeric: true }) * dir;
                        });

                        rows.forEach((row) => {
                            tbody.appendChild(row);

                            if (! mobile) {
                                return;
                            }

                            const mobileRow = mobile.querySelector('[data-job-board-mobile-for="' + row.dataset.roId + '"]');

                            if (mobileRow) {
                                mobile.appendChild(mobileRow);
                            }
                        });
                    },
                };
            }
        </script>
    </x-tabler.page>
</x-operations.app>
