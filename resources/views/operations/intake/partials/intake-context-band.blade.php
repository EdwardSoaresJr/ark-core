@php
    use App\Ark\Operations\Encounters\EncounterSource;

    $intakeWorkspaceParams = $intakeWorkspaceParams ?? [];
    $billingClass = trim((string) ($customer->customer_type ?: 'Retail'));
    $referralLabel = filled($customer->referral_source)
        ? (EncounterSource::tryFrom($customer->referral_source)?->label() ?? $customer->referral_source)
        : null;
@endphp

<div class="ops-intake-context">
    <div class="ops-intake-recognition min-w-0">
        <div class="ops-intake-recognition-block ops-intake-recognition-block--customer">
            <p class="ops-intake-context-customer">{{ $customer->name }}</p>
            <p class="ops-intake-context-meta ops-intake-recognition-meta">
                @if ($customer->display_phone)
                    <span>{{ $customer->display_phone }}</span>
                @endif
                @if ($customer->display_phone)
                    <span class="ops-intake-context-sep">·</span>
                @endif
                <span>Billing class {{ $billingClass }}</span>
                @if ($referralLabel)
                    <span class="ops-intake-context-sep">·</span>
                    <span>Referral {{ $referralLabel }}</span>
                @endif
            </p>
            @if ($customer->identityPressure()->showsChip())
                <div class="mt-1">
                    @include('operations.repair-orders.partials.repair-order-customer-identity-pressure', [
                        'customer' => $customer,
                        'variant' => 'intake',
                    ])
                </div>
            @endif
        </div>

        @if ($intakeStep === 'open' && $selectedVehicle)
            <div class="ops-intake-recognition-block ops-intake-recognition-block--vehicle">
                <p class="ops-intake-context-vehicle">{{ $selectedVehicle->display_name }}</p>
                @if (! $selectedVehicle->hasVin())
                    <div class="mt-0.5 flex flex-wrap items-center gap-1.5">
                        @include('operations.repair-orders.partials.repair-order-vehicle-identity-pressure-chip', ['vehicle' => $selectedVehicle])
                    </div>
                @endif
                @if ($selectedVehicle->plate || $selectedVehicle->vin)
                    <p class="ops-intake-context-meta ops-intake-recognition-meta">
                        @if ($selectedVehicle->plate)
                            Plate {{ $selectedVehicle->plate }}@if ($selectedVehicle->plate_state) / {{ $selectedVehicle->plate_state }} @endif
                        @endif
                        @if ($selectedVehicle->plate && $selectedVehicle->vin)
                            <span class="ops-intake-context-sep">·</span>
                        @endif
                        @if ($selectedVehicle->vin)
                            VIN {{ $selectedVehicle->vin }}
                        @endif
                    </p>
                @endif
            </div>
        @elseif ($intakeStep === 'vehicle')
            <div class="ops-intake-recognition-block ops-intake-recognition-block--vehicle">
                <p class="ops-intake-context-meta ops-intake-recognition-prompt">Choose the vehicle for this visit.</p>
            </div>
        @endif
    </div>

    <div class="ops-intake-context-actions">
        @if ($intakeStep === 'open')
            <a
                href="{{ route('operations.intake.create', array_merge($intakeWorkspaceParams, ['customer_id' => $customer->id, 'select_vehicle' => 1])) }}"
                class="ops-index-btn ops-index-btn--ghost"
            >Change vehicle</a>
        @endif
        <a href="{{ route('operations.intake.create', array_merge($intakeWorkspaceParams, ['q' => $searchQuery])) }}" class="ops-index-btn ops-index-btn--ghost">Change customer</a>
    </div>
</div>

@include('operations.intake.partials.intake-step-rail', ['intakeStep' => $intakeStep])
