<section class="border border-slate-300 bg-white">
    <div class="flex flex-wrap items-baseline justify-between gap-2 border-b border-slate-200 px-3 py-2">
        <div>
            <h2 class="text-sm font-black text-slate-950">{{ $drilldown['title'] }}</h2>
            @if ($drilldown['note'])
                <p class="mt-1 max-w-4xl text-xs leading-5 text-slate-600">{{ $drilldown['note'] }}</p>
            @endif
        </div>
        <a href="{{ route('operations.owner.scoreboard', ['period' => $periodKey]) }}" class="text-xs font-bold text-slate-700 underline">Close</a>
    </div>
    @if ($drilldown['rows'] === [])
        <p class="px-3 py-4 text-sm text-slate-500">Nothing in this list.</p>
    @else
        <ul class="divide-y divide-slate-200">
            @foreach ($drilldown['rows'] as $row)
                <li class="px-3 py-2">
                    <div class="flex flex-wrap items-baseline justify-between gap-2">
                        @if ($row['url'])
                            <a href="{{ $row['url'] }}" class="text-sm font-black text-slate-950 underline">{{ $row['primary'] }}</a>
                        @else
                            <p class="text-sm font-black text-slate-950">{{ $row['primary'] }}</p>
                        @endif
                        <p class="text-xs text-slate-600">{{ $row['meta'] }}</p>
                    </div>
                    <p class="text-sm text-slate-800">{{ $row['secondary'] }}</p>
                </li>
            @endforeach
        </ul>
    @endif
</section>
