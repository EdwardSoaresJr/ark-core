@if ($visible)
    <details class="ops-ecosystem-switcher">
        <summary class="ops-ecosystem-switcher__trigger" aria-label="ARK ecosystem products">
            <img
                src="{{ \App\Support\Branding\Branding::favicon('16') }}"
                alt=""
                class="ops-ecosystem-switcher__mark"
                width="16"
                height="16"
            >
            <span class="ops-ecosystem-switcher__label">ARK</span>
            <svg class="ops-ecosystem-switcher__chevron" aria-hidden="true" viewBox="0 0 20 20" fill="none">
                <path d="M5 7.5l5 5 5-5" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" />
            </svg>
        </summary>
        <div class="ops-ecosystem-switcher__menu" role="menu">
            @foreach ($items as $item)
                @if ($item['current'])
                    <span class="ops-ecosystem-switcher__item ops-ecosystem-switcher__item--current" role="menuitem">
                        {{ $item['label'] }}
                    </span>
                @else
                    <a
                        href="{{ $item['url'] }}"
                        class="ops-ecosystem-switcher__item"
                        role="menuitem"
                        @if ($item['external']) target="_blank" rel="noopener noreferrer" @endif
                    >
                        {{ $item['label'] }}
                    </a>
                @endif
            @endforeach
        </div>
    </details>
@endif
