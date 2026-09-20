@props([
    'title',
    'total' => null,
    'eyebrow' => null,
    'eyebrowClass' => null,
])

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
                    <p class="ops-scope-header-meta ops-money">{{ $total }}</p>
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
