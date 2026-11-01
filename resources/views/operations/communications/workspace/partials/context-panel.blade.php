@php
    $customer = is_array(data_get($context, 'customer')) ? $context['customer'] : [];
    $vehicle = is_array($context['vehicle'] ?? null) ? $context['vehicle'] : [];
    $work = is_array($context['work'] ?? null) ? $context['work'] : null;
    $visits = is_array($context['recent_visits'] ?? null) ? $context['recent_visits'] : [];
    $actions = is_array($context['actions'] ?? null) ? $context['actions'] : [];
    $match = collect($actions)->first(fn ($action): bool => in_array($action['label'] ?? '', ['Find customer', 'Create contact', 'Create customer'], true));
@endphp

<div class="card flex-fill overflow-hidden" data-comms-context-card>
    <div class="card-header py-2 d-xl-none">
        <button type="button" class="btn btn-sm btn-ghost-secondary ps-0" @click="contextOpen = false">
            <x-tabler.icon name="arrow-left" />
            Back to conversation
        </button>
    </div>
    @if ($context === null)
        <div class="card-body text-secondary">Select a conversation</div>
    @else
        <div class="card-body overflow-auto flex-fill p-0" data-comms-context-rail>
            <section class="p-3 border-bottom" aria-labelledby="comms-context-customer">
                <div class="subheader mb-1" id="comms-context-customer">Customer</div>
                <div class="fw-bold">{{ $customer['name'] ?? data_get($context, 'headline') ?? 'Unknown' }}</div>
                <div class="text-secondary small">{{ $customer['status'] ?? data_get($context, 'link_status') }}</div>
                @if (filled($customer['phone'] ?? data_get($context, 'phone') ?? data_get($context, 'thread.phone')))
                    <div class="small">{{ $customer['phone'] ?? data_get($context, 'phone') ?? data_get($context, 'thread.phone') }}</div>
                @endif
                @if (filled($customer['email'] ?? null))
                    <div class="small text-truncate">{{ $customer['email'] }}</div>
                @endif
                @if (filled($context['customer_url'] ?? null))
                    <a href="{{ $context['customer_url'] }}" class="btn btn-sm mt-2">
                        <x-tabler.icon name="user" />
                        View full profile
                    </a>
                @elseif (is_array($match) && filled($match['url'] ?? null))
                    <a href="{{ $match['url'] }}" class="btn btn-sm mt-2">{{ $match['label'] }}</a>
                @endif
            </section>

            <section class="p-3 border-bottom" aria-labelledby="comms-context-vehicle">
                <div class="subheader mb-1" id="comms-context-vehicle">Vehicle &amp; repair order</div>
                @if (is_array($context['open_repair_orders'] ?? null))
                    @forelse ($context['open_repair_orders'] as $order)
                        <div @class(['small', 'mt-2' => ! $loop->first]) data-open-ro="{{ $order['number'] ?? '' }}">
                            @if (filled($order['vehicle'] ?? null))
                                <div class="fw-bold">{{ $order['vehicle'] }}</div>
                            @endif
                            @if (filled($order['number'] ?? null))
                                <div>{{ $order['number'] }}</div>
                            @endif
                            @if (filled($order['status'] ?? null))
                                <div class="text-secondary">{{ $order['status'] }}</div>
                            @endif
                            @if (filled($order['url'] ?? null))
                                <a href="{{ $order['url'] }}">View repair order</a>
                            @endif
                        </div>
                    @empty
                        <div class="text-secondary small">No current visit</div>
                    @endforelse
                @elseif (filled($vehicle['label'] ?? null) || filled($vehicle['ro_number'] ?? null))
                    <div class="small">
                        @if (filled($vehicle['label'] ?? null))
                            <div class="fw-bold">{{ $vehicle['label'] }}</div>
                        @endif
                        @if (filled($vehicle['ro_number'] ?? null))
                            <div>{{ $vehicle['ro_number'] }}</div>
                        @endif
                        @if (filled($vehicle['status'] ?? null))
                            <div class="text-secondary">{{ $vehicle['status'] }}</div>
                        @endif
                        @if (filled($vehicle['url'] ?? null))
                            <a href="{{ $vehicle['url'] }}">View repair order</a>
                        @endif
                    </div>
                @else
                    <div class="text-secondary small">No current visit</div>
                @endif
            </section>

            @if (is_array($work) && filled($work['url'] ?? null))
                @include('operations.communications.workspace.partials.work-panel', ['work' => $work])
            @endif

            <section class="p-3 border-bottom" aria-labelledby="comms-context-visits">
                <div class="subheader mb-1" id="comms-context-visits">Recent visits</div>
                @forelse ($visits as $visit)
                    <a href="{{ $visit['url'] }}" class="d-flex gap-2 small text-reset py-1">
                        <span class="fw-medium">{{ $visit['number'] }}</span>
                        <span class="text-secondary">{{ $visit['date'] }}</span>
                        <span class="ms-auto text-secondary">{{ $visit['status'] }}</span>
                    </a>
                @empty
                    <div class="text-secondary small">No visits yet</div>
                @endforelse
            </section>

            @if (filled($context['internal_note_url'] ?? null))
                <form method="POST" action="{{ $context['internal_note_url'] }}" class="p-3">
                    @csrf
                    <input type="hidden" name="section" value="inbox">
                    <label class="subheader mb-1 d-block" for="comms-context-internal-note">Internal notes</label>
                    <textarea id="comms-context-internal-note" name="body" rows="3" required maxlength="2000" class="form-control" placeholder="Add an internal note…"></textarea>
                    <button type="submit" class="btn btn-sm mt-2">Save</button>
                </form>
            @endif
        </div>
    @endif
</div>
