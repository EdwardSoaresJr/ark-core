@if ($duplicateMatches->isNotEmpty())
    <div class="alert alert-warning mt-3 mb-0" role="status" aria-live="polite" data-intake-duplicates>
        <h4 class="alert-title">Similar customers on file</h4>
        <div class="text-secondary mb-2">Possible matches while you're adding someone new. Use one below if it's the same person - otherwise continue and add them.</div>
        <div class="list-group">
            @foreach ($duplicateMatches as $match)
                @php
                    /** @var \App\Ark\Operations\Customers\Customer $customer */
                    $customer = $match['customer'];
                @endphp
                <div class="list-group-item">
                    <div class="row g-2 align-items-center">
                        <div class="col overflow-hidden">
                            <div class="fw-bold text-truncate">{{ $customer->name }}</div>
                            <div class="small text-secondary text-truncate">
                                @if ($customer->display_phone)
                                    {{ $customer->display_phone }}
                                @endif
                                @if ($customer->email)
                                    @if ($customer->display_phone) · @endif{{ $customer->email }}
                                @endif
                                @if (! $customer->display_phone && ! $customer->email)
                                    No phone or email
                                @endif
                            </div>
                            @if ($customer->display_address)
                                <div class="small text-secondary text-truncate">{{ $customer->display_address }}</div>
                            @endif
                            <div class="d-flex flex-wrap gap-1 mt-1">
                                @foreach ($match['reasons'] as $reason)
                                    <span class="badge bg-yellow-lt">{{ $reason }}</span>
                                @endforeach
                            </div>
                        </div>
                        <div class="col-12 col-sm-auto">
                            <button type="button" data-intake-customer-id="{{ $customer->id }}" class="btn w-100">Use this customer</button>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
    </div>
@endif
