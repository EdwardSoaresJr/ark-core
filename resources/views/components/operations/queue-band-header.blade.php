@props([
    'id' => null,
    'label',
    'description' => null,
    'count' => null,
    'variant' => 'lane',
    'href' => null,
    'linkTitle' => null,
])

<div @class([
    'ops-queue-band-header' => $variant !== 'home',
    'ops-pressure-band-header' => $variant === 'lane',
    'ops-queue-band-header--section' => $variant === 'section',
    'ops-home-band-header' => $variant === 'home',
])>
    <div class="min-w-0 flex-1">
        @if (filled($href))
            <a
                href="{{ $href }}"
                class="group inline-flex max-w-full items-center gap-1.5 no-underline"
                @if (filled($linkTitle)) title="{{ $linkTitle }}" @endif
            >
                @if ($variant === 'lane')
                    <h2
                        @if ($id) id="{{ $id }}" @endif
                        class="truncate text-[11px] font-semibold uppercase leading-4 tracking-[0.08em] text-slate-600 group-hover:text-ops-accent-900"
                    >
                        {{ $label }}
                    </h2>
                @else
                    <h3
                        @if ($id) id="{{ $id }}" @endif
                        class="truncate text-sm font-black text-slate-950 group-hover:text-ops-accent-900"
                    >
                        {{ $label }}
                        @if ($variant === 'home' && $count !== null && (int) $count > 0)
                            <x-operations.pressure-count :count="$count" inline />
                        @endif
                    </h3>
                @endif
                <span class="shrink-0 text-[10px] font-bold uppercase tracking-wide text-slate-400 group-hover:text-ops-accent-700" aria-hidden="true">Open</span>
            </a>
        @else
            @if ($variant === 'lane')
                <h2
                    @if ($id) id="{{ $id }}" @endif
                    class="truncate text-[11px] font-semibold uppercase leading-4 tracking-[0.08em] text-slate-600"
                >
                    {{ $label }}
                </h2>
            @else
                <h3
                    @if ($id) id="{{ $id }}" @endif
                    class="text-sm font-black text-slate-950"
                >
                    {{ $label }}
                    @if ($count !== null && (int) $count > 0 && ($variant === 'home' || $variant === 'section'))
                        <x-operations.pressure-count :count="$count" inline />
                    @endif
                </h3>
            @endif
        @endif

        @if (filled($description))
            <p @class([
                'truncate text-[11px] leading-3 text-slate-400' => $variant === 'lane',
                'mt-0.5 text-[11px] leading-4 text-slate-500' => $variant === 'section' || $variant === 'home',
                'ops-pressure-band-description' => $variant === 'lane',
            ])>{{ $description }}</p>
        @endif
    </div>

    @if ($variant === 'lane' && $count !== null)
        <x-operations.pressure-count :count="$count" />
    @endif

    @isset($actions)
        <div class="flex shrink-0 flex-wrap items-center gap-2">
            {{ $actions }}
        </div>
    @endisset
</div>
