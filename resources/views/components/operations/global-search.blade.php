@can(App\Ark\Runtime\Authorization\ArkCapability::OperationsAccess->value)
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
@endcan
