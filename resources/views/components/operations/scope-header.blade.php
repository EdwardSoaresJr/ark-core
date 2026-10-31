@props([
    'title',
    'total' => null,
    'eyebrow' => null,
    'eyebrowClass' => null,
    'continuityRegion' => null,
    'ui' => 'legacy',
])

@if ($ui === 'tabler')
    <div {{ $attributes->class('card-header align-items-start flex-wrap gap-2') }}>
        <div class="flex-fill" style="min-width: 0">
            @if (filled($eyebrow))
                <div @class(['subheader mb-1', $eyebrowClass])>{{ $eyebrow }}</div>
            @endif
            <h3 class="card-title">{{ $title }}</h3>
            @isset($subline)
                {{ $subline }}
            @endisset
        </div>
        @if (filled($total) || isset($status))
            <div class="d-flex flex-column align-items-end gap-1 ms-auto">
                @if (filled($total))
                    <div
                        class="h3 mb-0"
                        @if ($continuityRegion) id="{{ $continuityRegion }}" data-continuity-region="concern-money" @endif
                    >{{ $total }}</div>
                @endif
                @isset($status)
                    {{ $status }}
                @endisset
            </div>
        @endif
    </div>
    @isset($toolbar)
        <div class="card-body border-bottom py-2 d-flex flex-wrap align-items-center gap-2">
            {{ $toolbar }}
        </div>
    @endisset
@else
<div {{ $attributes->class('ops-worksheet-concern-header') }}>
    <div class="ops-scope-header-grid">
        <div class="ops-scope-header-identity">
            @if (filled($eyebrow))
                <p @class(['ops-scope-header-eyebrow', $eyebrowClass])>{{ $eyebrow }}</p>
            @endif
            <h2 class="ops-scope-header-title">{{ $title }}</h2>
        </div>
        @if (filled($total) || isset($status))
            <div class="ops-scope-header-end">
                @if (filled($total))
                    <p
                        class="ops-scope-header-meta ops-money"
                        @if ($continuityRegion) id="{{ $continuityRegion }}" data-continuity-region="concern-money" @endif
                    >{{ $total }}</p>
                @endif
                @isset($status)
                    <div class="ops-scope-header-status">
                        {{ $status }}
                    </div>
                @endisset
            </div>
        @endif
        @isset($subline)
            <div class="ops-scope-header-subline">
                {{ $subline }}
            </div>
        @endisset
    </div>
    @isset($toolbar)
        <div class="ops-scope-header-toolbar">
            {{ $toolbar }}
        </div>
    @endisset
</div>
@endif
