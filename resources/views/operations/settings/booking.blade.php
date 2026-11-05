@php
    use App\Ark\Operations\Booking\BookingMode;
    use App\Ark\Operations\Booking\BookingSettings;
    use App\Ark\Operations\Appointments\AppointmentSlotMinutes;
@endphp
<x-operations.app title="Online booking" ui="tabler">
    @php
        $tabs = [
            'form' => 'Booking form',
            'services' => 'Services',
            'questions' => 'Questions',
            'availability' => 'Availability',
            'website' => 'Website popup',
        ];
        $fieldLevels = [
            BookingSettings::FIELD_HIDDEN => 'Don\'t ask',
            BookingSettings::FIELD_OPTIONAL => 'Optional',
            BookingSettings::FIELD_REQUIRED => 'Required',
        ];
        $durationOptions = AppointmentSlotMinutes::durationOptions(15);
        $embedSnippet = '<script src="'.$popupUrl.'" defer></script>'."\n".'<a href="'.$bookingUrl.'">'.e($booking['popup']['button_label']).'</a>';
        $weekdays = ['monday' => 'Mon', 'tuesday' => 'Tue', 'wednesday' => 'Wed', 'thursday' => 'Thu', 'friday' => 'Fri', 'saturday' => 'Sat', 'sunday' => 'Sun'];
    @endphp

    <x-tabler.page layout="fluid" title="Online booking" pretitle="Settings" subtitle="What customers can choose when they book from your website, and how requests reach your schedule.">
        <x-slot:actions>
            <a href="{{ $bookingUrl }}" target="_blank" rel="noopener" class="btn">
                <x-tabler.icon name="calendar" /> Open booking form
            </a>
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

        <div class="card" style="max-width: 64rem" data-booking-settings>
            <div class="card-header">
                <ul class="nav nav-tabs card-header-tabs" role="tablist">
                    @foreach ($tabs as $key => $label)
                        <li class="nav-item">
                            <a class="nav-link {{ $tab === $key ? 'active' : '' }}" href="{{ route('operations.settings.booking', ['tab' => $key]) }}" @if ($tab === $key) aria-current="page" @endif>{{ $label }}</a>
                        </li>
                    @endforeach
                </ul>
            </div>

            @if ($tab === 'form')
                <form method="POST" action="{{ route('operations.settings.booking.update') }}" class="card-body" data-booking-settings-form>
                    @csrf
                    @method('PATCH')
                    <input type="hidden" name="section" value="form">

                    <div class="d-flex flex-wrap align-items-start justify-content-between gap-3">
                        <div>
                            <h3 class="mb-1">Take bookings online</h3>
                            <p class="text-secondary small mb-0">Turn off to show customers your phone number instead of the form.</p>
                        </div>
                        <label class="form-check form-switch mb-0">
                            <input type="hidden" name="enabled" value="0">
                            <input type="checkbox" name="enabled" value="1" class="form-check-input" @checked(old('enabled', $booking['enabled']))>
                            <span class="form-check-label">{{ $booking['enabled'] ? 'On' : 'Off' }}</span>
                        </label>
                    </div>

                    <fieldset class="border-top pt-3 mt-3">
                        <legend class="h3 mb-1">Requests or confirmed appointments</legend>
                        <p class="text-secondary small">Requests wait for staff on the Schedule. Confirmed appointments go straight on the schedule at an open time, within your scheduling hours and capacity. A service can still require approval.</p>
                        @foreach (BookingMode::cases() as $mode)
                            <label class="form-check">
                                <input type="radio" name="mode" value="{{ $mode->value }}" class="form-check-input" @checked(old('mode', $booking['mode']) === $mode->value)>
                                <span class="form-check-label">{{ $mode->label() }}</span>
                                @if ($mode === BookingMode::Request)
                                    <span class="form-check-description">Recommended. Customers pick a day and morning or afternoon; you choose the exact time.</span>
                                @else
                                    <span class="form-check-description">Customers see open start times and the appointment is confirmed immediately.</span>
                                @endif
                            </label>
                        @endforeach
                    </fieldset>

                    <div class="border-top pt-3 mt-3">
                        <h3 class="mb-2">What customers read</h3>
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label" for="booking-title">Form title</label>
                                <input id="booking-title" name="title" maxlength="120" class="form-control" value="{{ old('title', $booking['title']) }}">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label" for="booking-intro">Introduction</label>
                                <input id="booking-intro" name="intro" maxlength="500" class="form-control" value="{{ old('intro', $booking['intro']) }}">
                            </div>
                            <div class="col-12">
                                <label class="form-label" for="booking-instructions">Instructions before sending <span class="text-secondary fw-normal">(optional)</span></label>
                                <textarea id="booking-instructions" name="instructions" rows="2" maxlength="1000" class="form-control" placeholder="Drop-offs before 8:30 AM. Leave keys in the drop box.">{{ old('instructions', $booking['instructions']) }}</textarea>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label" for="booking-request-confirmation">After a request</label>
                                <textarea id="booking-request-confirmation" name="request_confirmation" rows="3" maxlength="1000" class="form-control">{{ old('request_confirmation', $booking['request_confirmation']) }}</textarea>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label" for="booking-instant-confirmation">After a confirmed booking</label>
                                <textarea id="booking-instant-confirmation" name="instant_confirmation" rows="3" maxlength="1000" class="form-control">{{ old('instant_confirmation', $booking['instant_confirmation']) }}</textarea>
                            </div>
                        </div>
                    </div>

                    <div class="border-top pt-3 mt-3">
                        <h3 class="mb-2">What the form asks</h3>
                        <div class="row g-3">
                            <div class="col-sm-4">
                                <label class="form-label" for="booking-field-email">Email</label>
                                <select id="booking-field-email" name="fields[email]" class="form-select">
                                    @foreach ($fieldLevels as $value => $label)
                                        <option value="{{ $value }}" @selected(old('fields.email', $booking['fields']['email']) === $value)>{{ $label }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-sm-4">
                                <label class="form-label" for="booking-field-vehicle">Vehicle</label>
                                <select id="booking-field-vehicle" name="fields[vehicle]" class="form-select">
                                    @foreach ($fieldLevels as $value => $label)
                                        <option value="{{ $value }}" @selected(old('fields.vehicle', $booking['fields']['vehicle']) === $value)>{{ $label }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-sm-4">
                                <label class="form-label" for="booking-field-concern">Description of the problem</label>
                                <select id="booking-field-concern" name="fields[concern]" class="form-select">
                                    @foreach ([BookingSettings::FIELD_OPTIONAL => 'Optional', BookingSettings::FIELD_REQUIRED => 'Required'] as $value => $label)
                                        <option value="{{ $value }}" @selected(old('fields.concern', $booking['fields']['concern']) === $value)>{{ $label }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                        <div class="mt-3">
                            @foreach (['vin' => 'Ask for the VIN', 'contact_preference' => 'Ask how they prefer to be reached', 'drivability' => 'Ask whether it is safe to drive or needs a tow'] as $key => $label)
                                <label class="form-check">
                                    <input type="hidden" name="fields[{{ $key }}]" value="0">
                                    <input type="checkbox" name="fields[{{ $key }}]" value="1" class="form-check-input" @checked(old('fields.'.$key, $booking['fields'][$key]))>
                                    <span class="form-check-label">{{ $label }}</span>
                                </label>
                            @endforeach
                        </div>
                        <div class="row g-3 mt-1">
                            <div class="col-sm-4">
                                <label class="form-label" for="booking-max-vehicles">Vehicles per booking</label>
                                <input id="booking-max-vehicles" type="number" min="1" max="{{ BookingSettings::MAX_VEHICLES }}" name="max_vehicles" class="form-control" value="{{ old('max_vehicles', $booking['max_vehicles']) }}">
                                <div class="form-hint">More than 1 lets fleets book several vehicles at once.</div>
                            </div>
                            <div class="col-sm-8">
                                <label class="form-label" for="booking-concerns">Common concerns customers can tap</label>
                                <textarea id="booking-concerns" name="concern_choices" rows="5" class="form-control" placeholder="One per line">{{ old('concern_choices', implode("\n", $booking['concern_choices'])) }}</textarea>
                                <div class="form-hint">One per line. Leave empty to show only the description box.</div>
                            </div>
                        </div>
                    </div>

                    <div class="d-flex justify-content-end border-top pt-3 mt-3">
                        <button type="submit" class="btn btn-primary">Save booking form</button>
                    </div>
                </form>
            @elseif ($tab === 'services')
                <div class="card-body">
                    <p class="text-secondary">Services customers choose from. Each one sets how long the visit takes (used for open times and capacity), where the work happens, and whether it always needs your approval. With no services, customers just describe the problem.</p>

                    @forelse ($services as $index => $service)
                        <details class="card card-sm mb-2" data-booking-service="{{ $service->id }}" @if ((int) old('edit_service_id') === $service->id) open @endif>
                            <summary class="card-body d-flex flex-wrap align-items-center gap-2" style="cursor: pointer; list-style: none">
                                <span class="fw-bold">{{ $service->name }}</span>
                                <span class="text-secondary small">{{ AppointmentSlotMinutes::durationLabel((int) $service->duration_minutes) }} · {{ $service->locationMode()->label() }}</span>
                                @if ($service->requires_approval)<span class="badge bg-yellow-lt">Always needs approval</span>@endif
                                @unless ($service->is_public)<span class="badge bg-secondary-lt">Hidden from customers</span>@endunless
                                @if ($service->optionList() !== [])<span class="badge bg-blue-lt">{{ count($service->optionList()) }} options</span>@endif
                                <span class="ms-auto btn btn-sm">Edit</span>
                            </summary>
                            <div class="card-body border-top">
                                @include('operations.settings.partials.booking-service-form', ['service' => $service, 'action' => route('operations.settings.booking.services.update', $service), 'method' => 'PATCH', 'submit' => 'Save service'])
                                <div class="d-flex flex-wrap gap-2 mt-3 border-top pt-3">
                                    <form method="POST" action="{{ route('operations.settings.booking.services.move', $service) }}">
                                        @csrf
                                        <input type="hidden" name="direction" value="up">
                                        <button type="submit" class="btn btn-sm" @disabled($index === 0)>Move up</button>
                                    </form>
                                    <form method="POST" action="{{ route('operations.settings.booking.services.move', $service) }}">
                                        @csrf
                                        <input type="hidden" name="direction" value="down">
                                        <button type="submit" class="btn btn-sm" @disabled($index === $services->count() - 1)>Move down</button>
                                    </form>
                                    <form method="POST" action="{{ route('operations.settings.booking.services.destroy', $service) }}" class="ms-auto" onsubmit="return confirm('Remove {{ e($service->name) }} from online booking?')">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="btn btn-sm btn-ghost-danger">Remove</button>
                                    </form>
                                </div>
                            </div>
                        </details>
                    @empty
                        <div class="empty py-4">
                            <p class="empty-title">No services yet</p>
                            <p class="empty-subtitle text-secondary">Customers describe the problem and pick a time. Add services when you want them to choose a type of visit.</p>
                            <form method="POST" action="{{ route('operations.settings.booking.services.seed') }}">
                                @csrf
                                <button type="submit" class="btn">Add starter services</button>
                            </form>
                        </div>
                    @endforelse

                    <div class="card mt-3">
                        <div class="card-header"><h3 class="card-title">Add a service</h3></div>
                        <div class="card-body">
                            @include('operations.settings.partials.booking-service-form', ['service' => null, 'action' => route('operations.settings.booking.services.store'), 'method' => 'POST', 'submit' => 'Add service'])
                        </div>
                    </div>
                </div>
            @elseif ($tab === 'questions')
                <div class="card-body">
                    <p class="text-secondary">Questions you want answered before the visit. Ask them for every service or only some. Answers appear on the request and the appointment.</p>
                    @forelse ($questions as $question)
                        <details class="card card-sm mb-2" data-booking-question="{{ $question->id }}">
                            <summary class="card-body d-flex flex-wrap align-items-center gap-2" style="cursor: pointer; list-style: none">
                                <span class="fw-bold">{{ $question->label }}</span>
                                <span class="text-secondary small">{{ $question->type->label() }}</span>
                                @if ($question->required)<span class="badge bg-red-lt">Required</span>@endif
                                <span class="text-secondary small">
                                    {{ $question->serviceIdList() === [] ? 'All services' : $services->whereIn('id', $question->serviceIdList())->pluck('name')->implode(', ') }}
                                </span>
                                <span class="ms-auto btn btn-sm">Edit</span>
                            </summary>
                            <div class="card-body border-top">
                                @include('operations.settings.partials.booking-question-form', ['question' => $question, 'action' => route('operations.settings.booking.questions.update', $question), 'method' => 'PATCH', 'submit' => 'Save question'])
                                <form method="POST" action="{{ route('operations.settings.booking.questions.destroy', $question) }}" class="mt-3 border-top pt-3">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="btn btn-sm btn-ghost-danger">Remove question</button>
                                </form>
                            </div>
                        </details>
                    @empty
                        <p class="text-secondary">No questions yet.</p>
                    @endforelse
                    <div class="card mt-3">
                        <div class="card-header"><h3 class="card-title">Add a question</h3></div>
                        <div class="card-body">
                            @include('operations.settings.partials.booking-question-form', ['question' => null, 'action' => route('operations.settings.booking.questions.store'), 'method' => 'POST', 'submit' => 'Add question'])
                        </div>
                    </div>
                </div>
            @elseif ($tab === 'availability')
                <div class="card-body">
                    <h3 class="mb-1">Days and times customers can book</h3>
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
                        <p class="text-secondary small">Online booking is closed on these dates. You can also open or close a day from the Schedule.</p>
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
                        <input type="hidden" name="section" value="availability">
                        <h3 class="mb-1">Limits</h3>
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
                        <p class="text-secondary small">For services done at the customer's location. Leave empty to accept any address.</p>
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
                            <button type="submit" class="btn btn-primary">Save limits and service area</button>
                        </div>
                    </form>
                </div>
            @else
                <form method="POST" action="{{ route('operations.settings.booking.update') }}" class="card-body">
                    @csrf
                    @method('PATCH')
                    <input type="hidden" name="section" value="website">
                    <h3 class="mb-1">Book Appointment buttons on your website</h3>
                    <p class="text-secondary small">Your website pages already open the booking popup from every "Request an appointment" button. For another website (ARK Sites, WordPress, an imported site), paste this before <code>&lt;/body&gt;</code>. Any link to your booking page then opens the popup, and <code>?concern=…</code> or <code>?service=…</code> on the link prefills it.</p>
                    <div class="mb-3">
                        <label class="form-label" for="booking-link">Booking page (direct link, for texts and social posts)</label>
                        <input id="booking-link" class="form-control" readonly value="{{ $bookingUrl }}" onclick="this.select()">
                    </div>
                    <div class="mb-3">
                        <label class="form-label" for="booking-embed">Embed code</label>
                        <textarea id="booking-embed" class="form-control font-monospace small" rows="3" readonly onclick="this.select()">{{ $embedSnippet }}</textarea>
                        <div class="form-hint">Buttons can also use <code>data-ark-book</code>, with <code>data-concern="Brakes squeal"</code> or <code>data-service="brakes"</code>.</div>
                    </div>
                    <div class="row g-3">
                        <div class="col-sm-6">
                            <label class="form-label" for="booking-button-label">Button label</label>
                            <input id="booking-button-label" name="button_label" maxlength="60" class="form-control" value="{{ old('button_label', $booking['popup']['button_label']) }}">
                        </div>
                        <div class="col-sm-6 d-flex align-items-end">
                            <label class="form-check mb-2">
                                <input type="hidden" name="floating_button" value="0">
                                <input type="checkbox" name="floating_button" value="1" class="form-check-input" @checked(old('floating_button', $booking['popup']['floating_button']))>
                                <span class="form-check-label">Show a floating button on every page</span>
                            </label>
                        </div>
                        <div class="col-12">
                            <label class="form-label" for="booking-domains">Other websites allowed to show the popup</label>
                            <textarea id="booking-domains" name="allowed_domains" rows="2" class="form-control" placeholder="yourshop.com">{{ old('allowed_domains', implode("\n", $booking['popup']['allowed_domains'])) }}</textarea>
                            <div class="form-hint">One per line, without https://. The www. version is included. Sites not listed get the full booking page instead of the popup.</div>
                        </div>
                    </div>
                    <div class="d-flex justify-content-end border-top pt-3 mt-3">
                        <button type="submit" class="btn btn-primary">Save website popup</button>
                    </div>
                </form>
            @endif
        </div>
    </x-tabler.page>
</x-operations.app>
