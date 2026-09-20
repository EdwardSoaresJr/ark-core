<x-operations.app title="Appointment">
    <section class="ops-index mx-auto max-w-2xl space-y-3">
        @if (session('status'))
            <div class="border border-emerald-200 bg-emerald-50 px-3 py-2 text-sm font-semibold text-emerald-950">{{ session('status') }}</div>
        @endif
        @if (session('schedule_warnings'))
            <div class="border border-amber-200 bg-amber-50 px-3 py-2 text-sm font-semibold text-amber-950 space-y-1">
                @foreach ((array) session('schedule_warnings') as $warning)
                    <p>{{ $warning }}</p>
                @endforeach
            </div>
        @endif
        @if (session('error'))
            <div class="border border-rose-200 bg-rose-50 px-3 py-2 text-sm font-semibold text-rose-950">{{ session('error') }}</div>
        @endif

        <div class="ops-board-shell">
            <div class="ops-page-toolbar">
                <div>
                    <p class="text-[10px] font-bold uppercase tracking-wide text-slate-400">{{ \App\Ark\Operations\Settings\ShopDisplayTimezone::format($appointment->starts_at, 'l, M j · g:i A') }}</p>
                    <h2 class="mt-0.5 text-base font-black text-slate-950">{{ $appointment->displayName() }}</h2>
                    @if ($appointment->customer_id === null)
                        <p class="mt-0.5 text-[11px] font-semibold text-amber-800">Not linked to a customer yet</p>
                    @endif
                    @if (filled($appointment->displayPhone()))
                        <p class="mt-0.5 text-xs text-slate-600">{{ $appointment->displayPhone() }}@if (filled($appointment->displayEmail())) · {{ $appointment->displayEmail() }}@endif</p>
                    @elseif (filled($appointment->displayEmail()))
                        <p class="mt-0.5 text-xs text-slate-600">{{ $appointment->displayEmail() }}</p>
                    @endif
                    @php
                        $appointmentLengthMinutes = max(
                            15,
                            (int) \App\Ark\Operations\Settings\ShopDisplayTimezone::present($appointment->starts_at)
                                ->diffInMinutes(\App\Ark\Operations\Settings\ShopDisplayTimezone::present($appointment->ends_at)),
                        );
                    @endphp
                    <p class="mt-1 text-xs text-slate-500">{{ $appointment->status->label() }} · {{ \App\Ark\Operations\Appointments\AppointmentSlotMinutes::durationLabel($appointmentLengthMinutes) }}@if ($appointment->estimated_labor_hours) · {{ $appointment->estimated_labor_hours }}h reserved labor @endif</p>
                </div>
                <a href="{{ route('operations.appointments.index') }}" class="ops-page-link">Schedule</a>
            </div>
        </div>

        <div class="ops-board-shell divide-y divide-slate-200">
            <div class="px-3 py-2.5">
                <p class="text-[10px] font-bold uppercase tracking-wide text-slate-400">Concern</p>
                <p class="mt-1 text-sm text-slate-950">{{ $appointment->concern }}</p>
                @if ($appointment->notes)
                    <p class="mt-2 text-[11px] text-slate-600">{{ $appointment->notes }}</p>
                @endif
            </div>

            <div class="grid gap-px bg-slate-200 sm:grid-cols-2">
                <div class="bg-white px-3 py-2">
                    <p class="text-[10px] font-bold uppercase tracking-wide text-slate-400">Vehicle</p>
                    <p class="mt-0.5 text-sm font-semibold text-slate-950">
                        @if ($appointment->vehicle)
                            {{ $appointment->vehicle->year }} {{ $appointment->vehicle->make }} {{ $appointment->vehicle->model }}
                        @else
                            Vehicle not set yet
                        @endif
                    </p>
                    @unless ($appointment->vehicle)
                        <p class="mt-0.5 text-[10px] text-slate-500">Add when they arrive — use Reschedule or edit.</p>
                    @endunless
                </div>
                <div class="bg-white px-3 py-2">
                    <p class="text-[10px] font-bold uppercase tracking-wide text-slate-400">Advisor</p>
                    <p class="mt-0.5 text-sm font-semibold text-slate-950">{{ $appointment->advisor?->name ?? '—' }}</p>
                </div>
            </div>

            <div class="flex flex-wrap gap-2 px-3 py-2.5">
                @if ($appointment->customer_id)
                    <a href="{{ route('operations.customers.show', $appointment->customer_id) }}" class="ops-page-link">Customer</a>
                    @php
                        $customerPhone = $appointment->displayPhone();
                        $customerHubUrl = route('operations.customers.show', $appointment->customer_id);
                    @endphp
                    @if (filled($customerPhone))
                        <a href="tel:{{ preg_replace('/\D+/', '', $customerPhone) }}" class="ops-page-link">Call</a>
                        <a href="{{ $customerHubUrl }}?compose=text#customer-communication" class="ops-page-link">Text</a>
                    @endif
                @else
                    @if (filled($createCustomerUrl ?? null))
                        <a href="{{ $createCustomerUrl }}" class="ops-page-link">Create customer</a>
                    @endif
                    @if (filled($appointment->displayPhone()))
                        <a href="tel:{{ preg_replace('/\D+/', '', $appointment->displayPhone()) }}" class="ops-page-link">Call</a>
                    @endif
                @endif
                @if ($appointment->repair_order_id)
                    <a href="{{ route('operations.repair-orders.show', $appointment->repairOrder) }}" class="ops-page-link">Open RO</a>
                @else
                    <a href="{{ $intakeUrl }}" class="ops-page-link ops-page-link--primary">Check In / Create RO</a>
                @endif
            </div>
            @if (! $appointment->repair_order_id && $appointment->status === App\Ark\Operations\Appointments\AppointmentStatus::Arrived)
                <p class="border-t border-slate-100 px-3 py-2 text-xs text-slate-600">Customer arrived. Use Check In to open the repair order.</p>
            @endif
        </div>

        @if ($appointment->status !== App\Ark\Operations\Appointments\AppointmentStatus::Canceled)
            <details class="ops-board-shell" @if ($openCommsPrompt ?? false) open @endif data-appointment-sms>
                <summary class="cursor-pointer px-3 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50">
                    Customer text — confirmation &amp; reminders
                </summary>
                <div class="space-y-4 border-t border-slate-200 p-3">
                    <p class="text-xs text-slate-600">
                        Texts go through Conversation (same as Hub). Nothing sends until you choose — confirmation is one click; reminders only fire if you opt in below.
                    </p>

                    @if (! ($smsCanSend ?? false))
                        <p class="rounded-sm border border-amber-200 bg-amber-50 px-3 py-2 text-xs font-semibold text-amber-950">
                            {{ $smsBlockReason ?? 'Cannot text this customer yet.' }}
                        </p>
                    @else
                        <div class="space-y-2">
                            <p class="text-[10px] font-bold uppercase tracking-wide text-slate-400">Confirmation</p>
                            @if ($appointment->confirmation_sms_sent_at)
                                <p class="text-xs text-slate-600">
                                    Sent {{ \App\Ark\Operations\Settings\ShopDisplayTimezone::format($appointment->confirmation_sms_sent_at, 'M j · g:i A') }}.
                                </p>
                            @else
                                @if ($confirmationPreview)
                                    <p class="rounded-sm border border-slate-200 bg-slate-50 px-3 py-2 text-xs leading-relaxed text-slate-700">{{ $confirmationPreview }}</p>
                                @endif
                                <form method="POST" action="{{ route('operations.appointments.sms.confirmation', $appointment) }}">
                                    @csrf
                                    <button type="submit" class="h-8 rounded-sm border border-slate-800 bg-slate-900 px-3 text-xs font-semibold text-white">
                                        Send confirmation SMS
                                    </button>
                                </form>
                            @endif
                        </div>

                        <form method="POST" action="{{ route('operations.appointments.sms.reminders', $appointment) }}" class="space-y-3 border-t border-slate-100 pt-3">
                            @csrf
                            @method('PATCH')
                            <p class="text-[10px] font-bold uppercase tracking-wide text-slate-400">Reminders (opt in)</p>
                            <label class="flex items-start gap-2 text-sm text-slate-800">
                                <input type="checkbox" name="reminder_day_before" value="1" class="mt-0.5 rounded border-slate-300" @checked(old('reminder_day_before', $appointment->reminder_day_before))>
                                <span>
                                    Day before
                                    @if ($appointment->reminder_day_before_sent_at)
                                        <span class="block text-xs font-normal text-slate-500">Sent {{ \App\Ark\Operations\Settings\ShopDisplayTimezone::format($appointment->reminder_day_before_sent_at, 'M j · g:i A') }}</span>
                                    @endif
                                </span>
                            </label>
                            <label class="block">
                                <span class="text-[10px] font-bold uppercase tracking-wide text-slate-400">Hours before</span>
                                <select name="reminder_hours_before" class="mt-0.5 h-9 w-full rounded-sm border border-slate-300 bg-white px-2 text-sm">
                                    <option value="">— none —</option>
                                    @foreach ($reminderHoursOptions as $hours)
                                        <option value="{{ $hours }}" @selected((string) old('reminder_hours_before', $appointment->reminder_hours_before) === (string) $hours)>
                                            {{ $hours }} hour{{ $hours === 1 ? '' : 's' }} before
                                        </option>
                                    @endforeach
                                </select>
                                @if ($appointment->reminder_hours_before_sent_at)
                                    <span class="mt-1 block text-xs text-slate-500">Sent {{ \App\Ark\Operations\Settings\ShopDisplayTimezone::format($appointment->reminder_hours_before_sent_at, 'M j · g:i A') }}</span>
                                @endif
                            </label>
                            <button type="submit" class="h-8 rounded-sm border border-slate-300 bg-white px-3 text-xs font-semibold text-slate-800 hover:bg-slate-50">
                                Save reminder plan
                            </button>
                        </form>
                    @endif
                </div>
            </details>
        @endif

        @if ($appointment->status !== App\Ark\Operations\Appointments\AppointmentStatus::Canceled)
            <div class="ops-board-shell p-3">
                <p class="text-[10px] font-bold uppercase tracking-wide text-slate-400">Status</p>
                <div class="mt-2 flex flex-wrap gap-1.5">
                    @foreach ($statuses as $status)
                        @if ($status === $appointment->status)
                            @continue
                        @endif
                        <form method="POST" action="{{ route('operations.appointments.status', $appointment) }}" class="inline">
                            @csrf
                            @method('PATCH')
                            <input type="hidden" name="status" value="{{ $status->value }}">
                            <button type="submit" class="ops-call-queue__action">{{ $status->label() }}</button>
                        </form>
                    @endforeach
                </div>
            </div>

            <details class="ops-board-shell" @if ($openEditor ?? false) open @endif data-appointment-editor>
                <summary class="cursor-pointer px-3 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50">Reschedule or edit</summary>
                <form method="POST" action="{{ route('operations.appointments.update', $appointment) }}" class="space-y-3 border-t border-slate-200 p-3">
                    @csrf
                    @method('PATCH')
                    <input type="hidden" name="customer_id" value="{{ $appointment->customer_id }}">
                    @if ($appointment->lead_id)
                        <input type="hidden" name="lead_id" value="{{ $appointment->lead_id }}">
                    @endif
                    <div class="space-y-2 border border-slate-200 bg-slate-50/60 p-3">
                        <p class="text-[10px] font-bold uppercase tracking-wide text-slate-400">Booking contact</p>
                        <p class="text-[11px] text-slate-500">Appointment-owned — correcting a typo here does not change the Customer record.</p>
                        <div class="grid gap-3 sm:grid-cols-2">
                            <label class="block sm:col-span-2">
                                <span class="text-[10px] font-bold uppercase tracking-wide text-slate-400">Name</span>
                                <input type="text" name="contact_name" value="{{ old('contact_name', $appointment->contact_name) }}" class="mt-0.5 h-9 w-full rounded-sm border border-slate-300 bg-white px-2 text-sm">
                            </label>
                            <label class="block">
                                <span class="text-[10px] font-bold uppercase tracking-wide text-slate-400">Phone</span>
                                <input type="tel" name="contact_phone" value="{{ old('contact_phone', $appointment->contact_phone) }}" class="mt-0.5 h-9 w-full rounded-sm border border-slate-300 bg-white px-2 text-sm">
                            </label>
                            <label class="block">
                                <span class="text-[10px] font-bold uppercase tracking-wide text-slate-400">Email</span>
                                <input type="email" name="contact_email" value="{{ old('contact_email', $appointment->contact_email) }}" class="mt-0.5 h-9 w-full rounded-sm border border-slate-300 bg-white px-2 text-sm">
                            </label>
                        </div>
                    </div>
                    <x-operations.appointment-datetime-fields
                        :starts-at="old('starts_at', \App\Ark\Operations\Settings\ShopDisplayTimezone::present($appointment->starts_at)->format('Y-m-d\TH:i'))"
                        :ends-at="old('ends_at', \App\Ark\Operations\Settings\ShopDisplayTimezone::present($appointment->ends_at)->format('Y-m-d\TH:i'))"
                        :slot-minutes="$slotMinutes"
                        :estimated-labor-hours="old('estimated_labor_hours', $appointment->estimated_labor_hours)"
                    />
                    <label class="block">
                        <span class="text-[10px] font-bold uppercase tracking-wide text-slate-400">Vehicle</span>
                        <select name="vehicle_id" class="mt-0.5 h-9 w-full rounded-sm border border-slate-300 bg-white px-2 text-sm">
                            <option value="" @selected(old('vehicle_id', $appointment->vehicle_id) === null || old('vehicle_id', $appointment->vehicle_id) === '')>
                                Vehicle not set yet
                            </option>
                            @foreach (($appointment->customer?->vehicles ?? []) as $vehicle)
                                <option value="{{ $vehicle->id }}" @selected((string) old('vehicle_id', $appointment->vehicle_id) === (string) $vehicle->id)>
                                    {{ $vehicle->year }} {{ $vehicle->make }} {{ $vehicle->model }}
                                </option>
                            @endforeach
                        </select>
                        <span class="mt-0.5 block text-[10px] text-slate-500">Optional until arrival.</span>
                    </label>
                    <label class="block">
                        <span class="text-[10px] font-bold uppercase tracking-wide text-slate-400">Concern</span>
                        <textarea name="concern" rows="2" required class="mt-0.5 w-full rounded-sm border border-slate-300 px-2.5 py-1.5 text-sm">{{ old('concern', $appointment->concern) }}</textarea>
                    </label>
                    <label class="block">
                        <span class="text-[10px] font-bold uppercase tracking-wide text-slate-400">Notes</span>
                        <textarea name="notes" rows="2" class="mt-0.5 w-full rounded-sm border border-slate-300 px-2.5 py-1.5 text-sm">{{ old('notes', $appointment->notes) }}</textarea>
                    </label>
                    <input type="hidden" name="advisor_user_id" value="{{ $appointment->advisor_user_id }}">
                    <button type="submit" class="h-8 rounded-sm border border-slate-800 bg-slate-900 px-3 text-xs font-semibold text-white">Save changes</button>
                </form>
            </details>
        @endif
    </section>
</x-operations.app>
