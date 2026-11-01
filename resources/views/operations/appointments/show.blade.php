<x-operations.app title="Appointment" ui="tabler">
    @php
        $appointmentLengthMinutes = max(
            15,
            (int) \App\Ark\Operations\Settings\ShopDisplayTimezone::present($appointment->starts_at)
                ->diffInMinutes(\App\Ark\Operations\Settings\ShopDisplayTimezone::present($appointment->ends_at)),
        );
        $customerPhone = $appointment->displayPhone();
        $customerEmail = $appointment->displayEmail();
    @endphp

    <x-tabler.page
        layout="focused"
        stack-actions
        :pretitle="\App\Ark\Operations\Settings\ShopDisplayTimezone::format($appointment->starts_at, 'l, M j · g:i A')"
        :title="$appointment->displayName()"
        actions-label="Appointment navigation"
    >
        <x-slot:actions>
            <a href="{{ route('operations.appointments.index') }}" class="btn">
                <x-tabler.icon name="calendar" />
                Schedule
            </a>
        </x-slot:actions>

        <div class="d-flex flex-column gap-3" data-appointment>
            @if (session('status'))
                <div class="alert alert-success mb-0" role="status">{{ session('status') }}</div>
            @endif
            @if (session('schedule_warnings'))
                <div class="alert alert-warning mb-0" role="status">
                    @foreach ((array) session('schedule_warnings') as $warning)
                        <div>{{ $warning }}</div>
                    @endforeach
                </div>
            @endif
            @if (session('error'))
                <div class="alert alert-danger mb-0" role="alert">{{ session('error') }}</div>
            @endif

            <div class="card" data-appointment-summary>
                <div class="card-body p-2 p-md-3">
                    @if ($appointment->customer_id === null)
                        <div class="small fw-medium text-warning">Not linked to a customer yet</div>
                    @endif
                    @if (filled($customerPhone))
                        <div class="text-secondary">{{ $customerPhone }}@if (filled($customerEmail)) · {{ $customerEmail }}@endif</div>
                    @elseif (filled($customerEmail))
                        <div class="text-secondary">{{ $customerEmail }}</div>
                    @endif
                    <div class="small text-secondary mt-1" data-appointment-status>{{ $appointment->status->label() }} · {{ \App\Ark\Operations\Appointments\AppointmentSlotMinutes::durationLabel($appointmentLengthMinutes) }}@if ($appointment->estimated_labor_hours) · {{ $appointment->estimated_labor_hours }}h reserved labor @endif</div>
                </div>

                <div class="card-body p-2 p-md-3">
                    <div class="subheader mb-1">Concern</div>
                    <div>{{ $appointment->concern }}</div>
                    @if ($appointment->notes)
                        <div class="small text-secondary mt-2">{{ $appointment->notes }}</div>
                    @endif
                </div>

                <div class="card-body p-2 p-md-3">
                    <div class="datagrid">
                        <div class="datagrid-item">
                            <div class="datagrid-title">Vehicle</div>
                            <div class="datagrid-content fw-medium">
                                @if ($appointment->vehicle)
                                    {{ $appointment->vehicle->year }} {{ $appointment->vehicle->make }} {{ $appointment->vehicle->model }}
                                @else
                                    Vehicle not set yet
                                @endif
                            </div>
                            @unless ($appointment->vehicle)
                                <div class="small text-secondary">Add when they arrive - use Reschedule or edit.</div>
                            @endunless
                        </div>
                        <div class="datagrid-item">
                            <div class="datagrid-title">Advisor</div>
                            <div class="datagrid-content fw-medium">{{ $appointment->advisor?->name ?? '-' }}</div>
                        </div>
                    </div>
                </div>

                <div class="card-footer p-2 p-md-3" data-appointment-actions>
                    <div class="btn-list">
                        @if ($appointment->customer_id)
                            <a href="{{ route('operations.customers.show', $appointment->customer_id) }}" class="btn">
                                <x-tabler.icon name="user" />
                                Customer
                            </a>
                            @if (filled($customerPhone))
                                <a href="tel:{{ preg_replace('/\D+/', '', $customerPhone) }}" class="btn">
                                    <x-tabler.icon name="phone" />
                                    Call
                                </a>
                                <a href="{{ route('operations.customers.show', $appointment->customer_id) }}?compose=text#customer-communication" class="btn">
                                    <x-tabler.icon name="message" />
                                    Text
                                </a>
                            @endif
                        @else
                            @if (filled($createCustomerUrl ?? null))
                                <a href="{{ $createCustomerUrl }}" class="btn">
                                    <x-tabler.icon name="plus" />
                                    Create customer
                                </a>
                            @endif
                            @if (filled($customerPhone))
                                <a href="tel:{{ preg_replace('/\D+/', '', $customerPhone) }}" class="btn">
                                    <x-tabler.icon name="phone" />
                                    Call
                                </a>
                            @endif
                        @endif
                        @if ($appointment->repair_order_id)
                            <a href="{{ route('operations.repair-orders.show', $appointment->repairOrder) }}" class="btn">
                                <x-tabler.icon name="file-text" />
                                Open RO
                            </a>
                        @else
                            <a href="{{ $intakeUrl }}" class="btn btn-primary" data-appointment-check-in>
                                <x-tabler.icon name="car" />
                                Check In / Create RO
                            </a>
                        @endif
                    </div>
                    @if (! $appointment->repair_order_id && $appointment->status === App\Ark\Operations\Appointments\AppointmentStatus::Arrived)
                        <div class="small text-secondary mt-2">Customer arrived. Use Check In to open the repair order.</div>
                    @endif
                </div>
            </div>

            @if ($appointment->status !== App\Ark\Operations\Appointments\AppointmentStatus::Canceled)
                <details class="card" @if ($openCommsPrompt ?? false) open @endif data-appointment-sms>
                    <summary class="card-body p-2 p-md-3 fw-medium">Customer text - confirmation &amp; reminders</summary>
                    <div class="card-body p-2 p-md-3 d-flex flex-column gap-3">
                        <div class="small text-secondary">
                            Texts go through Conversation (same as Hub). Nothing sends until you choose - confirmation is one click; reminders only fire if you opt in below.
                        </div>

                        @if (! ($smsCanSend ?? false))
                            <div class="alert alert-warning mb-0">{{ $smsBlockReason ?? 'Cannot text this customer yet.' }}</div>
                        @else
                            <div>
                                <div class="subheader mb-2">Confirmation</div>
                                @if ($appointment->confirmation_sms_sent_at)
                                    <div class="small text-secondary">
                                        Sent {{ \App\Ark\Operations\Settings\ShopDisplayTimezone::format($appointment->confirmation_sms_sent_at, 'M j · g:i A') }}.
                                    </div>
                                @else
                                    @if ($confirmationPreview)
                                        <div class="border rounded p-2 small mb-2">{{ $confirmationPreview }}</div>
                                    @endif
                                    <form method="POST" action="{{ route('operations.appointments.sms.confirmation', $appointment) }}">
                                        @csrf
                                        <button type="submit" class="btn btn-outline-primary">
                                            <x-tabler.icon name="message" />
                                            Send confirmation SMS
                                        </button>
                                    </form>
                                @endif
                            </div>

                            <form method="POST" action="{{ route('operations.appointments.sms.reminders', $appointment) }}" class="border-top pt-3">
                                @csrf
                                @method('PATCH')
                                <div class="subheader mb-2">Reminders (opt in)</div>
                                <label class="form-check">
                                    <input type="checkbox" name="reminder_day_before" value="1" class="form-check-input" @checked(old('reminder_day_before', $appointment->reminder_day_before))>
                                    <span class="form-check-label">Day before</span>
                                    @if ($appointment->reminder_day_before_sent_at)
                                        <span class="form-check-description">Sent {{ \App\Ark\Operations\Settings\ShopDisplayTimezone::format($appointment->reminder_day_before_sent_at, 'M j · g:i A') }}</span>
                                    @endif
                                </label>
                                <div class="mb-3">
                                    <label class="form-label" for="appointment-reminder-hours">Hours before</label>
                                    <select id="appointment-reminder-hours" name="reminder_hours_before" class="form-select">
                                        <option value="">- none -</option>
                                        @foreach ($reminderHoursOptions as $hours)
                                            <option value="{{ $hours }}" @selected((string) old('reminder_hours_before', $appointment->reminder_hours_before) === (string) $hours)>
                                                {{ $hours }} hour{{ $hours === 1 ? '' : 's' }} before
                                            </option>
                                        @endforeach
                                    </select>
                                    @if ($appointment->reminder_hours_before_sent_at)
                                        <div class="form-hint">Sent {{ \App\Ark\Operations\Settings\ShopDisplayTimezone::format($appointment->reminder_hours_before_sent_at, 'M j · g:i A') }}</div>
                                    @endif
                                </div>
                                <button type="submit" class="btn">Save reminder plan</button>
                            </form>
                        @endif
                    </div>
                </details>

                <div class="card" data-appointment-status-actions>
                    <div class="card-body p-2 p-md-3">
                        <div class="subheader mb-2">Status</div>
                        <div class="btn-list">
                            @foreach ($statuses as $status)
                                @if ($status === $appointment->status)
                                    @continue
                                @endif
                                <form method="POST" action="{{ route('operations.appointments.status', $appointment) }}">
                                    @csrf
                                    @method('PATCH')
                                    <input type="hidden" name="status" value="{{ $status->value }}">
                                    <button type="submit" class="btn">{{ $status->label() }}</button>
                                </form>
                            @endforeach
                        </div>
                    </div>
                </div>

                <details class="card" @if ($openEditor ?? false) open @endif data-appointment-editor>
                    <summary class="card-body p-2 p-md-3 fw-medium">Reschedule or edit</summary>
                    <form method="POST" action="{{ route('operations.appointments.update', $appointment) }}" class="card-body p-2 p-md-3">
                        @csrf
                        @method('PATCH')
                        <input type="hidden" name="customer_id" value="{{ $appointment->customer_id }}">
                        @if ($appointment->lead_id)
                            <input type="hidden" name="lead_id" value="{{ $appointment->lead_id }}">
                        @endif

                        <fieldset class="form-fieldset">
                            <div class="subheader mb-1">Booking contact</div>
                            <div class="form-hint mb-3">Appointment-owned - correcting a typo here does not change the Customer record.</div>
                            <div class="row g-3">
                                <div class="col-12">
                                    <label class="form-label" for="appointment-contact-name">Name</label>
                                    <input type="text" id="appointment-contact-name" name="contact_name" value="{{ old('contact_name', $appointment->contact_name) }}" class="form-control">
                                </div>
                                <div class="col-sm-6">
                                    <label class="form-label" for="appointment-contact-phone">Phone</label>
                                    <input type="tel" id="appointment-contact-phone" name="contact_phone" value="{{ old('contact_phone', $appointment->contact_phone) }}" class="form-control">
                                </div>
                                <div class="col-sm-6">
                                    <label class="form-label" for="appointment-contact-email">Email</label>
                                    <input type="email" id="appointment-contact-email" name="contact_email" value="{{ old('contact_email', $appointment->contact_email) }}" class="form-control">
                                </div>
                            </div>
                        </fieldset>

                        <div class="mb-3" data-appointment-time-fields>
                            <x-operations.appointment-datetime-fields
                                :starts-at="old('starts_at', \App\Ark\Operations\Settings\ShopDisplayTimezone::present($appointment->starts_at)->format('Y-m-d\TH:i'))"
                                :ends-at="old('ends_at', \App\Ark\Operations\Settings\ShopDisplayTimezone::present($appointment->ends_at)->format('Y-m-d\TH:i'))"
                                :slot-minutes="$slotMinutes"
                                :estimated-labor-hours="old('estimated_labor_hours', $appointment->estimated_labor_hours)"
                            />
                        </div>

                        <div class="mb-3">
                            <label class="form-label" for="appointment-vehicle">Vehicle</label>
                            <select id="appointment-vehicle" name="vehicle_id" class="form-select">
                                <option value="" @selected(old('vehicle_id', $appointment->vehicle_id) === null || old('vehicle_id', $appointment->vehicle_id) === '')>
                                    Vehicle not set yet
                                </option>
                                @foreach (($appointment->customer?->vehicles ?? []) as $vehicle)
                                    <option value="{{ $vehicle->id }}" @selected((string) old('vehicle_id', $appointment->vehicle_id) === (string) $vehicle->id)>
                                        {{ $vehicle->year }} {{ $vehicle->make }} {{ $vehicle->model }}
                                    </option>
                                @endforeach
                            </select>
                            <div class="form-hint">Optional until arrival.</div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label" for="appointment-concern">Concern</label>
                            <textarea id="appointment-concern" name="concern" rows="2" required class="form-control">{{ old('concern', $appointment->concern) }}</textarea>
                        </div>
                        <div class="mb-3">
                            <label class="form-label" for="appointment-notes">Notes</label>
                            <textarea id="appointment-notes" name="notes" rows="2" class="form-control">{{ old('notes', $appointment->notes) }}</textarea>
                        </div>
                        <input type="hidden" name="advisor_user_id" value="{{ $appointment->advisor_user_id }}">
                        <button type="submit" class="btn btn-primary">Save changes</button>
                    </form>
                </details>
            @endif
        </div>
    </x-tabler.page>
</x-operations.app>
