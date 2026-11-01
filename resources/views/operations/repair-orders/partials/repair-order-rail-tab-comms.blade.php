@php
    /** @var \Illuminate\Support\Collection<int, \App\Ark\Operations\Timeline\OperationalEventEntry> $timelineEvents */
    $timelineEvents = collect($timelineEvents ?? []);
    $hasConversationHistory = (bool) ($hasConversationHistory ?? false);
@endphp

<div id="communication-rail" class="d-flex flex-column gap-3" data-comms-panel>
    <div>
        <h3 class="card-title mb-1">Communications</h3>
        <p class="small text-secondary mb-0">Text the customer from here - <span class="fw-bold">Send Estimate</span> for the portal link, pay links when invoiced. Calls and messages stay on this conversation.</p>
    </div>

    @if ($repairOrder->customer)
        {{-- The reply composer is shared with the communications views and moves with them. --}}
        <div class="ark-legacy bg-white text-dark border rounded overflow-hidden" data-comms-composer>
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
                class="bg-white"
            />
        </div>
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

    <div class="card card-sm">
        <div class="card-header">
            <div>
                <h3 class="card-title">Conversation</h3>
                <p class="card-subtitle">Evidence linked to this repair order.</p>
            </div>
        </div>
        {{-- Timeline rows are the shared event-bubble partial and need the legacy thread layout. --}}
        <div
            id="conversation-messages-ro"
            class="ark-legacy ops-comms-workspace__thread-body bg-white text-dark"
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
</div>
