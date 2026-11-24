@php
    /** @var \App\Ark\Operations\Today\AdvisorHomeCockpitProjection $cockpit */
    /** @var list<\App\Ark\Operations\Today\AdvisorHomeAttentionZone> $attentionZones */
    /** @var list<\App\Ark\Operations\Workboard\JobBoardListRow> $jobBoardListRows */
    /** @var list<array{key: string, label: string, color: string, count: int}> $jobBoardLaneTabs */
    /** @var \App\Ark\Operations\Workboard\JobBoardListPage $jobBoardListPage */
@endphp

<x-operations.app title="Job Board" ui="tabler">
    <x-tabler.page layout="fluid" title="Job Board">
        <section
            class="d-flex flex-column gap-2"
            data-job-board
            data-job-board-layout="list"
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
                ])

                @include('operations.workboard.partials.home-board-lane-tabs', [
                    'jobBoardLaneTabs' => $jobBoardLaneTabs,
                    'jobBoardListPage' => $jobBoardListPage,
                ])
            </div>

            @include('operations.workboard.partials.home-board-list', [
                'jobBoardListRows' => $jobBoardListRows,
                'jobBoardListPage' => $jobBoardListPage,
            ])
        </section>
    </x-tabler.page>
</x-operations.app>
