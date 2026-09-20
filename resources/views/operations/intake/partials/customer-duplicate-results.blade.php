@if ($duplicateMatches->isNotEmpty())
    <div class="ops-intake-duplicate-hint" role="status" aria-live="polite">
        <p class="ops-intake-duplicate-hint-head">Similar customers on file</p>
        <p class="ops-intake-duplicate-hint-copy">Possible matches while you're adding someone new. Use one below if it's the same person — otherwise continue and add them.</p>
        <div class="ops-intake-duplicate-list">
            @foreach ($duplicateMatches as $match)
                @php
                    /** @var \App\Ark\Operations\Customers\Customer $customer */
                    $customer = $match['customer'];
                @endphp
                <div class="ops-intake-duplicate-row">
                    <div class="ops-intake-duplicate-row-main min-w-0">
                        <p class="ops-intake-duplicate-row-name">{{ $customer->name }}</p>
                        <p class="ops-intake-duplicate-row-meta">
                            @if ($customer->display_phone)
                                <span>{{ $customer->display_phone }}</span>
                            @endif
                            @if ($customer->email)
                                @if ($customer->display_phone)<span class="ops-ro-sep">·</span>@endif
                                <span class="truncate">{{ $customer->email }}</span>
                            @endif
                            @if (! $customer->display_phone && ! $customer->email)
                                <span>No phone or email</span>
                            @endif
                        </p>
                        @if ($customer->display_address)
                            <p class="ops-intake-duplicate-row-address truncate">{{ $customer->display_address }}</p>
                        @endif
                        <span class="ops-intake-duplicate-reasons">
                            @foreach ($match['reasons'] as $reason)
                                <span class="ops-state-pill">{{ $reason }}</span>
                            @endforeach
                        </span>
                    </div>
                    <button
                        type="button"
                        data-intake-customer-id="{{ $customer->id }}"
                        class="ops-intake-duplicate-use-link shrink-0"
                    >Use this customer</button>
                </div>
            @endforeach
        </div>
    </div>
@endif
