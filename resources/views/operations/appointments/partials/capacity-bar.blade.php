@props(['row'])

<li>
    <div class="flex items-baseline justify-between gap-2">
        <p class="truncate text-[11px] font-bold text-slate-900">{{ $row['label'] }}</p>
        <p class="shrink-0 tabular-nums text-[10px] font-semibold {{ ! empty($row['over_warn']) ? 'text-amber-800' : 'text-slate-500' }}">
            {{ $row['assigned_label'] }} / {{ $row['capacity_label'] }}
        </p>
    </div>
    <div class="ops-capacity-bar mt-0.5" role="img" aria-label="{{ $row['label'] }} {{ $row['assigned_label'] }} of {{ $row['capacity_label'] }}">
        <div
            class="ops-capacity-bar__fill {{ ! empty($row['over_warn']) ? 'ops-capacity-bar__fill--warn' : '' }} {{ ! empty($row['over_capacity']) ? 'ops-capacity-bar__fill--over' : '' }}"
            style="width: {{ $row['bar_percent'] }}%"
        ></div>
    </div>
    <p class="mt-0.5 text-[10px] text-slate-500">{{ $row['remaining_hours'] }}h remaining</p>
</li>
