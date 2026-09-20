@php
    /** @var \Illuminate\Support\Collection<int, \App\Ark\Operations\Timeline\OperationalEventEntry> $timelineEvents */
    $timelineEvents = collect($timelineEvents ?? []);
    $hasConversationHistory = (bool) ($hasConversationHistory ?? false);
@endphp

<div id="communication-rail" class="ops-review-rail-tab-panel divide-y divide-slate-100 text-sm">
    <div class="ops-review-panel-header">
        <p class="ops-eyebrow">Communications</p>
        <p class="ops-meta mt-0.5">Text the customer from here — <span class="font-semibold text-slate-700">Send Estimate</span> for the portal link, pay links when invoiced. Calls and messages stay on this conversation.</p>
    </div>

    @if ($repairOrder->customer)
        <x-operations.conversation-quick-reply
            :customer="$repairOrder->customer"
            :repair-order="$repairOrder"
            :repair-order-id="$repairOrder->repair_order_id"
            :is-terminal="$isTerminal ?? false"
            :send-estimate-url="route('operations.repair-orders.conversation-actions.send-estimate', $repairOrder)"
            :send-payment-url="route('operations.repair-orders.conversation-actions.send-payment', $repairOrder)"
            :send-deposit-url="route('operations.repair-orders.conversation-actions.send-deposit', $repairOrder)"
            :send-inspection-url="route('operations.repair-orders.conversation-actions.send-inspection', $repairOrder)"
            :has-conversation-history="$hasConversationHistory"
            :messages-list-ids="['conversation-messages-ro']"
            :messenger-always-open="filled($repairOrder->customer->messenger_psid)"
            class="border-b border-slate-200 bg-white"
        />
    @endif

    @include('operations.repair-orders.partials.repair-order-comms-workflow-log', [
        'repairOrder' => $repairOrder,
        'estimateVersion' => $estimateVersion,
        'isTerminal' => $isTerminal ?? false,
    ])

    @include('operations.repair-orders.partials.repair-order-commitments', [
        'repairOrder' => $repairOrder,
        'isTerminal' => $isTerminal ?? false,
    ])

    <div class="ops-review-panel-header border-t border-slate-200">
        <p class="ops-eyebrow">Conversation</p>
        <p class="ops-meta mt-0.5">Evidence linked to this repair order.</p>
    </div>

    <div
        id="conversation-messages-ro"
        class="ops-comms-workspace__thread-body ops-ro-comms-timeline px-3 py-2"
        data-timeline-refresh="comms-tab"
        aria-label="Conversation"
    >
        @forelse ($timelineEvents as $event)
            @include('operations.timeline.partials.event-bubble', ['event' => $event])
        @empty
            <div data-conversation-empty class="px-3 py-2 text-xs leading-4 text-slate-500">
                No communications linked to this repair order yet. Reply above to start outreach.
            </div>
        @endforelse
    </div>
</div>
