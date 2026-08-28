@php
    $initialCommsFilter = in_array(request()->query('comms'), ['all', 'call', 'text', 'email', 'portal', 'logged'], true)
        ? (string) request()->query('comms')
        : (request()->query('compose') === 'text' ? 'text' : 'all');
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
    class="scroll-mt-6"
>
    <div class="border-b border-slate-100 px-3 py-2">
        <p class="ops-eyebrow">Communications</p>
        <p class="ops-meta mt-0.5">One timeline for calls, text, and email — filter by type when you need to focus.</p>
        <nav class="mt-2 flex flex-wrap gap-1.5" aria-label="Communication type filters">
            <button type="button" @click="setFilter('all')" :class="filterClass('all')">All · {{ $hubCommsCounts['all'] }}</button>
            <button type="button" @click="setFilter('call')" :class="filterClass('call')">Calls · {{ $hubCommsCounts['call'] }}</button>
            <button type="button" @click="setFilter('text')" :class="filterClass('text')">Text · {{ $hubCommsCounts['text'] }}</button>
            <button type="button" @click="setFilter('email')" :class="filterClass('email')">Email · {{ $hubCommsCounts['email'] }}</button>
            @if ($hubCommsCounts['portal'] > 0)
                <button type="button" @click="setFilter('portal')" :class="filterClass('portal')">Portal · {{ $hubCommsCounts['portal'] }}</button>
            @endif
            @if ($hubCommsCounts['logged'] > 0)
                <button type="button" @click="setFilter('logged')" :class="filterClass('logged')">Logged · {{ $hubCommsCounts['logged'] }}</button>
            @endif
        </nav>
    </div>

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
            <div data-conversation-row data-filter="{{ $event->hubFilter() }}">
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
