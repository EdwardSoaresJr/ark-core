{{-- Contextual Repair Order footer - disposable projection, not a toolbar --}}
@php
    /** @var \App\Ark\Operations\RepairOrders\RepairOrderFooterProjection $footer */
    $footer = $repairOrderFooter;
    $workflow = $footer->workflow;
    $keyTagUrl = route('operations.repair-orders.print-key-tag', $repairOrder);
    $oilStickerUrl = route('operations.repair-orders.print-oil-change-sticker', $repairOrder);
    $docked = (bool) ($docked ?? false);
@endphp

{{-- Own x-data so @click and the PRINT menu bind wherever the dock renders. --}}
<footer
    @class([
        'border-top mt-2 pt-2' => $docked,
    ])
    data-ro-footer
    @if ($docked) data-ro-footer-docked @endif
    aria-label="Repair Order actions"
    x-data
>
    <div class="d-flex flex-wrap align-items-center gap-2">
        <div class="d-flex align-items-center gap-2">
            @if ($workflow->key !== 'none')
                @if ($workflow->opensModal)
                    <button
                        type="button"
                        class="btn btn-primary btn-sm"
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
                        class="btn btn-primary btn-sm"
                        @if ($workflow->title) title="{{ $workflow->title }}" @endif
                    >
                        {{ $workflow->label }}
                    </a>
                @else
                    <a
                        href="{{ $workflow->href }}"
                        class="btn btn-primary btn-sm"
                        @if ($workflow->title) title="{{ $workflow->title }}" @endif
                    >
                        {{ $workflow->label }}
                    </a>
                @endif
            @endif
        </div>

        <div class="d-flex flex-wrap align-items-center gap-1 flex-fill">
            @foreach ($footer->present as $action)
                @if ($action->opensModal)
                    <button
                        type="button"
                        class="btn btn-ghost-secondary btn-sm"
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
                        class="btn btn-ghost-secondary btn-sm"
                        @if ($action->title) title="{{ $action->title }}" @endif
                    >
                        {{ $action->label }}
                    </a>
                @endif
            @endforeach
        </div>

        <div
            class="position-relative ms-auto"
            x-data="{ open: false }"
            @keydown.escape.window="open = false"
            data-ro-footer-print
        >
            <button
                type="button"
                class="btn btn-ghost-secondary btn-sm"
                @click="open = ! open"
                :aria-expanded="open"
                aria-haspopup="menu"
            >
                <x-tabler.icon name="printer" />
                PRINT
            </button>
            <div
                class="dropdown-menu dropdown-menu-end bottom-100 end-0 mb-1"
                :class="{ show: open }"
                role="menu"
                @click.outside="open = false"
            >
                @foreach ($footer->utilities as $action)
                    @if ($action->key === 'key_tag')
                        @if (filled($action->blockedReason))
                            <button
                                type="button"
                                role="menuitem"
                                class="dropdown-item flex-column align-items-start"
                                @click="open = false; window.alert(@js($action->blockedReason))"
                            >
                                {{ $action->label }}
                                <span class="small text-secondary text-wrap">{{ $action->blockedReason }}</span>
                            </button>
                        @else
                            <button
                                type="button"
                                role="menuitem"
                                class="dropdown-item"
                                @click="open = false; window.arkPrintDocument?.(window.ARK_PRINTERS?.keyTag, @js($keyTagUrl), $event.currentTarget, { document: 'key_tag', resolvePrinter: true })"
                            >
                                {{ $action->label }}
                            </button>
                        @endif
                    @elseif ($action->key === 'oil_sticker')
                        <button
                            type="button"
                            role="menuitem"
                            class="dropdown-item"
                            @click="open = false; window.arkPrintDocument?.(window.ARK_PRINTERS?.oilSticker, @js($oilStickerUrl), $event.currentTarget, { document: 'oil_change_sticker', resolvePrinter: true })"
                        >
                            {{ $action->label }}
                        </button>
                    @else
                        <a
                            href="{{ $action->href }}"
                            @if ($action->opensInNewTab) target="_blank" rel="noopener" @endif
                            role="menuitem"
                            class="dropdown-item"
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
            class="btn btn-icon btn-sm"
            title="Back to top"
            aria-label="Back to top"
            data-ro-footer-top
            @click="
                const target = document.querySelector('[data-worksheet-root]')
                    || document.getElementById('review-toolbar');
                (target || document.documentElement).scrollIntoView({ behavior: 'smooth', block: 'start' });
            "
        >
            <x-tabler.icon name="arrow-up" />
        </button>
    </div>
</footer>
