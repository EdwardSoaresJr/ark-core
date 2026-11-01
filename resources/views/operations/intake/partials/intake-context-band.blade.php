@php
    use App\Ark\Operations\Encounters\EncounterSource;

    $intakeWorkspaceParams = $intakeWorkspaceParams ?? [];
    $billingClass = trim((string) ($customer->customer_type ?: 'Retail'));
    $referralLabel = filled($customer->referral_source)
        ? (EncounterSource::tryFrom($customer->referral_source)?->label() ?? $customer->referral_source)
        : null;
    $missingIdentityFields = $customer->identityPressure()->showsChip() ? $customer->missingIdentityFieldLabels() : [];
@endphp

<div class="card" data-intake-context>
    <div class="card-body p-2 p-md-3">
        <div class="row g-3 align-items-start">
            <div class="col-12 col-md">
                <div class="fw-bold fs-3">{{ $customer->name }}</div>
                <div class="small text-secondary">
                    @if ($customer->display_phone)
                        {{ $customer->display_phone }} ·
                    @endif
                    Billing class {{ $billingClass }}
                    @if ($referralLabel)
                        · Referral {{ $referralLabel }}
                    @endif
                </div>
                @if ($missingIdentityFields !== [])
                    <div class="alert alert-warning mt-2 mb-0 py-2" data-intake-identity-needed>
                        <div class="fw-medium">Customer information needed</div>
                        <ul class="mb-0 ps-3">
                            @foreach ($missingIdentityFields as $field)
                                <li>Missing {{ $field }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif
            </div>

            @if ($intakeStep === 'open' && $selectedVehicle)
                <div class="col-12 col-md">
                    <div class="fw-bold">{{ $selectedVehicle->display_name }}</div>
                    @if (! $selectedVehicle->hasVin())
                        <x-tabler.vehicle-identity-chip :vehicle="$selectedVehicle" />
                    @endif
                    @if ($selectedVehicle->plate || $selectedVehicle->vin)
                        <div class="small text-secondary text-break">
                            @if ($selectedVehicle->plate)
                                Plate {{ $selectedVehicle->plate }}@if ($selectedVehicle->plate_state) / {{ $selectedVehicle->plate_state }} @endif
                            @endif
                            @if ($selectedVehicle->plate && $selectedVehicle->vin) · @endif
                            @if ($selectedVehicle->vin)
                                VIN {{ $selectedVehicle->vin }}
                            @endif
                        </div>
                    @endif
                </div>
            @elseif ($intakeStep === 'vehicle')
                <div class="col-12 col-md small text-secondary">Choose the vehicle for this visit.</div>
            @endif

            <div class="col-12 col-md-auto">
                <div class="btn-list">
                    @if ($intakeStep === 'open')
                        <a href="{{ route('operations.intake.create', array_merge($intakeWorkspaceParams, ['customer_id' => $customer->id, 'select_vehicle' => 1])) }}" class="btn">Change vehicle</a>
                    @endif
                    <a href="{{ route('operations.intake.create', array_merge($intakeWorkspaceParams, ['q' => $searchQuery])) }}" class="btn">Change customer</a>
                </div>
            </div>
        </div>
    </div>
    <div class="card-footer p-2 p-md-3">
        @include('operations.intake.partials.intake-step-rail', ['intakeStep' => $intakeStep])
    </div>
</div>
