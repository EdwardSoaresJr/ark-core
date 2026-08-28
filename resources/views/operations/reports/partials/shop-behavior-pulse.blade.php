<div class="overflow-hidden border border-slate-300 bg-white">
    <div class="border-b border-slate-200 bg-slate-50 px-3 py-2">
        <p class="text-[11px] font-bold uppercase tracking-[0.08em] text-slate-500">Shop Behavior Pulse</p>
        <p class="text-xs text-slate-400">What is stuck in the building right now — counts only, not revenue.</p>
    </div>
    <div class="grid gap-px border-t border-slate-200 bg-slate-200 sm:grid-cols-2 lg:grid-cols-5">
        @foreach ($shopBehaviorPulse as $row)
            <div class="bg-white px-3 py-2">
                <div class="flex items-baseline justify-between gap-2">
                    <p class="text-[10px] font-bold uppercase tracking-wide text-slate-400">{{ $row['label'] }}</p>
                    <p class="text-lg font-black tabular-nums text-slate-950">{{ $row['count'] }}</p>
                </div>
                <p class="mt-0.5 text-[11px] leading-4 text-slate-500">{{ $row['hint'] }}</p>
            </div>
        @endforeach
    </div>
</div>

@if (($frontDoorLanding['total'] ?? 0) > 0)
    <div class="overflow-hidden border border-slate-300 bg-white">
        <div class="border-b border-slate-200 bg-slate-50 px-3 py-2">
            <p class="text-[11px] font-bold uppercase tracking-[0.08em] text-slate-500">Front Door Experiment</p>
            <p class="text-xs text-slate-400">First operational surface opened per session — last {{ $frontDoorLanding['days'] }} days.</p>
        </div>
        <div class="grid gap-px border-t border-slate-200 bg-slate-200 sm:grid-cols-3">
            <div class="bg-white px-3 py-2">
                <p class="text-[10px] font-bold uppercase tracking-wide text-slate-400">Work first</p>
                <p class="mt-0.5 text-lg font-black tabular-nums text-slate-950">{{ $frontDoorLanding['attention'] }}</p>
            </div>
            <div class="bg-white px-3 py-2">
                <p class="text-[10px] font-bold uppercase tracking-wide text-slate-400">Workboard first</p>
                <p class="mt-0.5 text-lg font-black tabular-nums text-slate-950">{{ $frontDoorLanding['workboard'] }}</p>
            </div>
            <div class="bg-white px-3 py-2">
                <p class="text-[10px] font-bold uppercase tracking-wide text-slate-400">Work share</p>
                <p class="mt-0.5 text-lg font-black tabular-nums text-slate-950">
                    @if ($frontDoorLanding['attention_share'] !== null)
                        {{ $frontDoorLanding['attention_share'] }}%
                    @else
                        —
                    @endif
                </p>
            </div>
        </div>
    </div>
@endif
