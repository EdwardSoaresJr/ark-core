{{-- Contextual Repair Order footer — disposable projection, not a toolbar --}}
@php
    /** @var \App\Ark\Operations\RepairOrders\RepairOrderFooterProjection $footer */
    $footer = $repairOrderFooter;
    $workflow = $footer->workflow;
    $keyTagUrl = route('operations.repair-orders.print-key-tag', $repairOrder);
    $oilStickerUrl = route('operations.repair-orders.print-oil-change-sticker', $repairOrder);
    $docked = (bool) ($docked ?? false);
@endphp

{{--
  Docked footer lives outside the worksheet Alpine tree (orientation stack).
  Own x-data so @click / PRINT menu bind — otherwise Add Work is a dead button.
--}}
<footer
    @class([
        'ops-ro-footer',
        'ops-ro-footer--docked' => $docked,
    ])
    data-ro-footer
    aria-label="Repair Order actions"
    x-data
>
    <div class="ops-ro-footer__row">
        <div class="ops-ro-footer__workflow">
            @if ($workflow->key !== 'none')
                @if ($workflow->opensModal)
                    <button
                        type="button"
                        class="ops-ro-footer__primary"
                        @if ($workflow->title) title="{{ $workflow->title }}" @endif
                        data-workspace-modal-trigger="add-work"
                        @click="window.dispatchEvent(new CustomEvent('ark-workspace-modal-open', { detail: { task: '{{ $workflow->modalTask }}', invokeEl: $event.currentTarget } }))"
                    >
                        {{ $workflow->label }}
                    </button>
                @elseif ($workflow->opensInNewTab)
                    <a
                        href="{{ $workflow->href }}"
                        target="_blank"
                        rel="noopener"
                        class="ops-ro-footer__primary"
                        @if ($workflow->title) title="{{ $workflow->title }}" @endif
                    >
                        {{ $workflow->label }}
                    </a>
                @else
                    <a
                        href="{{ $workflow->href }}"
                        class="ops-ro-footer__primary"
                        @if ($workflow->title) title="{{ $workflow->title }}" @endif
                    >
                        {{ $workflow->label }}
                    </a>
                @endif
            @endif
        </div>

        <div class="ops-ro-footer__present">
            @foreach ($footer->present as $action)
                @if ($action->opensModal)
                    <button
                        type="button"
                        class="ops-ro-footer__quiet"
                        @if ($action->title) title="{{ $action->title }}" @endif
                        data-workspace-modal-trigger="{{ $action->modalTask }}"
                        @click="window.dispatchEvent(new CustomEvent('ark-workspace-modal-open', { detail: { task: '{{ $action->modalTask }}', invokeEl: $event.currentTarget } }))"
                    >
                        {{ $action->label }}
                    </button>
                @else
                    <a
                        href="{{ $action->href }}"
                        @if ($action->opensInNewTab) target="_blank" rel="noopener" @endif
                        class="ops-ro-footer__quiet"
                        @if ($action->title) title="{{ $action->title }}" @endif
                    >
                        {{ $action->label }}
                    </a>
                @endif
            @endforeach
        </div>

        <div
            class="ops-ro-footer__utilities"
            x-data="{ open: false }"
            @keydown.escape.window="open = false"
        >
            <button
                type="button"
                class="ops-ro-footer__overflow"
                @click="open = ! open"
                :aria-expanded="open"
                aria-haspopup="menu"
            >
                PRINT
            </button>
            <div
                x-show="open"
                x-cloak
                class="ops-ro-footer__menu"
                role="menu"
                @click.outside="open = false"
            >
                @foreach ($footer->utilities as $action)
                    @if ($action->key === 'key_tag')
                        <button
                            type="button"
                            role="menuitem"
                            class="ops-ro-footer__menu-item"
                            @click="open = false; window.arkPrintDocument?.(window.ARK_PRINTERS?.keyTag, @js($keyTagUrl), $event.currentTarget, { document: 'key_tag', resolvePrinter: true })"
                        >
                            {{ $action->label }}
                        </button>
                    @elseif ($action->key === 'oil_sticker')
                        <button
                            type="button"
                            role="menuitem"
                            class="ops-ro-footer__menu-item"
                            @click="open = false; window.arkPrintDocument?.(window.ARK_PRINTERS?.oilSticker, @js($oilStickerUrl), $event.currentTarget, { document: 'oil_change_sticker', resolvePrinter: true })"
                        >
                            {{ $action->label }}
                        </button>
                    @else
                        <a
                            href="{{ $action->href }}"
                            @if ($action->opensInNewTab) target="_blank" rel="noopener" @endif
                            role="menuitem"
                            class="ops-ro-footer__menu-item"
                            @click="open = false"
                        >
                            {{ $action->label }}
                        </a>
                    @endif
                @endforeach
            </div>
        </div>

        <button
            type="button"
            class="ops-ro-footer__top"
            title="Back to top"
            aria-label="Back to top"
            @click="
                const target = document.querySelector('.ops-estimate-workspace')
                    || document.getElementById('review-toolbar')
                    || document.querySelector('[data-worksheet-root]');
                (target || document.documentElement).scrollIntoView({ behavior: 'smooth', block: 'start' });
            "
        >
            <span aria-hidden="true">↑</span>
        </button>
    </div>
</footer>
