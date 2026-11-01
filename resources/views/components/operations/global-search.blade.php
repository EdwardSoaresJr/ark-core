@props(['ui' => 'legacy'])

@can(App\Ark\Runtime\Authorization\ArkCapability::OperationsAccess->value)
@if ($ui === 'tabler')
    @php
        $opsCommandPalette = app(\App\Ark\Operations\Commands\OperationsCommandRegistry::class)
            ->forUserAsArrays(auth()->user());
    @endphp
    <div
        data-ops-global-search
        data-ops-global-search-ui="tabler"
        data-search-url="{{ route('operations.search') }}"
        data-compose-url="{{ route('operations.communications.compose') }}"
        data-csrf="{{ csrf_token() }}"
        hidden
        aria-hidden="true"
    >
        <script type="application/json" data-ops-global-search-commands>@json($opsCommandPalette)</script>
        <div class="modal d-block" tabindex="-1">
            <div class="modal-backdrop show z-0" data-ops-global-search-backdrop></div>
            <div
                class="modal-dialog modal-lg modal-dialog-scrollable"
                data-ops-global-search-dialog
                role="dialog"
                aria-modal="true"
                aria-label="Command palette"
            >
                <div class="modal-content">
                    <div class="modal-header p-2">
                        <label class="visually-hidden" for="ops-global-search-input">Search commands and records</label>
                        <div class="input-icon w-100">
                            <span class="input-icon-addon">
                                <x-tabler.icon name="search" />
                            </span>
                            <input
                                id="ops-global-search-input"
                                type="search"
                                class="form-control form-control-lg"
                                data-ops-global-search-input
                                placeholder="Type a command or search…"
                                autocomplete="off"
                            >
                        </div>
                    </div>
                    <div class="modal-body p-0">
                        <div class="list-group list-group-flush" data-ops-global-search-results>
                            <div class="list-group-item small text-secondary">⌘K · Enter runs · Esc closes</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
@elseif (! request()->attributes->get('operations.tabler_shell_overlays'))
    @php
        $opsCommandPalette = app(\App\Ark\Operations\Commands\OperationsCommandRegistry::class)
            ->forUserAsArrays(auth()->user());
    @endphp
    <div
        class="ops-global-search"
        data-ops-global-search
        data-search-url="{{ route('operations.search') }}"
        data-compose-url="{{ route('operations.communications.compose') }}"
        data-csrf="{{ csrf_token() }}"
        hidden
        aria-hidden="true"
    >
        <script type="application/json" data-ops-global-search-commands>@json($opsCommandPalette)</script>
        <div class="ops-global-search__backdrop" data-ops-global-search-backdrop></div>
        <div
            class="ops-global-search__dialog"
            data-ops-global-search-dialog
            role="dialog"
            aria-modal="true"
            aria-label="Command palette"
        >
            <label class="sr-only" for="ops-global-search-input">Search commands and records</label>
            <input
                id="ops-global-search-input"
                type="search"
                class="ops-global-search__input"
                data-ops-global-search-input
                placeholder="Type a command or search…"
                autocomplete="off"
            >
            <div class="ops-global-search__results" data-ops-global-search-results>
                <p class="ops-global-search__hint">⌘K · Enter runs · Esc closes</p>
            </div>
        </div>
    </div>
@endif
@endcan
