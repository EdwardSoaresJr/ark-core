@php
    $initialCommsFilter = in_array(request()->query('comms'), ['all', 'call', 'text', 'email', 'portal', 'logged'], true)
        ? (string) request()->query('comms')
        : (request()->query('compose') === 'text' ? 'text' : 'all');
    $commsFilters = array_filter([
        'all' => 'All',
        'call' => 'Calls',
        'text' => 'Text',
        'email' => 'Email',
        'portal' => $hubCommsCounts['portal'] > 0 ? 'Portal' : null,
        'logged' => $hubCommsCounts['logged'] > 0 ? 'Logged' : null,
    ]);
@endphp

<div
    id="customer-communication"
    x-data="arkCustomerHubComms(@js([
        'initialFilter' => $initialCommsFilter,
        'counts' => $hubCommsCounts,
        'customerId' => $customer->id,
        'updatesUrl' => route('operations.customers.hub-comms.updates', $customer),
        'messagesListId' => 'conversation-messages-relationship',
    ]))"
    x-init="init()"
>
    <div class="card-body border-bottom py-2">
        <h3 class="card-title mb-0">Communications</h3>
        <div class="text-secondary small">One timeline for calls, text, and email - filter by type when you need to focus.</div>
        <div class="btn-list mt-2" role="group" aria-label="Communication type filters">
            @foreach ($commsFilters as $filterKey => $filterLabel)
                <button
                    type="button"
                    class="btn btn-sm"
                    :class="isActive('{{ $filterKey }}') ? 'btn-primary' : ''"
                    :aria-pressed="isActive('{{ $filterKey }}').toString()"
                    x-on:click="setFilter('{{ $filterKey }}')"
                >{{ $filterLabel }} · {{ $hubCommsCounts[$filterKey] }}</button>
            @endforeach
        </div>
    </div>

    {{-- The composer and timeline rows are shared with the communications and repair-order screens. --}}
    <div class="ark-legacy bg-white text-dark">
        <x-operations.conversation-quick-reply
            :customer="$customer"
            :messages-list-ids="['conversation-messages-relationship']"
            :open-repair-orders="$callContext->openRepairOrders"
            :has-conversation-history="$hubCommsCounts['text'] > 0"
            always-open
            keep-open-after-send
            class="border-b border-slate-200 bg-white"
        />

        <div id="conversation-messages-relationship" class="divide-y divide-slate-100">
            @forelse ($hubCommsTimeline as $event)
                <div
                    data-conversation-row
                    data-filter="{{ $event->hubFilter() }}"
                    @if (filled($event->metadata['platform_message_public_id'] ?? null))
                        data-platform-message-id="{{ $event->metadata['platform_message_public_id'] }}"
                    @endif
                    data-occurred-at="{{ $event->occurredAt->utc()->toIso8601String() }}"
                >
                    @include('operations.timeline.partials.hub-event-row', ['event' => $event])
                </div>
            @empty
                <div data-conversation-empty class="px-3 py-2 text-xs leading-4 text-slate-500">
                    No communications recorded for this customer yet.
                </div>
            @endforelse

            @if ($hubCommsTimeline->isNotEmpty())
                <div x-show="isFilterEmpty()" x-cloak class="px-3 py-6 text-xs leading-4 text-slate-500" x-text="emptyLabel()"></div>
            @endif
        </div>
    </div>
</div>
