@props([
    'presentation' => null,
    'variant' => 'portal',
])

@if (is_array($presentation) && (($presentation['lines'] ?? []) !== []))
    @php
        $kind = (string) ($presentation['kind'] ?? 'generic');
        $title = match ($kind) {
            'brake_pads' => 'Brake',
            'tire_tread' => 'Tire',
            'tire_psi' => 'Pressure',
            default => 'Measurements',
        };
        $isPrint = $variant === 'print';
    @endphp

    <div @class([
        'mt-3 rounded-lg border border-slate-200 bg-slate-50 px-3 py-2.5',
        'measurement-box' => $isPrint,
    ])>
        <p @class([
            'text-[11px] font-bold uppercase tracking-wide text-slate-500',
            'measurement-title' => $isPrint,
        ])>{{ $title }}</p>
        <div @class([
            'mt-1.5 space-y-0.5 text-sm font-semibold text-slate-900',
            'measurement-lines' => $isPrint,
        ])>
            @foreach ($presentation['lines'] as $line)
                <p>{{ $line }}</p>
            @endforeach
        </div>
    </div>
@endif
