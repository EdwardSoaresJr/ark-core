{{-- Contextual Repair Order footer - disposable projection, not a toolbar --}}
@php
    /** @var \App\Ark\Operations\RepairOrders\RepairOrderFooterProjection $footer */
    $footer = $repairOrderFooter;
    $workflow = $footer->workflow;
    $docked = (bool) ($docked ?? false);
    $estimatingTools = $estimatingTools ?? null;
    $printItems = App\Ark\Operations\RepairOrders\RepairOrderPrintMenuProjection::items(
        $repairOrder,
        $financial ?? null,
        auth()->user(),
    );
@endphp

{{-- Own x-data so @click binds wherever the dock renders. Print uses Bootstrap dropup (not Alpine open) so the toggle cannot be cancelled by click.outside. --}}
<footer
    @class([
        'border-top mt-1 pt-1 mt-md-2 pt-md-2' => $docked,
    ])
    data-ro-footer
    @if ($docked) data-ro-footer-docked @endif
    aria-label="Repair Order actions"
    x-data
>
    <div @class([
        'd-flex align-items-center',
        'flex-wrap gap-2' => $estimatingTools === null,
        'flex-nowrap flex-md-wrap gap-1 gap-sm-2' => $estimatingTools !== null,
    ])>
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

        @if ($estimatingTools !== null)
            @include('operations.repair-orders.partials.repair-order-footer-estimating-tools', [
                'tools' => $estimatingTools,
                'presentActions' => $footer->present,
            ])
        @endif

        <div @class([
            'flex-wrap align-items-center gap-1 flex-fill',
            'd-flex' => $estimatingTools === null,
            'd-none d-md-flex' => $estimatingTools !== null,
        ])>
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
            class="dropup ms-auto"
            data-ro-footer-print
            data-ro-print-menu
        >
            <button
                type="button"
                class="btn btn-ghost-secondary btn-sm dropdown-toggle"
                data-bs-toggle="dropdown"
                data-bs-display="static"
                aria-expanded="false"
                aria-haspopup="menu"
                aria-label="Print"
                title="Print"
            >
                <x-tabler.icon name="printer" />
                <span @class(['d-none d-sm-inline' => $estimatingTools !== null])>PRINT</span>
            </button>
            <div
                class="dropdown-menu dropdown-menu-end"
                role="menu"
                data-ro-footer-print-menu
                style="max-height: min(70vh, 24rem); overflow-y: auto;"
            >
                @include('operations.repair-orders.partials.repair-order-print-menu-items', [
                    'printItems' => $printItems,
                    'closeExpr' => '',
                ])
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
