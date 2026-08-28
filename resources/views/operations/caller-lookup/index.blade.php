<x-operations.app title="Lookup Caller">
    <section class="ops-index space-y-2">
        <div class="ops-board-shell">
            <div class="ops-page-toolbar">
                <p class="ops-page-toolbar-note">Who is calling and what is going on? Paste a phone number to resolve customer, vehicles, open ROs, and recent conversation.</p>
                <div class="ops-page-toolbar-actions">
                    <a href="{{ route('operations.workboard') }}" class="ops-page-link">Workboard</a>
                    @if ($context?->customer)
                        <a href="{{ route('operations.customers.show', $context->customer) }}" class="ops-page-link">Customer Hub</a>
                    @endif
                </div>
            </div>

            <form method="GET" action="{{ route('operations.caller-lookup') }}" class="ops-board-filters">
                <div class="ops-index-filters ops-index-filters--customer">
                    <div class="min-w-0 flex-1">
                        <label for="caller-phone" class="ops-index-field-label">Phone Number</label>
                        <input
                            id="caller-phone"
                            name="phone"
                            value="{{ $phone }}"
                            type="tel"
                            autofocus
                            autocomplete="off"
                            inputmode="tel"
                            placeholder="719-555-1234"
                            class="ops-index-field min-h-11 sm:min-h-8"
                        >
                    </div>
                    <button type="submit" class="ops-index-btn ops-index-btn--primary min-h-11 sm:min-h-8 lg:self-end">Lookup Caller</button>
                </div>
            </form>
        </div>

        @if ($phone !== '' && $context === null)
            <div class="ops-board-shell px-3 py-2 text-sm font-semibold text-rose-900">
                Enter a valid phone number with at least 7 digits.
            </div>
        @endif

        @if ($context && ! $context->hasMatch())
            <div class="ops-board-shell">
                <div class="px-3 py-3 text-sm text-slate-700">
                    <p class="font-bold text-slate-950">No customer matched {{ $context->displayPhone }}</p>
                    @if ($context->recentConversationMessages->isEmpty())
                        <p class="mt-1 text-xs leading-4 text-slate-500">No conversation history on this number yet.</p>
                    @endif
                </div>
                @if ($context->recentConversationMessages->isNotEmpty())
                    <x-operations.conversation-context-panel
                        :context="$context"
                        :show-customer-header="false"
                        :show-active-vehicles="false"
                        :show-open-repair-orders="false"
                        conversation-label="Recent Conversation"
                        conversation-meta="Unmatched number"
                    />
                @endif
            </div>
        @endif

        @if ($context?->hasMatch())
            <div class="ops-board-shell">
                <x-operations.conversation-context-panel :context="$context" />
            </div>
        @endif
    </section>
</x-operations.app>
