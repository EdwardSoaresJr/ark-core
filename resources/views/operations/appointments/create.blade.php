<x-operations.app title="Schedule appointment" ui="tabler">
    <x-tabler.page
        layout="focused"
        title="Schedule appointment"
        subtitle="Book the visit first. Customer and vehicle can wait until arrival."
        actions-label="Schedule navigation"
    >
        <x-slot:actions>
            <x-tabler.page-action :href="route('operations.appointments.index')" icon="calendar" label="Back to schedule" />
        </x-slot:actions>

        <div class="d-flex flex-column gap-3" data-schedule-create>
            @if (session('status'))
                <div class="alert alert-success mb-0" role="status">{{ session('status') }}</div>
            @endif
            @if ($errors->any())
                <div class="alert alert-danger mb-0" role="alert">{{ $errors->first() }}</div>
            @endif

            @if ($customer === null && ! ($showUnlinkedForm ?? false))
                <div class="card" data-schedule-customer-search>
                    <div class="card-body p-2 p-md-3">
                        <h3 class="card-title mb-1">Find an existing customer - or schedule a new caller</h3>
                        <div class="small text-secondary mb-3">Search by name, phone, email, plate, or VIN. Or book with name and phone only.</div>
                        <form method="GET" action="{{ route('operations.schedule') }}" class="row g-2">
                            @foreach (request()->except('q', 'mode') as $key => $value)
                                @if (filled($value) && ! is_array($value))
                                    <input type="hidden" name="{{ $key }}" value="{{ $value }}">
                                @endif
                            @endforeach
                            <div class="col-12 col-sm">
                                <div class="input-group">
                                    <input
                                        type="search"
                                        name="q"
                                        value="{{ $searchQuery }}"
                                        autofocus
                                        placeholder="Name, phone, plate…"
                                        aria-label="Search customers"
                                        class="form-control"
                                    >
                                    <button type="submit" class="btn">
                                        <x-tabler.icon name="search" />
                                        Search
                                    </button>
                                </div>
                            </div>
                            <div class="col-12 col-sm-auto">
                                <a href="{{ route('operations.schedule', array_merge(request()->except('q'), ['mode' => 'new'])) }}" class="btn btn-primary w-100">
                                    <x-tabler.icon name="plus" />
                                    New caller
                                </a>
                            </div>
                        </form>
                    </div>

                    @if ($searchQuery !== '')
                        @if ($searchCustomers->isEmpty())
                            <div class="card-body p-2 p-md-3" data-schedule-no-match>
                                <div class="small text-secondary mb-2">No customer match. Schedule with name and phone, or add a customer record.</div>
                                <a href="{{ route('operations.schedule', array_merge(request()->except('q'), ['mode' => 'new', 'q' => $searchQuery])) }}" class="btn btn-primary">
                                    Schedule with name &amp; phone
                                </a>
                            </div>
                            <form method="POST" action="{{ route('operations.customers.store') }}" class="card-body p-2 p-md-3">
                                @csrf
                                <input type="hidden" name="return_to" value="schedule">
                                @foreach (($scheduleSearchPreserve ?? []) as $key => $value)
                                    @if (filled($value) && ! is_array($value))
                                        <input type="hidden" name="schedule_{{ $key }}" value="{{ $value }}">
                                    @endif
                                @endforeach
                                <div class="row g-3">
                                    <div class="col-sm-6">
                                        <label class="form-label" for="schedule-new-first-name">First name</label>
                                        <input type="text" id="schedule-new-first-name" name="first_name" required value="{{ old('first_name') }}" class="form-control">
                                    </div>
                                    <div class="col-sm-6">
                                        <label class="form-label" for="schedule-new-last-name">Last name</label>
                                        <input type="text" id="schedule-new-last-name" name="last_name" required value="{{ old('last_name') }}" class="form-control">
                                    </div>
                                    <div class="col-12">
                                        <label class="form-label" for="schedule-new-phone">Phone</label>
                                        <input type="tel" id="schedule-new-phone" name="phone" required value="{{ old('phone', $searchQuery) }}" class="form-control">
                                    </div>
                                    <div class="col-12">
                                        <button type="submit" class="btn">Add customer &amp; schedule</button>
                                    </div>
                                </div>
                            </form>
                        @else
                            <div class="list-group list-group-flush" data-schedule-matches>
                                @foreach ($searchCustomers as $match)
                                    <a
                                        href="{{ \App\Ark\Operations\Appointments\ScheduleUrl::to(array_merge($scheduleSearchPreserve ?? [], [
                                            'customer' => $match->id,
                                        ])) }}"
                                        class="list-group-item list-group-item-action"
                                    >
                                        <div class="fw-bold">{{ $match->name }}</div>
                                        <div class="small text-secondary">{{ $match->display_phone ?: 'No phone' }}@if ($match->email) · {{ $match->email }}@endif</div>
                                    </a>
                                @endforeach
                            </div>
                        @endif
                    @endif
                </div>
            @else
                <form method="POST" action="{{ route('operations.appointments.store') }}" class="card" data-schedule-form>
                    @csrf
                    <div class="card-body p-2 p-md-3">
                        @if ($customer !== null)
                            <input type="hidden" name="customer_id" value="{{ $customer->id }}">
                            <div class="d-flex flex-wrap align-items-baseline column-gap-3 row-gap-1 mb-3" data-schedule-customer>
                                <span class="fw-bold">{{ $customer->name }}</span>
                                @if (filled($customer->display_phone))
                                    <span class="small text-secondary">{{ $customer->display_phone }}</span>
                                @endif
                                <a href="{{ route('operations.customers.show', $customer) }}" class="small">Customer hub</a>
                                @if ($repairOrder ?? null)
                                    <a href="{{ route('operations.repair-orders.show', $repairOrder) }}" class="small">RO #{{ $repairOrder->repair_order_id }}</a>
                                @endif
                            </div>
                        @else
                            <fieldset class="form-fieldset" data-schedule-caller>
                                <div class="subheader mb-2">Caller</div>
                                <div class="row g-3">
                                    <div class="col-12">
                                        <label class="form-label" for="schedule-contact-name">Name</label>
                                        <input type="text" id="schedule-contact-name" name="contact_name" required value="{{ $defaultContactName }}" @class(['form-control', 'is-invalid' => $errors->has('contact_name')]) autofocus>
                                    </div>
                                    <div class="col-sm-6">
                                        <label class="form-label" for="schedule-contact-phone">Phone</label>
                                        <input type="tel" id="schedule-contact-phone" name="contact_phone" required value="{{ $defaultContactPhone ?? $searchQuery }}" @class(['form-control', 'is-invalid' => $errors->has('contact_phone')])>
                                    </div>
                                    <div class="col-sm-6">
                                        <label class="form-label" for="schedule-contact-email">Email</label>
                                        <input type="email" id="schedule-contact-email" name="contact_email" value="{{ $defaultContactEmail }}" class="form-control">
                                    </div>
                                </div>
                                <div class="form-hint mt-2">No customer record required. Link or create the customer at arrival.</div>
                                @if (filled($vehicleContextLabel ?? null))
                                    <div class="small mt-1"><span class="fw-medium">Vehicle noted:</span> {{ $vehicleContextLabel }}</div>
                                @endif
                            </fieldset>
                        @endif

                        @if ($defaultLeadId ?? null)
                            <input type="hidden" name="lead_id" value="{{ $defaultLeadId }}">
                        @endif

                        @if ($repairOrder ?? null)
                            <input type="hidden" name="repair_order_id" value="{{ $repairOrder->id }}">
                        @endif

                        <div class="mb-3">
                            <x-operations.appointment-datetime-fields
                                :starts-at="$defaultStartsAt"
                                :ends-at="$defaultEndsAt"
                                :duration-minutes="$defaultDurationMinutes ?? null"
                                :preferred-period="$preferredPeriod ?? null"
                                :preferred-date="$preferredDate ?? null"
                                :request-preference-detail="$requestPreferenceDetail ?? null"
                                :slot-minutes="$slotMinutes"
                                :estimated-labor-hours="old('estimated_labor_hours')"
                            />
                        </div>

                        @if ($customer !== null)
                            <div class="mb-3">
                                <label class="form-label" for="schedule-vehicle">Vehicle</label>
                                <select id="schedule-vehicle" name="vehicle_id" class="form-select">
                                    <option value="" @selected(old('vehicle_id', $selectedVehicleId) === null || old('vehicle_id', $selectedVehicleId) === '')>
                                        Vehicle not set yet
                                    </option>
                                    @foreach ($customer->vehicles as $vehicle)
                                        <option value="{{ $vehicle->id }}" @selected((string) old('vehicle_id', $selectedVehicleId) === (string) $vehicle->id)>
                                            {{ $vehicle->year }} {{ $vehicle->make }} {{ $vehicle->model }}
                                        </option>
                                    @endforeach
                                </select>
                                <div class="form-hint">Optional - leave unset until they arrive if the vehicle isn’t known yet.</div>
                            </div>
                        @endif

                        <div class="mb-3">
                            <label class="form-label" for="schedule-concern">Concern</label>
                            <textarea id="schedule-concern" name="concern" rows="2" required maxlength="1000" placeholder="What is the customer coming in for?" class="form-control">{{ old('concern', $defaultConcern ?? '') }}</textarea>
                        </div>

                        <div class="mb-3">
                            <label class="form-label" for="schedule-notes">Notes</label>
                            <textarea id="schedule-notes" name="notes" rows="2" maxlength="2000" class="form-control">{{ old('notes') }}</textarea>
                        </div>

                        <div class="row">
                            <div class="col-sm-6">
                                <label class="form-label" for="schedule-advisor">Advisor</label>
                                <select id="schedule-advisor" name="advisor_user_id" class="form-select">
                                    <option value="">-</option>
                                    @foreach ($advisors as $advisor)
                                        <option value="{{ $advisor->id }}" @selected(old('advisor_user_id', $defaultAdvisorId) == $advisor->id)>{{ $advisor->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                    </div>
                    <div class="card-footer p-2 p-md-3">
                        <button type="submit" class="btn btn-primary">Schedule</button>
                    </div>
                </form>
            @endif
        </div>
    </x-tabler.page>
</x-operations.app>
