@php
    /** @var \App\Ark\Operations\Workboard\JobBoardListPage $jobBoardListPage */
    /** @var list<\App\Ark\Operations\Workboard\JobBoardListRow> $jobBoardListRows */
    /** @var list<array{key: string, label: string, color: string, count: int}> $jobBoardLaneTabs */
    $cookieDomain = \App\Ark\Runtime\Preferences\EcosystemDisplayTheme::cookieDomain();
    $listActive = $jobBoardLayoutMode === \App\Ark\Operations\Workboard\JobBoardLayout::List->value;
    $cardsActive = $jobBoardLayoutMode === \App\Ark\Operations\Workboard\JobBoardLayout::Cards->value;
@endphp

<x-operations.app title="Job Board · DataTables eval" ui="tabler">
    @vite(['resources/css/eval/job-board-datatables.css'])

    <x-tabler.page layout="fluid" title="Job Board">
        <div class="alert ark-dt-banner mb-2 py-2" role="status">
            <div class="d-flex flex-wrap gap-2 align-items-center justify-content-between">
                <div class="small mb-0">
                    <strong>Evaluation prototype</strong> — DataTables.js list chrome + Tabler styling.
                    Shared Job Board builder / concern fallback from PR #254. Yajra is <em>not</em> installed.
                    <a href="{{ $productionListUrl }}">Open production List view</a>
                </div>
                <span class="badge bg-azure-lt text-azure">eval only</span>
            </div>
        </div>

        <section
            class="d-flex flex-column gap-2 ark-dt-prototype"
            data-job-board
            data-ark-dt-prototype
            data-dt-url="{{ $prototypeDataUrl }}"
            data-job-board-layout="{{ $jobBoardLayoutMode }}"
            data-job-board-layout-explicit="1"
            data-job-board-cookie-domain="{{ $cookieDomain }}"
        >
            <div class="sticky-top ark-sticky-below-workspace-tabs bg-body py-2 my-n2" data-job-board-chrome>
                <form
                    method="get"
                    action="{{ $prototypePageUrl }}"
                    class="row g-2 align-items-center"
                    aria-label="Job board filters"
                    role="search"
                    data-job-board-filters
                >
                    @if ($jobBoardListPage->laneKey !== 'all')
                        <input type="hidden" name="lane" value="{{ $jobBoardListPage->laneKey }}">
                    @endif
                    <div class="col-12 col-md">
                        <div class="input-icon">
                            <span class="input-icon-addon"><x-tabler.icon name="search" /></span>
                            <input
                                type="search"
                                name="q"
                                value="{{ $jobBoardListPage->query }}"
                                class="form-control"
                                placeholder="Search RO #, customer, vehicle, concern"
                                aria-label="Search job board"
                                autocomplete="off"
                            >
                        </div>
                    </div>
                    <div class="col col-md-auto">
                        <select name="tech" class="form-select" aria-label="Employee">
                            <option value="">All employees</option>
                            @foreach ($homeBoardTechnicians as $technician)
                                <option value="{{ $technician->id }}" @selected((string) $jobBoardListPage->techId === (string) $technician->id)>{{ $technician->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-auto">
                        <button type="submit" class="btn btn-outline-secondary">Search</button>
                    </div>
                    <div class="col-auto">
                        <div class="nav nav-segmented" role="group" aria-label="Job board layout">
                            <button type="button" class="nav-link{{ $listActive ? ' active' : '' }}" data-job-board-layout-toggle="list" aria-pressed="{{ $listActive ? 'true' : 'false' }}">List</button>
                            <button type="button" class="nav-link{{ $cardsActive ? ' active' : '' }}" data-job-board-layout-toggle="cards" aria-pressed="{{ $cardsActive ? 'true' : 'false' }}">Cards</button>
                        </div>
                    </div>
                </form>

                <nav class="nav nav-tabs flex-nowrap overflow-auto border-0 gap-0 mt-2" aria-label="Job board queues" data-job-board-lane-tabs>
                    @foreach ($jobBoardLaneTabs as $tab)
                        @php
                            $tabTone = \App\Ark\Operations\RepairOrders\Status\RepairOrderStatusColor::tablerColorForChipTone(
                                \App\Ark\Operations\RepairOrders\Status\RepairOrderStatusColor::chipTone($tab['color']),
                            );
                            $tabUrl = route('operations.eval.job-board-datatables', array_filter([
                                'lane' => $tab['key'] === 'all' ? null : $tab['key'],
                                'q' => $jobBoardListPage->query !== '' ? $jobBoardListPage->query : null,
                                'tech' => $jobBoardListPage->techId !== '' ? $jobBoardListPage->techId : null,
                            ]));
                            $isActive = $jobBoardListPage->laneKey === $tab['key'];
                        @endphp
                        <a
                            href="{{ $tabUrl }}"
                            @class(['nav-link text-nowrap px-3', 'active' => $isActive])
                            data-job-board-lane-tab="{{ $tab['key'] }}"
                            @if ($isActive) aria-current="page" @endif
                        >
                            <span class="status-dot status-{{ $tabTone }} me-1" aria-hidden="true"></span>
                            {{ $tab['label'] }}
                            <span class="badge bg-secondary-lt text-secondary ms-1">{{ $tab['count'] }}</span>
                        </a>
                    @endforeach
                </nav>
            </div>

            <p class="mb-0 text-secondary small" data-job-board-list-range>
                Loading…
            </p>

            <div data-job-board-view="list" @class(['d-none' => $cardsActive])>
                <div class="card">
                    <div class="table-responsive">
                        <table
                            id="ark-job-board-dt"
                            class="table table-vcenter table-hover card-table table-sm mb-0 ark-job-board-table display nowrap w-100"
                            style="width:100%"
                        >
                            <thead>
                                <tr>
                                    <th scope="col">RO #</th>
                                    <th scope="col">Customer / Vehicle</th>
                                    <th scope="col">Concern / Job</th>
                                    <th scope="col">Status</th>
                                    <th scope="col">Tech</th>
                                    <th scope="col" class="text-end">Total</th>
                                    <th scope="col">Attention</th>
                                    <th scope="col">Activity</th>
                                    <th scope="col">Updated</th>
                                    <th scope="col"><span class="visually-hidden">Open</span></th>
                                </tr>
                            </thead>
                        </table>
                    </div>
                </div>
            </div>

            <div data-job-board-view="cards" @class(['d-none' => $listActive])>
                @include('operations.workboard.partials.home-board-cards', [
                    'jobBoardListRows' => $jobBoardListRows,
                    'jobBoardListPage' => $jobBoardListPage,
                ])
                <p class="small text-secondary mt-2 mb-0">
                    Cards use the same server projection as production. Filters reload the page; List uses DataTables AJAX.
                </p>
            </div>
        </section>
    </x-tabler.page>

    {{-- CDN only for this eval page: no production composer/npm Yajra/DataTables dependency. --}}
    <script src="https://code.jquery.com/jquery-3.7.1.min.js" integrity="sha256-/JqT3SQfawRcv/BIHPThkBvs0OEvtFFmqPF/lYI/Cxo=" crossorigin="anonymous"></script>
    <script src="https://cdn.datatables.net/2.3.4/js/dataTables.min.js" crossorigin="anonymous"></script>
    @vite(['resources/js/eval/job-board-datatables.js'])
</x-operations.app>
