@php
    use App\Ark\Operations\Booking\BookingMode;
    use App\Ark\Operations\Booking\BookingSettings;
    use App\Ark\Operations\Booking\ServiceLocationType;
    use App\Ark\Operations\PhoneNumber;

    $in = fn (string $key, mixed $default = '') => data_get($input, $key, $default);
    $fields = $settings['fields'];
    $instant = $mode === BookingMode::Instant;
    $hasServices = $services !== [];
    $selectedServiceId = (string) $in('service_id', $selectedService ?? '');
    $onSiteModes = collect($services)->filter(fn (array $s): bool => $s['location_mode'] !== 'shop');
    $showLocation = $onSiteModes->isNotEmpty();
    $locationValue = $in('location', $location->value);
    $vehicleRows = array_values(array_filter((array) $in('vehicles', []), 'is_array'));
    $vehicleSlots = max(1, $settings['max_vehicles']);
    $phoneShown = (string) $in('contact_phone', $portalContact['phone'] ?? '');
    $emailShown = (string) $in('contact_email', $portalContact['email'] ?? '');
    $normalizedPhone = PhoneNumber::normalize($phoneShown);
    $phoneProof = $verifiedPhone !== null && $normalizedPhone !== null && $verifiedPhone === $normalizedPhone;
    $emailProof = $verifiedEmail !== null && strcasecmp($verifiedEmail, trim($emailShown)) === 0;
    $portalProof = filled($portalContact['phone'] ?? null) && PhoneNumber::normalize($portalContact['phone']) === $normalizedPhone;
    $verifyNeeded = ($phoneVerificationReady || $emailVerificationReady) && ! $portalProof;
    $intent = (string) $in('_intent', '');
    $error = fn (string $key): ?string => $errors->first($key) ?: null;
    $phone = $shop->phone ?? null;
    $phoneHref = filled($phone) ? preg_replace('/\D+/', '', (string) $phone) : null;
    $submitLabel = $instant ? 'Book this appointment' : 'Send appointment request';
@endphp

