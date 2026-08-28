@php
    /** @var string $title */
    /** @var string|null $subtitle */
    /** @var int $count */
    /** @var string|null $empty */
    /** @var list<array<string, mixed>> $rows */
    /** @var string $rowPartial */
    $tone = $tone ?? 'default';
    $collapsed = $collapsed ?? false;
@endphp

<div class="ops-board-shell overflow-hidden">
    <div @class([
        'border-b px-4 py-3',
        'border-amber-200 bg-amber-50/50' => $tone === 'amber',
        'border-slate-200 bg-slate-50' => $tone !== 'amber',
    ])>
        <div class="flex items-baseline justify-between gap-3">
            <div>
                <h2 class="text-sm font-bold text-slate-900">{{ $title }} ({{ $count }})</h2>
                @if (filled($subtitle ?? null))
                    <p class="mt-0.5 text-xs text-slate-600">{{ $subtitle }}</p>
                @endif
            </div>
        </div>
    </div>

    @if ($rows === [])
        @if (filled($empty))
            <p class="px-4 py-6 text-center text-sm text-slate-600">{{ $empty }}</p>
        @endif
    @else
        <div @class(['divide-y divide-slate-100', 'max-h-96 overflow-y-auto' => $collapsed])>
            @foreach ($rows as $row)
                @include($rowPartial, ['row' => $row])
            @endforeach
        </div>
    @endif
</div>
