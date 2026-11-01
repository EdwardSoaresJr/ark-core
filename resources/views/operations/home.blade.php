@php
    /** @var \App\Ark\Operations\Today\AdvisorHomeCockpitProjection $cockpit */
    /** @var list<\App\Ark\Operations\Today\AdvisorHomeAttentionZone> $attentionZones */
@endphp

<x-operations.app title="Job Board" ui="tabler">
    <x-tabler.page layout="fluid">
        <section
            class="d-flex flex-column gap-3"
            data-job-board
            x-data="{
                query: '',
                techId: '',
                cardMatches(el) {
                    if (this.techId !== '' && (el.dataset.homeTechId ?? '') !== this.techId) {
                        return false;
                    }

                    const needle = this.query.trim().toLowerCase();

                    if (needle === '') {
                        return true;
                    }

                    return (el.dataset.homeSearch ?? '').includes(needle);
                },
                columnVisibleCount(columnKey) {
                    // Touch reactive deps so counts refresh when filters change.
                    void this.query;
                    void this.techId;

                    const root = this.$root.querySelector('#ops-home-col-' + columnKey);

                    if (! root) {
                        return 0;
                    }

                    return [...root.querySelectorAll('[data-home-search]')]
                        .filter((el) => this.cardMatches(el))
                        .length;
                },
            }"
        >
            @if (session('comms_gate'))
                <div class="alert alert-danger mb-0" role="alert">
                    {{ (int) (session('comms_gate.count') ?? 0) }} customer {{ ((int) (session('comms_gate.count') ?? 0)) === 1 ? 'contact' : 'contacts' }} still need action. Clear communications before opening other ARK pages.
                </div>
            @endif
            @if ($errors->has('lifecycle'))
                <div class="alert alert-danger mb-0" role="alert">{{ $errors->first('lifecycle') }}</div>
            @endif
            @if (session('status'))
                <div class="alert alert-success mb-0" role="status">{{ session('status') }}</div>
            @endif

            <div class="sticky-top bg-body py-2 my-n2">
                @include('operations.workboard.partials.home-board-toolbar', [
                    'homeBoardTechnicians' => $homeBoardTechnicians,
                    'activeRepairOrderCount' => $activeRepairOrderCount,
                ])
            </div>

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
        </section>
    </x-tabler.page>
</x-operations.app>