<div class="ark-booking__card" data-ark-booking data-embed="{{ $embed ? '1' : '0' }}" data-availability-url="{{ route('public.book.availability', [], false) }}">
    <header class="ark-booking__header">
        <p class="ark-booking__shop">{{ $shop->displayName() }}</p>
        <h1 class="ark-booking__title" id="ark-booking-title">{{ $settings['title'] }}</h1>
        @if (filled($settings['intro']))
            <p class="ark-booking__intro">{{ $settings['intro'] }}</p>
        @endif
        <p class="ark-booking__mode" data-booking-mode-note>
            @if ($instant)
                <strong>You are booking a confirmed appointment.</strong> Pick an open time and it is reserved for you.
            @else
                <strong>This is a request, not a confirmed appointment.</strong> Choose when works for you. The shop confirms the exact time with you.
            @endif
        </p>
    </header>

    @if ($errors->any())
        <div class="ark-booking__alert ark-booking__alert--error" role="alert" tabindex="-1" data-booking-errors>
            <p class="ark-booking__alert-title">Please check the highlighted details.</p>
            <ul>
                @foreach ($errors->all() as $message)
                    <li>{{ $message }}</li>
                @endforeach
            </ul>
        </div>
    @endif
    @if (filled($notice))
        <p class="ark-booking__alert ark-booking__alert--ok" role="status">{{ $notice }}</p>
    @endif

    @if (! $settings['enabled'] || ! $availability['accepting'])
        <div class="ark-booking__closed">
            <p>{{ $settings['enabled'] ? $availability['message'] : 'Online booking is not available right now.' }}</p>
            @if ($phoneHref)
                <p><a class="public-cta public-cta--primary" href="tel:{{ $phoneHref }}" target="_top">Call {{ PhoneNumber::display($phone) ?? $phone }}</a></p>
            @endif
        </div>
    @else
        <form class="ark-booking__form" method="post" action="{{ route('public.book.store', [], false) }}" novalidate data-booking-form aria-labelledby="ark-booking-title">
            <input type="hidden" name="_token" value="{{ csrf_token() }}">
            <input type="hidden" name="form_rendered_at" value="{{ $renderStamp }}">
            <input type="hidden" name="submission_key" value="{{ $submissionKey }}">
            <input type="hidden" name="identity_token" value="{{ $identityToken }}">
            <input type="hidden" name="source_page" value="{{ $in('source_page') }}">
            <input type="hidden" name="source_host" value="{{ $in('source_host') }}">
            @if ($embed)
                <input type="hidden" name="embed" value="1">
            @endif
            <p class="ark-booking__trap" aria-hidden="true">
                <label>Company website <input type="text" name="company_website" tabindex="-1" autocomplete="off"></label>
            </p>

            <ol class="ark-booking__progress" data-booking-progress hidden aria-label="Booking steps"></ol>
            <p class="sr-only" aria-live="polite" data-booking-live></p>

            @if ($hasServices)
                <section class="ark-booking__step" data-book-step="service" data-step-label="Service" aria-labelledby="book-step-service">
                    <h2 class="ark-booking__step-title" id="book-step-service">What can we help with?</h2>
                    <fieldset>
                        <legend class="sr-only">Service</legend>
                        <div class="ark-booking__services">
                            @foreach ($services as $service)
                                <label class="ark-booking__service" data-service-card>
                                    <input
                                        type="radio"
                                        name="service_id"
                                        value="{{ $service['id'] }}"
                                        required
                                        data-location-mode="{{ $service['location_mode'] }}"
                                        data-booking-mode="{{ $serviceModes[$service['id']] ?? $mode->value }}"
                                        data-concern-prompt="{{ $service['concern_prompt'] }}"
                                        @checked($selectedServiceId === (string) $service['id'])
                                    >
                                    <span class="ark-booking__service-body">
                                        <span class="ark-booking__service-name">{{ $service['name'] }}</span>
                                        @if ($service['description'] !== '')
                                            <span class="ark-booking__service-text">{{ $service['description'] }}</span>
                                        @endif
                                        <span class="ark-booking__service-meta">
                                            About {{ $service['duration_label'] }}
                                            @if ($service['location_mode'] === 'mobile') · We come to you @elseif ($service['location_mode'] === 'either') · At the shop or at your location @endif
                                        </span>
                                    </span>
                                </label>
                            @endforeach
                        </div>
                    </fieldset>
                    @if ($error('service_id'))<p class="ark-booking__error" id="service_id-error">{{ $error('service_id') }}</p>@endif

                    @foreach ($services as $service)
                        @if ($service['options'] !== [])
                            <div class="ark-booking__field" data-service-options="{{ $service['id'] }}">
                                <label class="ark-booking__label" for="service-option-{{ $service['id'] }}">{{ $service['name'] }}: which one?</label>
                                <select class="public-lead-form__input" id="service-option-{{ $service['id'] }}" name="service_option" data-required-when-shown @disabled($selectedServiceId !== (string) $service['id'])>
                                    <option value="">Choose…</option>
                                    @foreach ($service['options'] as $option)
                                        <option value="{{ $option['label'] }}" data-requires-details="{{ $option['requires_details'] ? '1' : '0' }}" @selected($in('service_option') === $option['label'])>{{ $option['label'] }}</option>
                                    @endforeach
                                </select>
                            </div>
                        @endif
                    @endforeach
                    @if ($error('service_option'))<p class="ark-booking__error">{{ $error('service_option') }}</p>@endif
                </section>
            @endif

            <section class="ark-booking__step" data-book-step="concern" data-step-label="Concern" aria-labelledby="book-step-concern">
                <h2 class="ark-booking__step-title" id="book-step-concern">What is the vehicle doing?</h2>
                @if ($settings['concern_choices'] !== [])
                    <fieldset class="ark-booking__field">
                        <legend class="ark-booking__label">Pick the closest match <span class="ark-booking__optional">(optional)</span></legend>
                        <div class="ark-booking__chips">
                            @foreach ($settings['concern_choices'] as $choice)
                                <label class="ark-booking__chip">
                                    <input type="radio" name="concern_choice" value="{{ $choice }}" @checked($in('concern_choice') === $choice)>
                                    <span>{{ $choice }}</span>
                                </label>
                            @endforeach
                        </div>
                    </fieldset>
                @endif
                <div class="ark-booking__field">
                    <label class="ark-booking__label" for="book-concern">
                        Describe it in your own words
                        @if ($fields['concern'] !== BookingSettings::FIELD_REQUIRED)<span class="ark-booking__optional">(optional)</span>@endif
                    </label>
                    <textarea
                        class="public-lead-form__textarea"
                        id="book-concern"
                        name="concern"
                        rows="4"
                        maxlength="2000"
                        placeholder="Sounds, smells, warning lights, when it started…"
                        data-default-placeholder="Sounds, smells, warning lights, when it started…"
                        @if ($fields['concern'] === BookingSettings::FIELD_REQUIRED) required @endif
                        @if ($error('concern')) aria-invalid="true" aria-describedby="concern-error" @endif
                    >{{ $in('concern') }}</textarea>
                    @if ($error('concern'))<p class="ark-booking__error" id="concern-error">{{ $error('concern') }}</p>@endif
                </div>
                @if ($fields['drivability'])
                    <div class="ark-booking__field ark-booking__checks">
                        <label class="ark-booking__check"><input type="checkbox" name="unsafe_to_drive" value="1" @checked($in('unsafe_to_drive'))> It is not safe to drive</label>
                        <label class="ark-booking__check"><input type="checkbox" name="needs_tow" value="1" @checked($in('needs_tow'))> It needs a tow</label>
                    </div>
                @endif
            </section>

            @if ($fields['vehicle'] !== BookingSettings::FIELD_HIDDEN)
                <section class="ark-booking__step" data-book-step="vehicle" data-step-label="Vehicle" aria-labelledby="book-step-vehicle">
                    <h2 class="ark-booking__step-title" id="book-step-vehicle">
                        {{ $vehicleSlots > 1 ? 'Which vehicles?' : 'Which vehicle?' }}
                        @if ($fields['vehicle'] !== BookingSettings::FIELD_REQUIRED)<span class="ark-booking__optional">(optional)</span>@endif
                    </h2>
                    @if ($portalVehicles !== [])
                        <div class="ark-booking__field">
                            <label class="ark-booking__label" for="book-vehicle-selection">Your vehicles</label>
                            <select class="public-lead-form__input" id="book-vehicle-selection" name="vehicle_selection">
                                @foreach ($portalVehicles as $vehicle)
                                    <option value="{{ $vehicle['id'] }}" @selected((string) $in('vehicle_selection') === (string) $vehicle['id'])>{{ $vehicle['label'] }}</option>
                                @endforeach
                                <option value="other" @selected($in('vehicle_selection') === 'other')>A different vehicle</option>
                            </select>
                        </div>
                    @endif
                    @for ($i = 0; $i < $vehicleSlots; $i++)
                        @php($row = $vehicleRows[$i] ?? [])
                        <fieldset class="ark-booking__vehicle" data-vehicle-row="{{ $i }}" @if ($i > 0 && array_filter($row) === []) data-vehicle-extra @endif>
                            <legend class="ark-booking__label">{{ $vehicleSlots > 1 ? 'Vehicle '.($i + 1) : 'Vehicle' }}</legend>
                            <div class="ark-booking__grid ark-booking__grid--{{ $fields['vin'] ? 4 : 3 }}">
                                <div>
                                    <label class="ark-booking__sublabel" for="vehicle-{{ $i }}-year">Year</label>
                                    <input class="public-lead-form__input" id="vehicle-{{ $i }}-year" name="vehicles[{{ $i }}][year]" inputmode="numeric" maxlength="4" autocomplete="off" value="{{ $row['year'] ?? '' }}" @if ($i === 0 && $fields['vehicle'] === BookingSettings::FIELD_REQUIRED && $portalVehicles === []) required @endif>
                                </div>
                                <div>
                                    <label class="ark-booking__sublabel" for="vehicle-{{ $i }}-make">Make</label>
                                    <input class="public-lead-form__input" id="vehicle-{{ $i }}-make" name="vehicles[{{ $i }}][make]" maxlength="80" value="{{ $row['make'] ?? '' }}" @if ($i === 0 && $fields['vehicle'] === BookingSettings::FIELD_REQUIRED && $portalVehicles === []) required @endif>
                                </div>
                                <div>
                                    <label class="ark-booking__sublabel" for="vehicle-{{ $i }}-model">Model</label>
                                    <input class="public-lead-form__input" id="vehicle-{{ $i }}-model" name="vehicles[{{ $i }}][model]" maxlength="80" value="{{ $row['model'] ?? '' }}" @if ($i === 0 && $fields['vehicle'] === BookingSettings::FIELD_REQUIRED && $portalVehicles === []) required @endif>
                                </div>
                                @if ($fields['vin'])
                                    <div>
                                        <label class="ark-booking__sublabel" for="vehicle-{{ $i }}-vin">VIN <span class="ark-booking__optional">(optional)</span></label>
                                        <input class="public-lead-form__input" id="vehicle-{{ $i }}-vin" name="vehicles[{{ $i }}][vin]" maxlength="17" autocapitalize="characters" spellcheck="false" value="{{ $row['vin'] ?? '' }}">
                                    </div>
                                @endif
                            </div>
                        </fieldset>
                    @endfor
                    @if ($error('vehicles.0.make'))<p class="ark-booking__error">{{ $error('vehicles.0.make') }}</p>@endif
                    @if ($vehicleSlots > 1)
                        <button type="button" class="ark-booking__link-button" data-add-vehicle hidden>Add another vehicle</button>
                    @endif
                </section>
            @endif

            @if ($showLocation)
                <section class="ark-booking__step" data-book-step="location" data-step-label="Location" data-location-step aria-labelledby="book-step-location">
                    <h2 class="ark-booking__step-title" id="book-step-location">Where should the work happen?</h2>
                    <fieldset class="ark-booking__field" data-location-choice>
                        <legend class="sr-only">Service location</legend>
                        <div class="ark-booking__chips">
                            <label class="ark-booking__chip" data-location-option="shop"><input type="radio" name="location" value="{{ ServiceLocationType::Shop->value }}" @checked($locationValue === ServiceLocationType::Shop->value)> <span>At the shop</span></label>
                            <label class="ark-booking__chip"><input type="radio" name="location" value="{{ ServiceLocationType::CustomerSite->value }}" @checked($locationValue === ServiceLocationType::CustomerSite->value)> <span>At my home or work</span></label>
                            @if ($vehicleSlots > 1)
                                <label class="ark-booking__chip"><input type="radio" name="location" value="{{ ServiceLocationType::FleetSite->value }}" @checked($locationValue === ServiceLocationType::FleetSite->value)> <span>At our business or fleet yard</span></label>
                            @endif
                        </div>
                    </fieldset>
                    <div data-address-fields>
                        <div class="ark-booking__field">
                            <label class="ark-booking__label" for="address-line1">Street address</label>
                            <input class="public-lead-form__input" id="address-line1" name="address[line1]" autocomplete="address-line1" maxlength="160" value="{{ $in('address.line1') }}" data-required-on-site @if ($error('address.line1')) aria-invalid="true" @endif>
                            @if ($error('address.line1'))<p class="ark-booking__error">{{ $error('address.line1') }}</p>@endif
                        </div>
                        <div class="ark-booking__field">
                            <label class="ark-booking__label" for="address-line2">Apartment, suite, gate <span class="ark-booking__optional">(optional)</span></label>
                            <input class="public-lead-form__input" id="address-line2" name="address[line2]" autocomplete="address-line2" maxlength="160" value="{{ $in('address.line2') }}">
                        </div>
                        <div class="ark-booking__grid ark-booking__grid--3">
                            <div>
                                <label class="ark-booking__sublabel" for="address-city">City</label>
                                <input class="public-lead-form__input" id="address-city" name="address[city]" autocomplete="address-level2" maxlength="80" value="{{ $in('address.city') }}" data-required-on-site>
                            </div>
                            <div>
                                <label class="ark-booking__sublabel" for="address-state">State</label>
                                <input class="public-lead-form__input" id="address-state" name="address[state]" autocomplete="address-level1" maxlength="40" value="{{ $in('address.state', $shop->state ?? '') }}">
                            </div>
                            <div>
                                <label class="ark-booking__sublabel" for="address-postal">ZIP code</label>
                                <input class="public-lead-form__input" id="address-postal" name="address[postal_code]" autocomplete="postal-code" inputmode="numeric" maxlength="12" value="{{ $in('address.postal_code') }}" data-required-on-site data-availability-input>
                            </div>
                        </div>
                        @if ($error('address.city') || $error('address.postal_code'))<p class="ark-booking__error">{{ $error('address.city') ?? $error('address.postal_code') }}</p>@endif
                        <div class="ark-booking__field">
                            <label class="ark-booking__label" for="address-notes">Parking or access notes <span class="ark-booking__optional">(optional)</span></label>
                            <textarea class="public-lead-form__textarea" id="address-notes" name="address[notes]" rows="2" maxlength="500" placeholder="Gate code, where the vehicle is parked…">{{ $in('address.notes') }}</textarea>
                        </div>
                    </div>
                </section>
            @endif

            <section class="ark-booking__step" data-book-step="when" data-step-label="Day and time" aria-labelledby="book-step-when">
                <h2 class="ark-booking__step-title" id="book-step-when">{{ $instant ? 'Pick a time' : 'When works for you?' }}</h2>
                <p class="ark-booking__hint" data-when-hint>
                    @if ($instant)
                        Only open times are shown. Times are in the shop's local time ({{ $availability['timezone'] }}).
                    @else
                        Pick a day and a time of day. This is your preference; the shop confirms the exact time.
                    @endif
                </p>
                <div class="ark-booking__field">
                    <label class="ark-booking__label" for="book-date">Day</label>
                    <select class="public-lead-form__input" id="book-date" name="preferred_date" required data-booking-date @if ($error('preferred_date')) aria-invalid="true" aria-describedby="preferred_date-error" @endif>
                        @foreach ($availability['dates'] as $date)
                            <option value="{{ $date['date'] }}" @selected($selectedDate === $date['date']) @disabled(! $date['available'])>
                                {{ $date['label'] }}@if (! $date['available']) - {{ $date['reason'] }}@endif
                            </option>
                        @endforeach
                    </select>
                    @if ($error('preferred_date'))<p class="ark-booking__error" id="preferred_date-error">{{ $error('preferred_date') }}</p>@endif
                </div>

                <fieldset class="ark-booking__field" data-booking-periods @if ($instant) hidden @endif>
                    <legend class="ark-booking__label">Time of day</legend>
                    <div class="ark-booking__chips" data-period-options>
                        @foreach ($periods as $period)
                            <label class="ark-booking__chip">
                                <input type="radio" name="preferred_period" value="{{ $period['value'] }}" @checked($in('preferred_period', $periods[0]['value'] ?? '') === $period['value']) @if (! $instant) required @endif>
                                <span>{{ $period['label'] }}</span>
                            </label>
                        @endforeach
                    </div>
                    @if ($error('preferred_period'))<p class="ark-booking__error">{{ $error('preferred_period') }}</p>@endif
                </fieldset>

                <div class="ark-booking__field" data-booking-slots @if (! $instant) hidden @endif>
                    <label class="ark-booking__label" for="book-time">Time</label>
                    <select class="public-lead-form__input" id="book-time" name="preferred_time" @if ($instant) required @endif data-booking-time>
                        @forelse ($slots as $slot)
                            <option value="{{ $slot['value'] }}" @selected($in('preferred_time') === $slot['value'])>{{ $slot['label'] }}</option>
                        @empty
                            <option value="">No open times that day</option>
                        @endforelse
                    </select>
                    @if ($error('preferred_time'))<p class="ark-booking__error">{{ $error('preferred_time') }}</p>@endif
                    <noscript><button type="submit" name="book_intent" value="refresh" class="ark-booking__link-button" formnovalidate>Show times for this day</button></noscript>
                </div>
            </section>

            <section class="ark-booking__step" data-book-step="contact" data-step-label="Contact" aria-labelledby="book-step-contact">
                <h2 class="ark-booking__step-title" id="book-step-contact">How do we reach you?</h2>
                <div class="ark-booking__field">
                    <label class="ark-booking__label" for="book-name">Name</label>
                    <input class="public-lead-form__input" id="book-name" name="contact_name" autocomplete="name" required maxlength="120" value="{{ $in('contact_name', $portalContact['name'] ?? '') }}" @if ($error('contact_name')) aria-invalid="true" @endif>
                    @if ($error('contact_name'))<p class="ark-booking__error">{{ $error('contact_name') }}</p>@endif
                </div>
                <div class="ark-booking__field">
                    <label class="ark-booking__label" for="book-phone">Mobile phone</label>
                    <input class="public-lead-form__input" id="book-phone" name="contact_phone" type="tel" autocomplete="tel" required maxlength="32" value="{{ $phoneShown }}" @if ($error('contact_phone')) aria-invalid="true" aria-describedby="contact_phone-error" @endif>
                    @if ($error('contact_phone'))<p class="ark-booking__error" id="contact_phone-error">{{ $error('contact_phone') }}</p>@endif
                </div>
                @if ($fields['email'] !== BookingSettings::FIELD_HIDDEN || $emailVerificationReady)
                    <div class="ark-booking__field">
                        <label class="ark-booking__label" for="book-email">
                            Email
                            @if ($fields['email'] !== BookingSettings::FIELD_REQUIRED)<span class="ark-booking__optional">(optional)</span>@endif
                        </label>
                        <input class="public-lead-form__input" id="book-email" name="contact_email" type="email" autocomplete="email" maxlength="255" value="{{ $emailShown }}" @if ($fields['email'] === BookingSettings::FIELD_REQUIRED) required @endif @if ($error('contact_email')) aria-invalid="true" @endif>
                        @if ($error('contact_email'))<p class="ark-booking__error">{{ $error('contact_email') }}</p>@endif
                    </div>
                @endif
                @if ($fields['contact_preference'])
                    <fieldset class="ark-booking__field">
                        <legend class="ark-booking__label">Best way to reach you</legend>
                        <div class="ark-booking__chips">
                            @foreach ($contactPreferences as $preference)
                                @continue($preference->value === 'email' && $fields['email'] === BookingSettings::FIELD_HIDDEN)
                                <label class="ark-booking__chip">
                                    <input type="radio" name="contact_preference" value="{{ $preference->value }}" @checked($in('contact_preference', 'text') === $preference->value)>
                                    <span>{{ $preference->formLabel() }}</span>
                                </label>
                            @endforeach
                        </div>
                    </fieldset>
                @endif

                @if ($verifyNeeded)
                    <div class="ark-booking__verify" data-booking-verify>
                        @if ($phoneProof)
                            <p class="ark-booking__status">Phone verified.</p>
                        @elseif ($emailProof)
                            <p class="ark-booking__status">Email verified.</p>
                        @else
                            <p class="ark-booking__hint">We confirm your number before sending your booking to the shop.</p>
                            @if ($phoneVerificationReady)
                                @if (in_array($intent, ['send_phone_code', 'check_phone_code'], true))
                                    <div class="ark-booking__field">
                                        <label class="ark-booking__label" for="book-phone-code">Code we texted you</label>
                                        <input class="public-lead-form__input" id="book-phone-code" name="phone_code" inputmode="numeric" autocomplete="one-time-code" maxlength="10">
                                    </div>
                                    <button class="public-cta public-cta--secondary" type="submit" name="book_intent" value="check_phone_code" formnovalidate>Verify code</button>
                                @endif
                                <button class="ark-booking__link-button" type="submit" name="book_intent" value="send_phone_code" formnovalidate>Text me a code</button>
                            @endif
                            @if ($emailVerificationReady)
                                @if (in_array($intent, ['send_email_code', 'check_email_code'], true))
                                    <div class="ark-booking__field">
                                        <label class="ark-booking__label" for="book-email-code">Code we emailed you</label>
                                        <input class="public-lead-form__input" id="book-email-code" name="email_code" inputmode="numeric" autocomplete="one-time-code" maxlength="10">
                                    </div>
                                    <button class="public-cta public-cta--secondary" type="submit" name="book_intent" value="check_email_code" formnovalidate>Verify email code</button>
                                @endif
                                <button class="ark-booking__link-button" type="submit" name="book_intent" value="send_email_code" formnovalidate>Email me a code instead</button>
                            @endif
                        @endif
                    </div>
                @endif
            </section>

            @if ($questions !== [])
                <section class="ark-booking__step" data-book-step="questions" data-step-label="Questions" aria-labelledby="book-step-questions">
                    <h2 class="ark-booking__step-title" id="book-step-questions">A few questions from the shop</h2>
                    @foreach ($questions as $question)
                        @php($name = 'answers['.$question['id'].']')
                        @php($value = (string) $in('answers.'.$question['id']))
                        @php($fieldId = 'question-'.$question['id'])
                        <div class="ark-booking__field" data-question data-services="{{ implode(',', $question['service_ids']) }}">
                            @if ($question['type'] === 'choice' || $question['type'] === 'yes_no')
                                <fieldset>
                                    <legend class="ark-booking__label">{{ $question['label'] }} @unless ($question['required'])<span class="ark-booking__optional">(optional)</span>@endunless</legend>
                                    @if ($question['help'] !== '')<p class="ark-booking__hint">{{ $question['help'] }}</p>@endif
                                    <div class="ark-booking__chips">
                                        @foreach ($question['type'] === 'yes_no' ? ['yes' => 'Yes', 'no' => 'No'] : array_combine($question['choices'], $question['choices']) as $choiceValue => $choiceLabel)
                                            <label class="ark-booking__chip">
                                                <input type="radio" name="{{ $name }}" value="{{ $choiceValue }}" @checked($value === (string) $choiceValue) @if ($question['required']) required @endif>
                                                <span>{{ $choiceLabel }}</span>
                                            </label>
                                        @endforeach
                                    </div>
                                </fieldset>
                            @else
                                <label class="ark-booking__label" for="{{ $fieldId }}">{{ $question['label'] }} @unless ($question['required'])<span class="ark-booking__optional">(optional)</span>@endunless</label>
                                @if ($question['help'] !== '')<p class="ark-booking__hint">{{ $question['help'] }}</p>@endif
                                @if ($question['type'] === 'paragraph')
                                    <textarea class="public-lead-form__textarea" id="{{ $fieldId }}" name="{{ $name }}" rows="3" maxlength="1000" @if ($question['required']) required @endif>{{ $value }}</textarea>
                                @else
                                    <input class="public-lead-form__input" id="{{ $fieldId }}" name="{{ $name }}" maxlength="200" value="{{ $value }}" @if ($question['required']) required @endif>
                                @endif
                            @endif
                            @if ($error('answers.'.$question['id']))<p class="ark-booking__error">{{ $error('answers.'.$question['id']) }}</p>@endif
                        </div>
                    @endforeach
                </section>
            @endif

            <section class="ark-booking__step" data-book-step="review" data-step-label="Review" aria-labelledby="book-step-review">
                <h2 class="ark-booking__step-title" id="book-step-review">Review and send</h2>
                <dl class="ark-booking__summary" data-booking-summary hidden></dl>
                @if (filled($settings['instructions']))
                    <div class="ark-booking__instructions">
                        <p class="ark-booking__label">From the shop</p>
                        <p>{!! nl2br(e($settings['instructions'])) !!}</p>
                    </div>
                @endif
                <p class="ark-booking__mode ark-booking__mode--final" data-booking-final-note>
                    @if ($instant)
                        When you press <strong>{{ $submitLabel }}</strong>, the time you picked is reserved for you.
                    @else
                        When you press <strong>{{ $submitLabel }}</strong>, the shop receives your request. Your appointment is <strong>not confirmed</strong> until the shop contacts you.
                    @endif
                </p>
                @if ($error('form'))<p class="ark-booking__error">{{ $error('form') }}</p>@endif
                <button class="public-cta public-cta--primary ark-booking__submit" type="submit" name="book_intent" value="submit" data-booking-submit data-label-request="Send appointment request" data-label-instant="Book this appointment">{{ $submitLabel }}</button>
            </section>

            <div class="ark-booking__nav" data-booking-nav hidden>
                <button type="button" class="public-cta public-cta--secondary" data-booking-back>Back</button>
                <button type="button" class="public-cta public-cta--primary" data-booking-next>Next</button>
            </div>
        </form>
    @endif

    <footer class="ark-booking__footer">
        @if ($phoneHref)
            <span>Prefer to talk? <a class="public-link" href="tel:{{ $phoneHref }}" target="_top">{{ PhoneNumber::display($phone) ?? $phone }}</a></span>
        @endif
        @if (! $embed && Route::has('public.contact') && $website !== null)
            <span><a class="public-link" href="{{ route('public.contact') }}">General question? Contact us</a></span>
        @endif
    </footer>
</div>
