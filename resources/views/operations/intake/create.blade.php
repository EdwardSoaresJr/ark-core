<x-operations.app title="Check In">
    @php
        $intakeWorkspaceParams = $intakeWorkspaceParams ?? [];
    @endphp
    <section class="ops-intake ops-workspace space-y-2">
        <div class="ops-board-shell">
            <x-operations.workspace-context-band note="Recognize customer and vehicle, capture why they're here, open the RO.">
                <x-slot:actions>
                    <a href="{{ route('operations.workboard') }}" class="ops-page-link">Operations</a>
                    <a href="{{ route('operations.customers.search', ['intake' => 1]) }}" class="ops-page-link">Customer Search</a>
                </x-slot:actions>
            </x-operations.workspace-context-band>
        </div>

        @if (session('status'))
            <div class="ops-intake-status">
                {{ session('status') }}
            </div>
        @endif

        @if (! $customer)
            @php
                $intakeCustomerShowUrl = str_replace('0', '__CUSTOMER__', route('operations.intake.customers.show', ['customer' => 0]));
                $intakeCustomerUpdateUrl = str_replace('0', '__CUSTOMER__', route('operations.customers.update', ['customer' => 0]));
                $incomingCallCapture = request()->has('phone') && ! request()->has('customer_id');
                $initialIntakePhone = filled(old('phone')) ? old('phone') : ($prefillPhone ?? '');
            @endphp
            @php
                $leadContactNames = $leadContactNames ?? ['first_name' => '', 'last_name' => ''];
                $leadPrefillFirstName = filled(old('first_name')) ? old('first_name') : ($leadContactNames['first_name'] ?? '');
                $leadPrefillLastName = filled(old('last_name')) ? old('last_name') : ($leadContactNames['last_name'] ?? '');
                $leadPrefillReferral = filled(old('referral_source')) ? old('referral_source') : ($leadReferralSource ?? '');
            @endphp
            <div
                class="ops-board-shell ops-intake-find-workspace"
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
                <p class="ops-index-results-head">Recognize customer</p>
                <div class="ops-intake-find-grid">
                    <div class="ops-intake-find-search-column">
                        @include('operations.intake.partials.vehicle-checkin', [
                            'intakeWorkspaceParams' => $intakeWorkspaceParams,
                        ])

                        <div class="ops-intake-find-search">
                            <label for="intake-customer-search" class="ops-index-field-label">Search existing</label>
                            <input
                                id="intake-customer-search"
                                x-model="query"
                                type="search"
                                @if (! $incomingCallCapture) autofocus @endif
                                autocomplete="off"
                                placeholder="Name, phone, email, plate, or VIN"
                                class="ops-intake-control"
                            >
                            <p
                                x-show="searchLoading"
                                x-cloak
                                class="ops-intake-find-search-status"
                                aria-live="polite"
                            >Searching…</p>
                        </div>

                        <div x-ref="results" class="ops-intake-find-search-results">
                            @include('operations.intake.partials.customer-search-results', [
                                'searchQuery' => $searchQuery,
                                'searchCustomers' => $searchCustomers,
                            ])
                        </div>
                    </div>

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
            <div class="ops-board-shell ops-intake-workspace">
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
                        class="ops-intake-open-stack"
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
    </section>
</x-operations.app>
