@php
    use App\Ark\Operations\Booking\BookingMode;
@endphp
<x-operations.app title="Online requests" ui="tabler">
    @php
        $weekdays = ['monday' => 'Mon', 'tuesday' => 'Tue', 'wednesday' => 'Wed', 'thursday' => 'Thu', 'friday' => 'Fri', 'saturday' => 'Sat', 'sunday' => 'Sun'];
    @endphp

    <x-tabler.page layout="fluid" title="Online requests" pretitle="Settings" subtitle="The scheduling rules every online booking passes before it reaches your Schedule.">
        <x-slot:actions>
            @if ($builderUrl)
                <a href="{{ $builderUrl }}" class="btn btn-primary">
                    <x-tabler.icon name="calendar" /> Book Service Builder
                </a>
            @endif
            <a href="{{ route('operations.settings.shop.edit', ['section' => 'operations']) }}" class="btn btn-ghost-secondary">Appointment settings</a>
        </x-slot:actions>

        @if (session('status'))
            <div class="alert alert-success" role="status">{{ session('status') }}</div>
        @endif
        @if ($errors->any())
            <div class="alert alert-danger" role="alert">
                <div class="fw-bold">Could not save. Check the fields below.</div>
                <ul class="mb-0 mt-1">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <div class="row g-3">
            @include('operations.settings.partials.section-nav', ['current' => 'booking'])

            <div class="col-12 col-lg-9 col-xxl-10">
                <div class="card" data-online-request-settings>
                    <div class="card-body">
                        <h3 class="mb-1">Days and times customers can ask for</h3>
                        <p class="text-secondary small">Comes from your appointment request availability and scheduling hours, the same rules the Schedule uses.</p>
                        <dl class="row mb-2 small">
                            <dt class="col-sm-4">Days</dt>
                            <dd class="col-sm-8">
                                @php($open = collect($weekdays)->filter(fn ($label, $day) => $requestAvailability['weekly'][$day]['enabled'] ?? false)->values())
                                {{ $open->isEmpty() ? 'None' : $open->implode(', ') }}
                            </dd>
                            <dt class="col-sm-4">How far ahead</dt>
                            <dd class="col-sm-8">{{ $requestAvailability['horizon_days'] }} days, {{ $requestAvailability['minimum_notice_days'] === 0 ? 'same day allowed' : $requestAvailability['minimum_notice_days'].' day(s) notice' }}</dd>
                            <dt class="col-sm-4">Times of day</dt>
                            <dd class="col-sm-8">
                                @php($w = $requestAvailability['request_windows'])
                                {{ collect([
                                    $w['morning']['enabled'] ? 'Morning '.$w['morning']['open'].'–'.$w['morning']['close'] : null,
                                    $w['afternoon']['enabled'] ? 'Afternoon '.$w['afternoon']['open'].'–'.$w['afternoon']['close'] : null,
                                    $w['flexible_enabled'] ? 'Flexible' : null,
                                ])->filter()->implode(' · ') ?: 'None' }}
                                @if (filled($w['latest_appointment_arrival'] ?? null)) · latest arrival {{ $w['latest_appointment_arrival'] }} @endif
                            </dd>
                        </dl>
                        <a href="{{ route('operations.settings.shop.edit', ['section' => 'operations']) }}">Change days, hours, and times of day</a>

                        <div class="border-top pt-3 mt-3">
                            <h3 class="mb-1">Holidays and closed dates</h3>
                            <p class="text-secondary small">Online requests are closed on these dates. You can also open or close a day from the Schedule.</p>
                            <div class="list-group mb-3">
                                @forelse ($exceptions as $exception)
                                    <div class="list-group-item d-flex align-items-center gap-2" data-booking-exception="{{ $exception->date->toDateString() }}">
                                        <span class="fw-medium">{{ $exception->date->format('D, M j, Y') }}</span>
                                        <span class="badge {{ $exception->enablesRequests() ? 'bg-green-lt' : 'bg-red-lt' }}">{{ $exception->enablesRequests() ? 'Open' : 'Closed' }}</span>
                                        @if (filled($exception->reason))<span class="text-secondary small">{{ $exception->reason }}</span>@endif
                                        <form method="POST" action="{{ route('operations.settings.booking.closed-dates.destroy', $exception) }}" class="ms-auto">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="btn btn-sm btn-ghost-secondary">Remove</button>
                                        </form>
                                    </div>
                                @empty
                                    <div class="list-group-item text-secondary small">No upcoming closed dates.</div>
                                @endforelse
                            </div>
                            <form method="POST" action="{{ route('operations.settings.booking.closed-dates.store') }}" class="row g-2 align-items-end">
                                @csrf
                                <div class="col-sm-4">
                                    <label class="form-label" for="closed-date">Date</label>
                                    <input id="closed-date" type="date" name="date" required class="form-control" min="{{ now()->toDateString() }}">
                                </div>
                                <div class="col-sm-5">
                                    <label class="form-label" for="closed-reason">Reason <span class="text-secondary fw-normal">(optional)</span></label>
                                    <input id="closed-reason" name="reason" maxlength="255" class="form-control" placeholder="Thanksgiving">
                                </div>
                                <div class="col-sm-3">
                                    <button type="submit" class="btn w-100">Close this date</button>
                                </div>
                            </form>
                        </div>

                        <form method="POST" action="{{ route('operations.settings.booking.update') }}" class="border-top pt-3 mt-3">
                            @csrf
                            @method('PATCH')
                            <fieldset>
                                <legend class="h3 mb-1">Requests or confirmed appointments</legend>
                                <p class="text-secondary small">Requests wait for staff on the Schedule. Confirmed appointments go straight on the schedule at an open time, within your scheduling hours and capacity. A booking that needs approval still waits.</p>
                                @foreach (BookingMode::cases() as $mode)
                                    <label class="form-check">
                                        <input type="radio" name="mode" value="{{ $mode->value }}" class="form-check-input" @checked(old('mode', $booking['mode']) === $mode->value)>
                                        <span class="form-check-label">{{ $mode->label() }}</span>
                                        <span class="form-check-description">{{ $mode === BookingMode::Request ? 'Recommended. Customers pick a day and morning or afternoon; you choose the exact time.' : 'Customers see open start times and the appointment is confirmed immediately.' }}</span>
                                    </label>
                                @endforeach
                            </fieldset>

                            <fieldset class="mt-4">
                                <legend class="h3 mb-1">Several services in one visit</legend>
                                <p class="text-secondary small">How long the visit is when a customer books more than one thing. The workload always counts every item's labor.</p>
                                @php($visitLength = old('visit_length', $booking['visit_length']))
                                <label class="form-check">
                                    <input type="radio" name="visit_length" value="{{ \App\Ark\Operations\Booking\BookingSettings::VISIT_DROP_OFF }}" class="form-check-input" @checked($visitLength === \App\Ark\Operations\Booking\BookingSettings::VISIT_DROP_OFF)>
                                    <span class="form-check-label">Drop-off: as long as the longest item</span>
                                    <span class="form-check-description">The vehicle stays while the work is done. Best for most repair shops.</span>
                                </label>
                                <label class="form-check">
                                    <input type="radio" name="visit_length" value="{{ \App\Ark\Operations\Booking\BookingSettings::VISIT_SEQUENTIAL }}" class="form-check-input" @checked($visitLength === \App\Ark\Operations\Booking\BookingSettings::VISIT_SEQUENTIAL)>
                                    <span class="form-check-label">One after another: add the times up</span>
                                    <span class="form-check-description">The customer waits, or a mobile technician works through the list on site.</span>
                                </label>
                                <p class="text-secondary small mb-0">A visit longer than a day is never confirmed online; it comes to you as a request.</p>
                            </fieldset>

                            <h3 class="mb-1 mt-4">Limits</h3>
                            <div class="row g-3">
                                <div class="col-sm-6">
                                    <label class="form-label" for="booking-daily-requests">Most online bookings per day</label>
                                    <input id="booking-daily-requests" type="number" min="1" max="500" name="daily_requests" class="form-control" value="{{ old('daily_requests', $booking['limits']['daily_requests']) }}" placeholder="No limit">
                                    <div class="form-hint">Counts requests and online bookings for that day. A full day is not offered.</div>
                                </div>
                                <div class="col-sm-6">
                                    <label class="form-label" for="booking-concurrent">Appointments at the same time (confirmed mode)</label>
                                    <input id="booking-concurrent" type="number" min="1" max="50" name="concurrent_appointments" class="form-control" value="{{ old('concurrent_appointments', $booking['limits']['concurrent_appointments']) }}" placeholder="One per bay">
                                    <div class="form-hint">Leave empty to allow one per schedulable bay (at least one). A one-bay or one-van shop uses 1.</div>
                                </div>
                            </div>
                            <label class="form-check mt-3">
                                <input type="hidden" name="respect_capacity" value="0">
                                <input type="checkbox" name="respect_capacity" value="1" class="form-check-input" @checked(old('respect_capacity', $booking['limits']['respect_capacity']))>
                                <span class="form-check-label">Don't offer days already at your scheduling target</span>
                                <span class="form-check-description">Uses Scheduling capacity from Appointment settings. Confirmed bookings always respect it.</span>
                            </label>

                            <h3 class="mb-1 mt-4">Service area (on-site services)</h3>
                            <p class="text-secondary small">For work done at the customer's location. Leave empty to accept any address.</p>
                            <div class="row g-3">
                                <div class="col-sm-6">
                                    <label class="form-label" for="booking-postal-codes">ZIP codes you serve</label>
                                    <textarea id="booking-postal-codes" name="service_area_postal_codes" rows="3" class="form-control" placeholder="80202 80203 80204">{{ old('service_area_postal_codes', implode(' ', $booking['service_area']['postal_codes'])) }}</textarea>
                                </div>
                                <div class="col-sm-6">
                                    <label class="form-label" for="booking-area-message">Message outside the area</label>
                                    <textarea id="booking-area-message" name="service_area_message" rows="3" maxlength="300" class="form-control" placeholder="That address is outside our service area. Call us and we will see what we can do.">{{ old('service_area_message', $booking['service_area']['message']) }}</textarea>
                                </div>
                            </div>
                            <div class="d-flex justify-content-end mt-3">
                                <button type="submit" class="btn btn-primary">Save online request rules</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </x-tabler.page>
</x-operations.app>
