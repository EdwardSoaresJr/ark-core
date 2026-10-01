@php
    /** @var \App\Ark\Operations\Conversations\Conversation $conversation */
    /** @var \App\Ark\Operations\Conversations\CustomerCallContext|null $context */
    /** @var \Illuminate\Support\Collection<int, \App\Ark\Operations\Conversations\ConversationMessage> $messages */

    $matched = $context?->hasMatch() ?? false;
    $customer = $context?->customer;
@endphp

<x-operations.app title="Reply · {{ $displayPhone }}">
    <section class="ops-index space-y-2">
        <div class="ops-board-shell">
            <div class="ops-page-toolbar">
                <div class="min-w-0">
                    <p class="text-sm font-black text-slate-950">{{ $matched ? $customer->name : 'Unknown' }}</p>
                    <p class="mt-0.5 text-[11px] leading-4 text-slate-500">{{ $displayPhone }} · reply in thread</p>
                </div>
                <div class="ops-page-toolbar-actions">
                    <a href="{{ route('operations.index') }}" class="ops-page-link">Back to Work</a>
                    @if ($matched && $customer)
                        <a href="{{ route('operations.customers.show', $customer) }}" class="ops-page-link">Customer Hub</a>
                    @elseif (filled($lookupUrl))
                        <a href="{{ $lookupUrl }}" class="ops-page-link">Lookup</a>
                    @endif
                    @if (filled($intakeUrl))
                        <a href="{{ $intakeUrl }}" class="ops-page-link">Start Check In</a>
                    @endif
                </div>
            </div>
        </div>

        <div class="ops-board-shell">
            <div class="ops-index-results-head">
                <span>Conversation</span>
                <span class="normal-case tracking-normal text-slate-400">{{ $matched ? 'Known customer' : 'Unmatched number' }}</span>
            </div>

            <div id="conversation-messages-contact" class="divide-y divide-slate-100">
                @forelse ($messages as $message)
                    <x-operations.conversation-message :message="$message" class="border-t border-slate-100" />
                @empty
                    <div class="px-3 py-2 text-xs leading-4 text-slate-500">No conversation history yet.</div>
                @endforelse
            </div>

            <x-operations.conversation-contact-quick-reply
                :conversation="$conversation"
                :display-phone="$displayPhone"
                :has-conversation-history="$messages->isNotEmpty()"
            />
        </div>
    </section>
</x-operations.app>
