<x-operations.app title="Check In" ui="tabler">
    @php
        $intakeWorkspaceParams = $intakeWorkspaceParams ?? [];
    @endphp
    <x-tabler.page
        layout="fluid"
        title="Check In"
        subtitle="Recognize customer and vehicle, capture why they're here, open the RO."
        actions-label="Check In navigation"
    >
        <x-slot:actions>
            <x-tabler.page-action :href="route('operations.workboard')" icon="layout-grid" label="Operations" />
            <x-tabler.page-action :href="route('operations.customers.search', ['intake' => 1])" icon="users" label="Customer Search" />
        </x-slot:actions>

        <div class="d-flex flex-column gap-3" data-intake>
            @if (session('status'))
                <div class="alert alert-success mb-0" role="status">{{ session('status') }}</div>
            @endif

            @if (! $customer)
                @php
                    $intakeCustomerShowUrl = str_replace('0', '__CUSTOMER__', route('operations.intake.customers.show', ['customer' => 0]));
                    $intakeCustomerUpdateUrl = str_replace('0', '__CUSTOMER__', route('operations.customers.update', ['customer' => 0]));
                    $incomingCallCapture = request()->has('phone') && ! request()->has('customer_id');
                    $initialIntakePhone = filled(old('phone')) ? old('phone') : ($prefillPhone ?? '');
                    $leadContactNames = $leadContactNames ?? ['first_name' => '', 'last_name' => ''];
                    $leadPrefillFirstName = filled(old('first_name')) ? old('first_name') : ($leadContactNames['first_name'] ?? '');
                    $leadPrefillLastName = filled(old('last_name')) ? old('last_name') : ($leadContactNames['last_name'] ?? '');
                    $leadPrefillReferral = filled(old('referral_source')) ? old('referral_source') : ($leadReferralSource ?? '');
                @endphp
                <div
                    class="row g-3 align-items-start"
                    data-intake-find-workspace
                    x-data="arkIntakeFindCustomer({
                        initialQuery: @js($searchQuery),
                        searchUrl: @js(route('operations.intake.customers.search')),
                        checkUrl: @js(route('operations.intake.customers.duplicates')),
                        customerShowUrl: @js($intakeCustomerShowUrl),
                        storeUrl: @js(route('operations.customers.store')),
                        customerUpdateUrl: @js($intakeCustomerUpdateUrl),
                        initialSelectedCustomerId: @js(old('customer_id') ? (int) old('customer_id') : null),
                        initialFirstName: @js($leadPrefillFirstName),
                        initialLastName: @js($leadPrefillLastName),
                        leadPrefill: @js([
                            'firstName' => $leadPrefillFirstName,
                            'lastName' => $leadPrefillLastName,
                            'phone' => $initialIntakePhone,
                            'email' => old('email', $lead?->contact_email ?? ''),
                            'contactPreference' => old('contact_preference', $lead?->contact_preference?->value ?? ''),
                            'referralSource' => $leadPrefillReferral,
                        ]),
                        leadId: @js($lead?->id),
                        initialPhone: @js($initialIntakePhone),
                        initialPhoneFromCall: @js($incomingCallCapture),
                        initialEmail: @js(old('email', $lead?->contact_email ?? '')),
                        initialContactPreference: @js(old('contact_preference', $lead?->contact_preference?->value ?? '')),
                        initialAddressLine1: @js(old('address_line_1', '')),
                        initialAddressLine2: @js(old('address_line_2', '')),
                        initialCity: @js(old('city', '')),
                        initialState: @js(old('state', '')),
                        initialPostalCode: @js(old('postal_code', '')),
                        initialReferralSource: @js($leadPrefillReferral),
                        initialCustomerType: @js(old('customer_type', 'Retail')),
                    })"
                >
                    <div class="col-12 col-lg-5">
                        <div class="card">
                            <div class="card-header">
                                <h3 class="card-title">Recognize customer</h3>
                            </div>
                            <div class="card-body p-2 p-md-3">
                                @include('operations.intake.partials.vehicle-checkin', [
                                    'intakeWorkspaceParams' => $intakeWorkspaceParams,
                                ])
                            </div>
                            <div class="card-body p-2 p-md-3">
                                <label for="intake-customer-search" class="form-label">Search existing</label>
                                <div class="input-icon">
                                    <span class="input-icon-addon">
                                        <x-tabler.icon name="search" />
                                    </span>
                                    <input
                                        id="intake-customer-search"
                                        x-model="query"
                                        type="search"
                                        @if (! $incomingCallCapture) autofocus @endif
                                        autocomplete="off"
                                        placeholder="Name, phone, email, plate, or VIN"
                                        class="form-control"
                                    >
                                </div>
                                <div x-show="searchLoading" x-cloak class="form-hint" aria-live="polite">Searching…</div>

                                <div x-ref="results">
                                    @include('operations.intake.partials.customer-search-results', [
                                        'searchQuery' => $searchQuery,
                                        'searchCustomers' => $searchCustomers,
                                    ])
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="col-12 col-lg-7">
                        @include('operations.intake.partials.customer-create', [
                            'customerTypes' => $customerTypes,
                            'referralSources' => $referralSources,
                            'focusFirstName' => $incomingCallCapture,
                            'intakeWorkspaceParams' => $intakeWorkspaceParams,
                            'lastNameOptional' => $leadLastNameOptional ?? false,
                            'leadPrefillFirstName' => $leadPrefillFirstName,
                            'leadPrefillLastName' => $leadPrefillLastName,
                        ])
                    </div>
                </div>
            @else
                <div class="d-flex flex-column gap-3" data-intake-workspace>
                    @include('operations.intake.partials.intake-context-band', [
                        'customer' => $customer,
                        'intakeStep' => $intakeStep,
                        'selectedVehicle' => $selectedVehicle,
                        'searchQuery' => $searchQuery,
                        'intakeWorkspaceParams' => $intakeWorkspaceParams,
                    ])

                    @if ($intakeStep === 'vehicle')
                        @include('operations.intake.partials.vehicle-select', [
                            'customer' => $customer,
                            'lastVisitVehicle' => $lastVisitVehicle,
                            'intakeWorkspaceParams' => $intakeWorkspaceParams,
                            'lead' => $lead,
                        ])
                    @elseif ($intakeStep === 'open' && $selectedVehicle)
                        <form
                            id="advisor-intake-form"
                            method="POST"
                            action="{{ route('operations.intake.store') }}"
                            data-intake-open-stack
                        >
                            @csrf
                            @include('operations.intake.partials.intake-workspace-hidden')
                            <input type="hidden" name="customer_id" value="{{ $customer->id }}">
                            <input type="hidden" name="vehicle_id" value="{{ $selectedVehicle->id }}">

                            @include('operations.intake.partials.open-ro-form-body', [
                                'customer' => $customer,
                                'initialVisitReason' => $initialVisitReason,
                            ])
                        </form>
                    @endif
                </div>
            @endif
        </div>
    </x-tabler.page>
</x-operations.app>
