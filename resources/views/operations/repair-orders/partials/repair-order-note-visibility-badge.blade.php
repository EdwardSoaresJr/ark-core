@php
    $audience = $audience ?? [
        'advisor' => true,
        'technician' => (bool) ($private ?? true),
        'customer' => ! (bool) ($private ?? true),
    ];
    $size = $size ?? 'sm';
    $ui = $ui ?? 'legacy';

    $labels = [];
    if ($audience['advisor'] ?? false) {
        $labels[] = ['label' => 'Advisor', 'color' => 'green', 'class' => 'ops-note-visibility--advisor border-emerald-300 bg-emerald-100 text-emerald-900'];
    }
    if ($audience['technician'] ?? false) {
        $labels[] = ['label' => 'Technician', 'color' => 'azure', 'class' => 'ops-note-visibility--technician border-sky-300 bg-sky-100 text-sky-900'];
    }
    if ($audience['customer'] ?? false) {
        $labels[] = ['label' => 'Customer', 'color' => 'pink', 'class' => 'ops-note-visibility--customer border-rose-300 bg-rose-100 text-rose-900'];
    }
    if ($labels === []) {
        $labels[] = ['label' => 'Advisor', 'color' => 'green', 'class' => 'ops-note-visibility--advisor border-emerald-300 bg-emerald-100 text-emerald-900'];
    }
@endphp

@if ($ui === 'tabler')
    @foreach ($labels as $chip)
        <span class="badge bg-{{ $chip['color'] }}-lt">{{ $chip['label'] }}</span>
    @endforeach
@else
<span class="inline-flex flex-wrap items-center gap-1">
    @foreach ($labels as $chip)
        <span @class([
            'ops-note-visibility inline-flex items-center gap-1 rounded-sm border px-1.5 py-0.5 text-[10px] font-extrabold uppercase tracking-wide',
            $chip['class'],
            'ops-note-visibility--md text-[11px]' => $size === 'md',
        ])>
            <span>{{ $chip['label'] }}</span>
        </span>
    @endforeach
</span>
@endif
