@php
    /** @var \App\Ark\Operations\Today\AdvisorHomeCockpitProjection $cockpit */
    /** @var list<\App\Ark\Operations\Today\AdvisorHomeAttentionZone> $attentionZones */
@endphp

<x-operations.app title="Job Board">
    <section
        class="ops-advisor-home ops-job-board"
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

                return [...root.querySelectorAll('.ops-job-card-wrap')]
                    .filter((el) => this.cardMatches(el))
                    .length;
            },
        }"
    >
        @if (session('comms_gate') || session('status') || $errors->has('lifecycle'))
            <div class="ops-advisor-home__alerts">
                @if (session('comms_gate'))
                    <div class="border border-rose-300 bg-rose-50 px-3 py-2 text-sm font-semibold text-rose-950" role="alert">
                        {{ (int) (session('comms_gate.count') ?? 0) }} customer {{ ((int) (session('comms_gate.count') ?? 0)) === 1 ? 'contact' : 'contacts' }} still need action. Clear communications before opening other ARK pages.
                    </div>
                @endif

                @if ($errors->has('lifecycle'))
                    <div class="border border-rose-300 bg-rose-50 px-3 py-2 text-sm font-semibold text-rose-950" role="alert">
                        {{ $errors->first('lifecycle') }}
                    </div>
                @endif

                @if (session('status'))
                    <div class="border border-emerald-200 bg-emerald-50 px-3 py-2 text-sm font-semibold text-emerald-950" role="status">
                        {{ session('status') }}
                    </div>
                @endif
            </div>
        @endif

        <div class="ops-advisor-home__sticky-head">
            @include('operations.workboard.partials.home-board-toolbar', [
                'homeBoardTechnicians' => $homeBoardTechnicians,
                'activeRepairOrderCount' => $activeRepairOrderCount,
            ])
        </div>

        <div class="ops-advisor-home__grid">
            <div class="ops-advisor-home-board ops-job-board__columns">
                @foreach ($homeBoardColumns as $column)
                    @include('operations.workboard.partials.home-column', [
                        'column' => $column,
                        'repairOrderTotals' => $repairOrderTotals,
                        'homeCardSurfaces' => $homeCardSurfaces,
                        'cockpit' => $cockpit,
                    ])
                @endforeach
            </div>
        </div>
    </section>
</x-operations.app>
