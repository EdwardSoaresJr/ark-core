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
            @php
                $customerName = $customer['name'] ?? data_get($context, 'headline') ?? 'Unknown';
                $customerPhone = $customer['phone'] ?? data_get($context, 'phone') ?? data_get($context, 'thread.phone');
                $phoneHref = \App\Ark\Operations\PhoneNumber::telUri((string) $customerPhone);
                $permission = is_array($context['permission'] ?? null) ? $context['permission'] : null;
            @endphp
            <section class="p-3 border-bottom" aria-labelledby="comms-context-customer">
                <div class="subheader mb-2" id="comms-context-customer">Customer</div>
                <div class="d-flex align-items-center gap-2">
                    <x-tabler.avatar :name="$customerName" class="flex-shrink-0" />
                    <div class="overflow-hidden">
                        <div class="fw-bold text-truncate">{{ $customerName }}</div>
                        <div class="text-secondary small">{{ $customer['status'] ?? data_get($context, 'link_status') }}</div>
                    </div>
                </div>
                <div class="btn-list mt-2">
                    @if ($phoneHref !== null)
                        <a href="{{ $phoneHref }}" class="btn btn-icon btn-sm" aria-label="Call" title="Call"><x-tabler.icon name="phone" /></a>
                    @endif
                    <a href="#comms-thread-composer" class="btn btn-icon btn-sm" aria-label="Text" title="Text" @click="contextOpen = false"><x-tabler.icon name="message" /></a>
                    @if (filled($customer['email'] ?? null))
                        <a href="mailto:{{ $customer['email'] }}" class="btn btn-icon btn-sm" aria-label="Email" title="Email"><x-tabler.icon name="mail" /></a>
                    @endif
                    @if (filled($context['customer_url'] ?? null))
                        <a href="{{ $context['customer_url'] }}" class="btn btn-sm" title="View full profile">
                            <x-tabler.icon name="user" />
                            View full profile
                        </a>
                    @elseif (is_array($match) && filled($match['url'] ?? null))
                        <a href="{{ $match['url'] }}" class="btn btn-sm">{{ $match['label'] }}</a>
                    @endif
                </div>
                <dl class="row g-0 small mt-3 mb-0">
                    @if (filled($customerPhone))
                        <dt class="col-4 text-secondary fw-normal">Phone</dt>
                        <dd class="col-8 mb-1">{{ $customerPhone }}</dd>
                    @endif
                    @if (filled($customer['email'] ?? null))
                        <dt class="col-4 text-secondary fw-normal">Email</dt>
                        <dd class="col-8 mb-1 text-truncate">{{ $customer['email'] }}</dd>
                    @endif
                    @if ($permission !== null)
                        <dt class="col-4 text-secondary fw-normal">Texting</dt>
                        <dd class="col-8 mb-1">
                            <span class="badge bg-{{ $permission['tone'] }}-lt" data-comms-context-texting="{{ $permission['texting'] }}">{{ $permission['label'] }}</span>
                            @if (filled($permission['detail'] ?? null))
                                <div class="text-secondary mt-1">{{ $permission['detail'] }}</div>
                            @endif
                            @foreach ($permission['notes'] ?? [] as $note)
                                <div class="text-secondary mt-1" data-comms-context-consent-note>{{ $note }}</div>
                            @endforeach
                        </dd>
                    @endif
                </dl>
            </section>

            <section class="p-3 border-bottom" aria-labelledby="comms-context-vehicle">
                <div class="subheader mb-1" id="comms-context-vehicle">Vehicle &amp; visit</div>
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
                                <span class="badge bg-secondary-lt mt-1">{{ $order['status'] }}</span>
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
                            <span class="badge bg-secondary-lt mt-1">{{ $vehicle['status'] }}</span>
                        @endif
                        @if (filled($vehicle['url'] ?? null))
                            <a href="{{ $vehicle['url'] }}">View repair order</a>
                        @endif
                    </div>
                @else
                    <div class="text-secondary small">No current visit</div>
                @endif
                @php
                    $shop = is_array($context['shop'] ?? null) ? $context['shop'] : [];
                @endphp
                @if (filled($shop['approval'] ?? null) || filled($shop['balance'] ?? null) || filled($shop['appointment'] ?? null))
                    <dl class="row g-0 small mt-2 mb-0" data-comms-context-visit>
                        @if (filled($shop['approval'] ?? null))
                            <dt class="col-4 text-secondary fw-normal">Approval</dt>
                            <dd class="col-8 mb-1"><span class="badge bg-{{ $shop['approval_tone'] }}-lt">{{ $shop['approval'] }}</span></dd>
                        @endif
                        @if (filled($shop['balance'] ?? null))
                            <dt class="col-4 text-secondary fw-normal">Balance</dt>
                            <dd class="col-8 mb-1"><span class="badge bg-{{ $shop['balance_tone'] }}-lt">{{ $shop['balance'] }}</span></dd>
                        @endif
                        @if (filled($shop['appointment'] ?? null))
                            <dt class="col-4 text-secondary fw-normal">Next visit</dt>
                            <dd class="col-8 mb-1">
                                @if (filled($shop['appointment_url'] ?? null))
                                    <a href="{{ $shop['appointment_url'] }}">{{ $shop['appointment'] }}</a>
                                @else
                                    {{ $shop['appointment'] }}
                                @endif
                            </dd>
                        @endif
                    </dl>
                @endif
            </section>

            @if (is_array($work) && filled($work['url'] ?? null))
                <section class="p-3 border-bottom" aria-labelledby="comms-context-work" data-comms-work-summary>
                    <div class="subheader mb-1" id="comms-context-work">Assignment</div>
                    <dl class="row g-0 small mb-0">
                        <dt class="col-4 text-secondary fw-normal">Owner</dt>
                        <dd class="col-8 mb-1 fw-medium">{{ $work['owner_name'] ?? 'Unassigned' }}</dd>
                        <dt class="col-4 text-secondary fw-normal">Follow-up</dt>
                        <dd class="col-8 mb-1">{{ $work['follow_up_due_label'] ?? 'None' }}</dd>
                    </dl>
                </section>
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
