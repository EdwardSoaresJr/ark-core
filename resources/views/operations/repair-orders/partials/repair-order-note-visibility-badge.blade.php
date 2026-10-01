@php
    $audience = $audience ?? [
        'advisor' => true,
        'technician' => (bool) ($private ?? true),
        'customer' => ! (bool) ($private ?? true),
    ];
    $size = $size ?? 'sm';

    $labels = [];
    if ($audience['advisor'] ?? false) {
        $labels[] = ['label' => 'Advisor', 'class' => 'ops-note-visibility--advisor border-emerald-300 bg-emerald-100 text-emerald-900'];
    }
    if ($audience['technician'] ?? false) {
        $labels[] = ['label' => 'Technician', 'class' => 'ops-note-visibility--technician border-sky-300 bg-sky-100 text-sky-900'];
    }
    if ($audience['customer'] ?? false) {
        $labels[] = ['label' => 'Customer', 'class' => 'ops-note-visibility--customer border-rose-300 bg-rose-100 text-rose-900'];
    }
    if ($labels === []) {
        $labels[] = ['label' => 'Advisor', 'class' => 'ops-note-visibility--advisor border-emerald-300 bg-emerald-100 text-emerald-900'];
    }
@endphp

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
