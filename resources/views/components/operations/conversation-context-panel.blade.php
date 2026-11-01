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
    'ui' => 'legacy',
])

@php
    $customer = $context->customer;
@endphp

@if ($ui === 'tabler')
<div {{ $attributes }} data-conversation-context>
    @if ($showCustomerHeader && $customer)
        <div class="card-body border-bottom py-2">
            <h3 class="card-title mb-0">{{ $customer->name }}</h3>
            <div class="text-secondary small">{{ $context->displayPhone }}</div>
        </div>
    @endif

    @if ($showSectionHeader)
        <div class="card-body border-bottom py-2">
            <h3 class="card-title mb-0">Relationship Context</h3>
            <div class="text-secondary small">Customer, open ROs, and recent conversation - do not auto-pick an RO.</div>
        </div>
    @endif

    @if ($showActiveVehicles && $context->vehicles->isNotEmpty())
        <div class="card-body border-bottom py-2">
            <div class="subheader mb-1">Active Vehicles</div>
            <ul class="list-unstyled mb-0">
                @foreach ($context->vehicles as $vehicle)
                    <li class="fw-medium">
                        {{ $vehicle->display_name }}
                        @if ($vehicle->plate)
                            <span class="fw-normal text-secondary">· {{ $vehicle->plate }}</span>
                        @endif
                    </li>
                @endforeach
            </ul>
        </div>
    @endif

    @if ($showOpenRepairOrders)
    <div id="open-repair-orders">
        <div class="card-body border-bottom py-2">
            <h3 class="card-title mb-0">{{ $openRepairOrdersLabel }}</h3>
            <div class="text-secondary small">
                @if (filled($openRepairOrdersMeta))
                    {{ $openRepairOrdersMeta }}
                @else
                    {{ $context->openRepairOrders->count() }} active
                @endif
            </div>
        </div>
        <div class="list-group list-group-flush">
            @forelse ($context->openRepairOrders as $openRepairOrder)
                @php
                    $isHighlighted = $highlightRepairOrderId !== null
                        && (int) $openRepairOrder->repairOrder->repair_order_id === (int) $highlightRepairOrderId;
                    $hubRo = $openRepairOrder->repairOrder;
                    $hubCustomer = $customer;
                    $hubTextUrl = $hubCustomer !== null && filled($context->displayPhone)
                        ? route('operations.customers.show', $hubCustomer).'?compose=text#customer-communication'
                        : null;
                    $hubStatusMoves = $hubRo->isTerminal()
                        ? []
                        : \App\Ark\Operations\RepairOrders\RepairOrderLifecycleSelectProjection::forCatalogTargets($hubRo, auth()->user())->boardMoves();
                @endphp
                <div @class(['list-group-item', 'bg-azure-lt' => $isHighlighted]) data-open-repair-order="{{ $hubRo->repair_order_id }}">
                    <div class="d-flex flex-wrap align-items-start justify-content-between gap-2">
                        <div>
                            <div class="fw-bold">{{ $openRepairOrder->vehicle->display_name }}</div>
                            <div class="small">
                                <a href="{{ route('operations.repair-orders.show', $hubRo) }}#builder">RO #{{ $hubRo->repair_order_id }}</a>
                                @if ($isHighlighted)
                                    <span class="fw-bold text-azure">· This RO</span>
                                @endif
                            </div>
                        </div>
                        {{-- x-operations.lifecycle-status-menu is shared with unmigrated job board and intake screens. --}}
                        <div class="ark-legacy flex-shrink-0">
                            <x-operations.lifecycle-status-menu
                                :repair-order="$hubRo"
                                :label="$hubRo->statusDisplayLabel()"
                                :tone="\App\Ark\Operations\RepairOrders\RepairOrderLifecycleSelectProjection::statusTone($hubRo)"
                                :status-moves="$hubStatusMoves"
                            />
                        </div>
                    </div>
                    <div class="d-flex flex-wrap align-items-center column-gap-2 mt-1 small">
                        @if ($hubCustomer)
                            <a href="{{ route('operations.customers.show', $hubCustomer) }}">{{ $hubCustomer->name }}</a>
                        @endif
                        @if ($hubTextUrl)
                            <a href="{{ $hubTextUrl }}" title="Open communications">{{ $context->displayPhone }}</a>
                        @elseif (filled($context->displayPhone))
                            <span class="text-secondary">{{ $context->displayPhone }}</span>
                        @endif
                        @unless ($isHighlighted)
                            <a href="{{ route('operations.repair-orders.show', $hubRo) }}">Open RO</a>
                        @endunless
                    </div>
                    <div class="mt-1 small">
                        <span class="fw-bold">Workflow:</span> {{ $openRepairOrder->workflowPostureLabel }}
                    </div>
                    @if (is_array($openRepairOrder->orientation ?? null))
                        {{-- The orientation snippet is shared with unmigrated queue rows. --}}
                        <div class="ark-legacy bg-white text-dark rounded mt-2">
                            @include('operations.orientation.partials.snippet', [
                                'orientation' => array_merge($openRepairOrder->orientation, ['density' => 'standard']),
                            ])
                        </div>
                    @endif
                    <div class="small text-secondary">{{ $openRepairOrder->workflowNextAction }}</div>
                </div>
            @empty
                <div class="list-group-item text-secondary small">No open repair orders for this customer.</div>
            @endforelse
        </div>
    </div>
    @endif

    @if ($showConversation)
        <div>
            <div class="card-body border-bottom py-2">
                <h3 class="card-title mb-0">{{ $conversationLabel }}</h3>
                @if (filled($conversationMeta))
                    <div class="text-secondary small">{{ $conversationMeta }}</div>
                @endif
            </div>
            <div id="conversation-messages-relationship">
                @forelse ($context->recentConversationMessages as $message)
                    <x-operations.conversation-message :message="$message" />
                @empty
                    <div data-conversation-empty class="px-3 py-2 text-secondary small">No conversation history yet. Use the composer below or wait for an inbound message.</div>
                @endforelse
            </div>
            @if ($showQuickReply && $customer)
                <x-operations.conversation-quick-reply
                    :customer="$customer"
                    :messages-list-ids="$quickReplyMessagesListIds"
                    :open-repair-orders="$quickReplyOpenRepairOrders"
                    :has-conversation-history="$context->recentConversationMessages->isNotEmpty()"
                    ui="tabler"
                />
            @endif
        </div>
    @endif
</div>
@else
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
            <p class="ops-meta mt-0.5">Customer, open ROs, and recent conversation - do not auto-pick an RO.</p>
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
                    <x-operations.conversation-message :message="$message" ui="legacy" class="border-t border-slate-100" />
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
@endif
