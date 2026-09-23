@php
    $laborGuides = $laborGuides ?? [];
    $partsCatalogs = collect($partsCatalogs ?? [])
        ->filter(fn (array $catalog): bool => filled($catalog['label'] ?? null))
        ->values();
    $composeActions = [
        ['task' => 'labor', 'lineType' => 'labor', 'icon' => 'labor', 'label' => 'Labor', 'tone' => 'labor'],
        ['task' => 'part', 'lineType' => 'part', 'icon' => 'part', 'label' => 'Part', 'tone' => 'part'],
        ['task' => 'note', 'lineType' => 'note', 'icon' => 'note', 'label' => 'Note', 'tone' => 'note'],
        ['task' => 'sublet', 'lineType' => 'sublet', 'icon' => 'sublet', 'label' => 'Sublet', 'tone' => 'sublet'],
        ['task' => 'saved-work', 'lineType' => null, 'icon' => 'saved-work', 'label' => 'Common Job', 'tone' => 'saved-work'],
        ['task' => 'evidence', 'lineType' => null, 'icon' => 'evidence', 'label' => 'Photo', 'tone' => 'evidence'],
    ];
@endphp

<div class="ops-ro-footer__compose" data-ro-footer-compose @if ($laborGuides !== []) data-toolbar-group="labor-guide" @endif>
        <div class="ops-ro-footer__compose-track" data-ro-footer-compose-track>
            @foreach ($laborGuides as $guide)
                <button
                    type="button"
                    class="ops-ro-footer__compose-btn ops-ro-footer__compose-btn--guide"
                    data-ro-footer-compose-item
                    data-compose-action="labor-guide"
                    data-guide-key="{{ $guide['key'] }}"
                    data-label="{{ $guide['label'] }}"
                    title="{{ $guide['title'] ?? $guide['label'] }}"
                    @disabled(($guide['kind'] ?? '') !== 'rte' && ! ($guide['can_open'] ?? false))
                >
                    {{ $guide['label'] }}
                </button>
            @endforeach

            @foreach ($composeActions as $action)
                <button
                    type="button"
                    class="ops-ro-footer__compose-btn ops-ro-footer__compose-btn--{{ $action['tone'] }}"
                    data-ro-footer-compose-item
                    data-compose-action="compose"
                    data-compose-task="{{ $action['task'] }}"
                    @if ($action['lineType']) data-line-type="{{ $action['lineType'] }}" @endif
                    data-label="{{ $action['label'] }}"
                    aria-label="Add {{ $action['label'] }}"
                    title="Add {{ $action['label'] }}"
                >
                    @include('operations.repair-orders.partials.workspace-modal.compose-icon', ['icon' => $action['icon']])
                    <span>{{ $action['label'] }}</span>
                </button>
            @endforeach

            @foreach ($partsCatalogs as $catalog)
                <button
                    type="button"
                    class="ops-ro-footer__compose-btn ops-ro-footer__compose-btn--guide"
                    data-ro-footer-compose-item
                    data-compose-action="parts-catalog"
                    data-catalog-key="{{ $catalog['key'] }}"
                    data-label="{{ $catalog['label'] }}"
                    title="{{ $catalog['open_label'] ?? $catalog['label'] }}"
                    @disabled(! ($catalog['can_open'] ?? false))
                >
                    {{ $catalog['label'] }}
                </button>
            @endforeach

            <div class="ops-ro-footer__compose-more" data-ro-footer-compose-more hidden>
                <button
                    type="button"
                    class="ops-ro-footer__compose-btn ops-ro-footer__compose-btn--more"
                    data-ro-footer-compose-more-toggle
                    aria-haspopup="menu"
                    aria-expanded="false"
                >
                    More
                </button>
                <div class="ops-ro-footer__menu" data-ro-footer-compose-menu role="menu" hidden></div>
            </div>
        </div>
</div>
