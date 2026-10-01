@php
    use App\Ark\Operations\Customers\Customer;
    use App\Ark\Operations\Customers\CustomerIdentityPressure;

    $repairOrder = $repairOrder ?? null;
    $customer = $customer ?? $repairOrder?->customer;
    $pressure = $repairOrder instanceof \App\Ark\Operations\RepairOrders\RepairOrder
        ? $repairOrder->customerIdentityPressure()
        : ($customer instanceof Customer ? $customer->identityPressure() : CustomerIdentityPressure::Critical);
    $missingFields = $customer instanceof Customer ? $customer->missingIdentityFieldLabels() : [];
    $hint = $customer instanceof Customer ? $customer->identityPressureHint() : null;
    $variant = $variant ?? 'compact';
@endphp

@if ($pressure->showsChip() && $missingFields !== [])
    @if ($variant === 'intake')
        <div class="ops-customer-identity-pressure ops-customer-identity-pressure--intake">
            <p class="ops-customer-identity-pressure-intake-label">Customer information needed</p>
            <ul class="ops-customer-identity-pressure-missing-list">
                @foreach ($missingFields as $field)
                    <li>Missing {{ $field }}</li>
                @endforeach
            </ul>
        </div>
    @else
        <div class="ops-customer-identity-pressure ops-customer-identity-pressure--{{ $variant }}">
            <div class="flex flex-wrap items-center gap-1.5">
                <span
                    class="ops-customer-identity-pressure-chip ops-customer-identity-pressure-chip--{{ $pressure->chipTone() }}"
                    @if ($hint)
                        title="{{ $hint }}"
                    @endif
                >{{ $pressure->label() }}</span>
                @if (($showMissingFields ?? true) && $hint)
                    <span class="ops-customer-identity-pressure-hint">{{ $hint }}</span>
                @endif
            </div>
        </div>
    @endif
@endif
