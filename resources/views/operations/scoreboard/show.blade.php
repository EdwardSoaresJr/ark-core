<x-operations.app title="Shop scoreboard">
    <div class="space-y-4">
        <form method="GET" action="{{ route('operations.owner.scoreboard') }}" class="flex flex-wrap items-center gap-2">
            @if ($focus)
                <input type="hidden" name="focus" value="{{ $focus }}">
            @endif
            @if ($focus === 'aging')
                <input type="hidden" name="days" value="{{ $agingDays }}">
            @endif
            <label class="text-xs font-bold uppercase tracking-wide text-slate-500" for="scoreboard-period">Period</label>
            <select id="scoreboard-period" name="period" class="rounded-sm border border-slate-300 bg-white px-2 py-1.5 text-sm font-semibold text-slate-950" onchange="this.form.submit()">
                @foreach ($snapshot['periods'] as $option)
                    <option value="{{ $option['key'] }}" @selected($option['key'] === $periodKey)>{{ $option['label'] }}</option>
                @endforeach
            </select>
            @can('settings.manage')
                <a href="{{ route('operations.settings.shop.edit', ['section' => 'excellence']) }}" class="text-xs font-bold text-slate-700 underline">Targets</a>
            @endcan
        </form>

        @include('operations.scoreboard.partials.board')

        @if ($drilldown)
            @include('operations.scoreboard.partials.drilldown')
        @endif
    </div>
</x-operations.app>
