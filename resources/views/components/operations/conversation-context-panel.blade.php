@props([
    'context',
    'highlightRepairOrderId' => null,
    'showCustomerHeader' => true,
    'showSectionHeader' => false,
    'showActiveVehicles' => true,
    'showOpenRepairOrders' => true,
    'showConversation' => true,
    'showQuickReply' => false,
    'openRepairOrdersLabel' => 'Open Repair Orders',
    'openRepairOrdersMeta' => null,
    'conversationLabel' => 'Conversation · What We\'ve Said',
    'conversationMeta' => 'Recent messages with this customer',
    'quickReplyMessagesListIds' => ['conversation-messages-relationship'],
    'quickReplyOpenRepairOrders' => [],
])

@php
    $customer = $context->customer;
@endphp

<div {{ $attributes->class(['divide-y divide-slate-100']) }}>
    @if ($showCustomerHeader && $customer)
        <div class="ops-index-results-head">
            <span>{{ $customer->name }}</span>
            <span class="normal-case tracking-normal text-slate-400">{{ $context->displayPhone }}</span>
        </div>
    @endif

    @if ($showSectionHeader)
        <div class="ops-review-panel-header">
            <p class="ops-eyebrow">Relationship Context</p>
            <p class="ops-meta mt-0.5">Customer, open ROs, and recent conversation — do not auto-pick an RO.</p>
        </div>
    @endif

    @if ($showActiveVehicles && $context->vehicles->isNotEmpty())
        <div class="px-3 py-2 text-sm">
            <p class="text-xs font-bold uppercase tracking-[0.08em] text-slate-400">Active Vehicles</p>
            <ul class="mt-1 space-y-0.5 text-sm font-semibold text-slate-800">
                @foreach ($context->vehicles as $vehicle)
                    <li>
                        {{ $vehicle->display_name }}
                        @if ($vehicle->plate)
                            <span class="font-medium text-slate-500">· {{ $vehicle->plate }}</span>
                        @endif
                    </li>
                @endforeach
            </ul>
        </div>
    @endif

    @if ($showOpenRepairOrders)
    <div id="open-repair-orders">
        <div class="ops-index-results-head {{ $showCustomerHeader ? '' : 'border-t border-slate-100' }}">
            <span>{{ $openRepairOrdersLabel }}</span>
            @if (filled($openRepairOrdersMeta))
                <span class="normal-case tracking-normal text-slate-400">{{ $openRepairOrdersMeta }}</span>
            @else
                <span class="normal-case tracking-normal text-slate-400">{{ $context->openRepairOrders->count() }} active</span>
            @endif
        </div>
        @forelse ($context->openRepairOrders as $openRepairOrder)
            @php
                $isHighlighted = $highlightRepairOrderId !== null
                    && (int) $openRepairOrder->repairOrder->repair_order_id === (int) $highlightRepairOrderId;
            @endphp
            <div @class([
                'border-t border-slate-100 px-3 py-2.5',
                'bg-sky-50/70 ring-1 ring-inset ring-sky-200' => $isHighlighted,
            ])>
                @php
                    $hubRo = $openRepairOrder->repairOrder;
                    $hubCustomer = $customer;
                    $hubTextUrl = $hubCustomer !== null && filled($context->displayPhone)
                        ? route('operations.customers.show', $hubCustomer).'?compose=text#customer-communication'
                        : null;
                    $hubStatusMoves = $hubRo->isTerminal()
                        ? []
                        : \App\Ark\Operations\RepairOrders\RepairOrderLifecycleSelectProjection::forCatalogTargets($hubRo, auth()->user())->boardMoves();
                @endphp
                <div class="flex flex-wrap items-start justify-between gap-2">
                    <div class="min-w-0">
                        <p class="text-sm font-black text-slate-950">{{ $openRepairOrder->vehicle->display_name }}</p>
                        <p class="mt-0.5 text-xs font-semibold text-slate-500">
                            <a href="{{ route('operations.repair-orders.show', $hubRo) }}#builder" class="ops-page-link">RO #{{ $hubRo->repair_order_id }}</a>
                            @if ($isHighlighted)
                                <span class="font-bold text-sky-800">· This RO</span>
                            @endif
                        </p>
                    </div>
                    <x-operations.lifecycle-status-menu
                        class="shrink-0"
                        :repair-order="$hubRo"
                        :label="$hubRo->statusDisplayLabel()"
                        :tone="\App\Ark\Operations\RepairOrders\RepairOrderLifecycleSelectProjection::statusTone($hubRo)"
                        :status-moves="$hubStatusMoves"
                    />
                </div>
                <div class="mt-1.5 flex flex-wrap items-center gap-x-2 gap-y-1 text-xs font-semibold">
                    @if ($hubCustomer)
                        <a href="{{ route('operations.customers.show', $hubCustomer) }}" class="ops-page-link">{{ $hubCustomer->name }}</a>
                    @endif
                    @if ($hubTextUrl)
                        <a href="{{ $hubTextUrl }}" class="ops-page-link" title="Open communications">{{ $context->displayPhone }}</a>
                    @elseif (filled($context->displayPhone))
                        <span class="text-slate-500">{{ $context->displayPhone }}</span>
                    @endif
                    @unless ($isHighlighted)
                        <a href="{{ route('operations.repair-orders.show', $hubRo) }}" class="ops-page-link">Open RO</a>
                    @endunless
                </div>
                <p class="mt-1.5 text-xs leading-4 text-slate-600">
                    <span class="font-bold text-slate-700">Workflow:</span> {{ $openRepairOrder->workflowPostureLabel }}
                </p>
                @if (is_array($openRepairOrder->orientation ?? null))
                    <div class="mt-2">
                        @include('operations.orientation.partials.snippet', [
                            'orientation' => array_merge($openRepairOrder->orientation, ['density' => 'standard']),
                        ])
                    </div>
                @endif
                <p class="mt-0.5 text-xs leading-4 text-slate-500">{{ $openRepairOrder->workflowNextAction }}</p>
            </div>
        @empty
            <div class="px-3 py-2 text-xs leading-4 text-slate-500">No open repair orders for this customer.</div>
        @endforelse
    </div>
    @endif

    @if ($showConversation)
        <div>
            <div class="ops-index-results-head border-t border-slate-100">
                <span>{{ $conversationLabel }}</span>
                @if (filled($conversationMeta))
                    <span class="normal-case tracking-normal text-slate-400">{{ $conversationMeta }}</span>
                @endif
            </div>
            <div id="conversation-messages-relationship" class="divide-y divide-slate-100">
                @forelse ($context->recentConversationMessages as $message)
                    <x-operations.conversation-message :message="$message" class="border-t border-slate-100" />
                @empty
                    <div data-conversation-empty class="px-3 py-2 text-xs leading-4 text-slate-500">No conversation history yet. Use the composer below or wait for an inbound message.</div>
                @endforelse
            </div>
            @if ($showQuickReply && $customer)
                <x-operations.conversation-quick-reply
                    :customer="$customer"
                    :messages-list-ids="$quickReplyMessagesListIds"
                    :open-repair-orders="$quickReplyOpenRepairOrders"
                    :has-conversation-history="$context->recentConversationMessages->isNotEmpty()"
                />
            @endif
        </div>
    @endif
</div>
