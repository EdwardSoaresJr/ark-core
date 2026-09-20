@php
    $customer = is_array(data_get($context, 'customer')) ? $context['customer'] : [];
    $vehicle = is_array($context['vehicle'] ?? null) ? $context['vehicle'] : [];
    $work = is_array($context['work'] ?? null) ? $context['work'] : null;
    $visits = is_array($context['recent_visits'] ?? null) ? $context['recent_visits'] : [];
    $actions = is_array($context['actions'] ?? null) ? $context['actions'] : [];
    $match = collect($actions)->first(fn ($action): bool => in_array($action['label'] ?? '', ['Find customer', 'Create contact', 'Create customer'], true));
@endphp

<div class="ops-comms-workspace__panel ops-comms-workspace__panel--context">
    @if ($context === null)
        <p class="ops-comms-workspace__empty">Select a conversation</p>
    @else
        <div class="ops-comms-inbox__rail">
            <div class="ops-comms-inbox__rail-card">
                <p class="ops-comms-inbox__rail-title">Customer</p>
                <p class="ops-comms-inbox__rail-name">{{ $customer['name'] ?? data_get($context, 'headline') ?? 'Unknown' }}</p>
                <p>{{ $customer['status'] ?? data_get($context, 'link_status') }}</p>
                @if (filled($customer['phone'] ?? data_get($context, 'phone') ?? data_get($context, 'thread.phone')))
                    <p>{{ $customer['phone'] ?? data_get($context, 'phone') ?? data_get($context, 'thread.phone') }}</p>
                @endif
                @if (filled($customer['email'] ?? null))
                    <p>{{ $customer['email'] }}</p>
                @endif
                @if (filled($context['customer_url'] ?? null))
                    <a href="{{ $context['customer_url'] }}" class="ops-comms-inbox__rail-link">View full profile</a>
                @elseif (is_array($match) && filled($match['url'] ?? null))
                    <a href="{{ $match['url'] }}" class="ops-comms-inbox__rail-link">{{ $match['label'] }}</a>
                @endif
            </div>

            <div class="ops-comms-inbox__rail-card">
                <p class="ops-comms-inbox__rail-title">Vehicle &amp; repair order</p>
                @if (filled($vehicle['label'] ?? null) || filled($vehicle['ro_number'] ?? null))
                    @if (filled($vehicle['label'] ?? null))
                        <p class="ops-comms-inbox__rail-name">{{ $vehicle['label'] }}</p>
                    @endif
                    @if (filled($vehicle['ro_number'] ?? null))
                        <p>{{ $vehicle['ro_number'] }}</p>
                    @endif
                    @if (filled($vehicle['status'] ?? null))
                        <p>{{ $vehicle['status'] }}</p>
                    @endif
                    @if (filled($vehicle['url'] ?? null))
                        <a href="{{ $vehicle['url'] }}" class="ops-comms-inbox__rail-link">View repair order</a>
                    @endif
                @else
                    <p>No current visit</p>
                @endif
            </div>

            @if (is_array($work) && filled($work['url'] ?? null))
                @include('operations.communications.workspace.partials.work-panel', ['work' => $work])
            @endif

            <div class="ops-comms-inbox__rail-card">
                <p class="ops-comms-inbox__rail-title">Recent visits</p>
                @forelse ($visits as $visit)
                    <a href="{{ $visit['url'] }}" class="ops-comms-inbox__visit">
                        <span>{{ $visit['number'] }}</span>
                        <span>{{ $visit['date'] }}</span>
                        <span>{{ $visit['status'] }}</span>
                    </a>
                @empty
                    <p>No visits yet</p>
                @endforelse
            </div>

            @if (filled($context['internal_note_url'] ?? null))
                <form method="POST" action="{{ $context['internal_note_url'] }}" class="ops-comms-inbox__rail-card">
                    @csrf
                    <input type="hidden" name="section" value="inbox">
                    <p class="ops-comms-inbox__rail-title">Internal notes</p>
                    <textarea name="body" rows="3" required maxlength="2000" class="ops-comms-inbox__note" placeholder="Add an internal note…"></textarea>
                    <button type="submit" class="ops-comms-inbox__decision-btn">Save</button>
                </form>
            @endif
        </div>
    @endif
</div>
