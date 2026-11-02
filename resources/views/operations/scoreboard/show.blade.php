<x-operations.app title="Shop scoreboard" ui="tabler">
    <x-tabler.page layout="fluid" title="Shop scoreboard" actions-label="Scoreboard shortcuts">
        @can('settings.manage')
            <x-slot:actions>
                <x-tabler.page-action :href="route('operations.settings.shop.edit', ['section' => 'excellence'])" icon="settings" label="Targets" />
            </x-slot:actions>
        @endcan

        @include('operations.dashboard.tabs')

        <form method="GET" action="{{ route('operations.owner.scoreboard') }}" class="d-flex align-items-center gap-2 mb-3" style="max-width: 22rem" data-scoreboard-period-form>
            @if ($focus)
                <input type="hidden" name="focus" value="{{ $focus }}">
            @endif
            @if ($focus === 'aging')
                <input type="hidden" name="days" value="{{ $agingDays }}">
            @endif
            <label class="form-label mb-0 text-nowrap" for="scoreboard-period">Period</label>
            <select id="scoreboard-period" name="period" class="form-select" onchange="this.form.submit()">
                @foreach ($snapshot['periods'] as $option)
                    <option value="{{ $option['key'] }}" @selected($option['key'] === $periodKey)>{{ $option['label'] }}</option>
                @endforeach
            </select>
        </form>

        @include('operations.scoreboard.partials.board', ['ui' => 'tabler'])

        @if ($drilldown)
            @include('operations.scoreboard.partials.drilldown')
        @endif
    </x-tabler.page>
</x-operations.app>
