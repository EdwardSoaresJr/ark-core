<x-operations.app title="Lookup Caller" ui="tabler">
    <x-tabler.page
        layout="focused"
        title="Lookup caller"
        subtitle="Who is calling and what is going on? Paste a phone number to resolve customer, vehicles, open ROs, and recent conversation."
    >
        <x-slot:actions>
            <x-tabler.page-action :href="route('operations.workboard')" icon="layout-grid" label="Job Board" />
            @if ($context?->customer)
                <x-tabler.page-action :href="route('operations.customers.show', $context->customer)" icon="user" label="Customer Hub" />
            @endif
        </x-slot:actions>

        <div class="d-flex flex-column gap-3" data-caller-lookup>
            <div class="card">
                <div class="card-body p-2 p-md-3">
                    <form method="GET" action="{{ route('operations.caller-lookup') }}" role="search" aria-label="Lookup caller">
                        <label for="caller-phone" class="form-label d-none d-md-block">Phone Number</label>
                        <div class="input-group">
                            <input
                                id="caller-phone"
                                name="phone"
                                value="{{ $phone }}"
                                type="tel"
                                autofocus
                                autocomplete="off"
                                inputmode="tel"
                                placeholder="719-555-1234"
                                aria-label="Phone Number"
                                class="form-control"
                            >
                            <button type="submit" class="btn btn-primary">Lookup Caller</button>
                        </div>
                    </form>
                </div>
            </div>

            @if ($phone !== '' && $context === null)
                <div class="alert alert-danger mb-0" role="alert">
                    Enter a valid phone number with at least 7 digits.
                </div>
            @endif

            {{-- The conversation context panel is shared with the communications screens and stays legacy until they migrate. --}}
            @if ($context && ! $context->hasMatch())
                <div class="card" data-caller-lookup-unmatched>
                    <div class="card-body p-2 p-md-3">
                        <h2 class="card-title mb-1">No customer matched {{ $context->displayPhone }}</h2>
                        @if ($context->recentConversationMessages->isEmpty())
                            <p class="text-secondary mb-0">No conversation history on this number yet.</p>
                        @endif
                    </div>
                    @if ($context->recentConversationMessages->isNotEmpty())
                        <div class="border-top">
                            <x-operations.conversation-context-panel
                                :context="$context"
                                :show-customer-header="false"
                                :show-active-vehicles="false"
                                :show-open-repair-orders="false"
                                conversation-label="Recent Conversation"
                                conversation-meta="Unmatched number"
                                ui="tabler"
                            />
                        </div>
                    @endif
                </div>
            @endif

            @if ($context?->hasMatch())
                <div class="card overflow-hidden" data-caller-lookup-match>
                    <x-operations.conversation-context-panel :context="$context" ui="tabler" />
                </div>
            @endif
        </div>
    </x-tabler.page>
</x-operations.app>
