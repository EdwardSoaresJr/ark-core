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
                $primaryPhone = $context['customer_primary_phone'] ?? null;
                // An email thread's address is an email, never a phone number; its customer is called on their own phone.
                $threadEmail = data_get($context, 'thread.surface') === 'email' ? trim((string) data_get($context, 'thread.address')) : '';
                $customerPhone = $threadEmail !== ''
                    ? ($customer['phone'] ?? data_get($context, 'phone') ?? $primaryPhone)
                    : ($customer['phone'] ?? data_get($context, 'phone') ?? data_get($context, 'thread.phone'));
                $phoneHref = \App\Ark\Operations\PhoneNumber::telUri((string) $customerPhone);
                $permission = is_array($context['permission'] ?? null) ? $context['permission'] : null;
                // Replies and calls use this conversation's number; say so when it is not the customer's main phone.
                $otherNumber = $threadEmail === '' && filled($customerPhone) && filled($primaryPhone)
                    && \App\Ark\Operations\PhoneNumber::normalize((string) $customerPhone) !== \App\Ark\Operations\PhoneNumber::normalize((string) $primaryPhone);
                // Replies by email go to this thread's address; say so when it is not the customer's email.
                $otherEmail = $threadEmail !== '' && strcasecmp($threadEmail, trim((string) ($customer['email'] ?? ''))) !== 0;
                // Texting the customer from an email thread starts from their own record, not this thread's composer.
                $textHref = $threadEmail === ''
                    ? '#comms-thread-composer'
                    : (filled($context['customer_url'] ?? null) && filled($customerPhone) ? $context['customer_url'].'?compose=text#customer-communication' : null);
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
                    @if ($textHref !== null)
                        <a href="{{ $textHref }}" class="btn btn-icon btn-sm" aria-label="Text" title="Text" @click="contextOpen = false"><x-tabler.icon name="message" /></a>
                    @endif
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
                    @if ($otherNumber)
                        <dt class="col-4 text-secondary fw-normal">Phones</dt>
                        <dd class="col-8 mb-1">
                            <div data-comms-context-thread-phone>{{ $customerPhone }}<div class="text-secondary">This conversation</div></div>
                            <div class="mt-1" data-comms-context-primary-phone>{{ $primaryPhone }}<div class="text-secondary">Main phone</div></div>
                        </dd>
                    @elseif (filled($customerPhone))
                        <dt class="col-4 text-secondary fw-normal">Phone</dt>
                        <dd class="col-8 mb-1">{{ $customerPhone }}</dd>
                    @endif
                    @if ($otherEmail)
                        <dt class="col-4 text-secondary fw-normal">Email</dt>
                        <dd class="col-8 mb-1 text-truncate">
                            <div class="text-truncate" data-comms-context-thread-email>{{ $threadEmail }}<div class="text-secondary">This conversation</div></div>
                            @if (filled($customer['email'] ?? null))
                                <div class="mt-1 text-truncate" data-comms-context-customer-email>{{ $customer['email'] }}<div class="text-secondary">Customer's email</div></div>
                            @endif
                        </dd>
                    @elseif (filled($customer['email'] ?? null))
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

            @if (is_array($work) && filled($work['url'] ?? null))
                <section class="p-3 border-bottom" aria-labelledby="comms-context-work" data-comms-work-summary>
                    <div class="subheader mb-1" id="comms-context-work">Conversation</div>
                    <dl class="row g-0 small mb-2">
                        <dt class="col-4 text-secondary fw-normal">Owner</dt>
                        <dd class="col-8 mb-1 fw-medium">{{ $work['owner_name'] ?? 'Unassigned' }}</dd>
                        <dt class="col-4 text-secondary fw-normal">Follow-up</dt>
                        <dd class="col-8 mb-1">{{ $work['follow_up_due_label'] ?? 'None' }}</dd>
                    </dl>
                    @include('operations.communications.workspace.partials.work-actions', ['work' => $work, 'variant' => 'rail'])
                </section>
            @endif

            <section class="p-3 border-bottom" aria-labelledby="comms-context-vehicle">
                <div class="subheader mb-1" id="comms-context-vehicle">Vehicle &amp; visit</div>
                @php
                    $openOrders = is_array($context['open_repair_orders'] ?? null) ? $context['open_repair_orders'] : [];
                    $checkIn = is_array($context['check_in'] ?? null) ? $context['check_in'] : null;
                    $shop = is_array($context['shop'] ?? null) ? $context['shop'] : [];
                @endphp
                @if ($openOrders !== [])
                    @foreach ($openOrders as $order)
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
                    @endforeach
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
                @if ($checkIn !== null && filled($checkIn['url'] ?? null))
                    <div class="mt-2" data-comms-context-check-in="{{ $checkIn['key'] ?? 'check_in' }}">
                        <a
                            href="{{ $checkIn['url'] }}"
                            class="btn btn-sm {{ ($checkIn['style'] ?? 'primary') === 'primary' ? 'btn-primary' : 'btn-outline-secondary' }}"
                            data-comms-check-in
                        >
                            <x-tabler.icon name="car" />
                            {{ $checkIn['label'] ?? 'Check In' }}
                        </a>
                        @if (filled($checkIn['hint'] ?? null))
                            <div class="small text-secondary mt-1">{{ $checkIn['hint'] }}</div>
                        @endif
                    </div>
                @endif
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
        </div>
    @endif
</div>
