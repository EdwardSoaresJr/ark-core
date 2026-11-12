{{-- Shared Print menu choices — consumed by header dropdown and sticky-footer dropup. --}}
@php
    /** @var list<array{key: string, label: string, href: ?string, opens_in_new_tab: bool, is_print: bool, print_document: ?string, blocked_reason: ?string, cache_bust_estimate_pdf: bool}> $printItems */
    $printItems = $printItems ?? [];
    $closeExpr = $closeExpr ?? 'open = false';
@endphp

@foreach ($printItems as $action)
    @if (($action['is_print'] ?? false) && ($action['print_document'] ?? null) === 'key_tag')
        @if (filled($action['blocked_reason'] ?? null))
            <button
                type="button"
                role="menuitem"
                class="dropdown-item flex-column align-items-start"
                data-ro-print-item="{{ $action['key'] }}"
                @click="{{ $closeExpr }}; window.alert(@js($action['blocked_reason']))"
            >
                {{ $action['label'] }}
                <span class="d-block small text-secondary text-wrap">{{ $action['blocked_reason'] }}</span>
            </button>
        @else
            <button
                type="button"
                role="menuitem"
                class="dropdown-item"
                data-ro-print-item="{{ $action['key'] }}"
                @click="{{ $closeExpr }}; window.arkPrintDocument?.(window.ARK_PRINTERS?.keyTag, @js($action['href']), $event.currentTarget, { document: 'key_tag', resolvePrinter: true })"
            >
                {{ $action['label'] }}
            </button>
        @endif
    @elseif (($action['is_print'] ?? false) && ($action['print_document'] ?? null) === 'oil_change_sticker')
        <button
            type="button"
            role="menuitem"
            class="dropdown-item"
            data-ro-print-item="{{ $action['key'] }}"
            @click="{{ $closeExpr }}; window.arkPrintDocument?.(window.ARK_PRINTERS?.oilSticker, @js($action['href']), $event.currentTarget, { document: 'oil_change_sticker', resolvePrinter: true })"
        >
            {{ $action['label'] }}
        </button>
    @elseif ($action['cache_bust_estimate_pdf'] ?? false)
        <a
            href="{{ $action['href'] }}"
            @if ($action['opens_in_new_tab'] ?? false) target="_blank" rel="noopener" @endif
            role="menuitem"
            class="dropdown-item"
            data-ro-print-item="{{ $action['key'] }}"
            @click="{{ $closeExpr }}; $event.currentTarget.href = @js($action['href']) + '?t=' + Date.now()"
        >
            {{ $action['label'] }}
        </a>
    @else
        <a
            href="{{ $action['href'] }}"
            @if ($action['opens_in_new_tab'] ?? false) target="_blank" rel="noopener" @endif
            role="menuitem"
            class="dropdown-item"
            data-ro-print-item="{{ $action['key'] }}"
            @click="{{ $closeExpr }}"
        >
            {{ $action['label'] }}
        </a>
    @endif
@endforeach
