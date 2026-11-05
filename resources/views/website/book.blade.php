@php
    $shownPhone = (string) old('contact_phone', $contactPhone);
    $shownEmail = (string) old('contact_email', $contactEmail);
    $bookIntent = (string) old('book_intent', '');
    $normalizedPhone = \App\Ark\Operations\PhoneNumber::normalize($shownPhone);
    $verifiedPhone = app(\App\Ark\Operations\Leads\Public\LeadPhoneVerification::class)->verifiedPhone(request()->session());
    $verifiedEmail = app(\App\Ark\Operations\Leads\Public\LeadEmailVerification::class)->verifiedEmail(request()->session());
    $phoneProof = $verifiedPhone !== null && $normalizedPhone !== null && $verifiedPhone === $normalizedPhone;
    $normalizedEmail = \App\Ark\Operations\EmailVerification\EmailVerification::normalizeEmail($shownEmail);
    $emailProof = $normalizedEmail !== null && $verifiedEmail !== null && $normalizedEmail === $verifiedEmail;
    $portalProof = false;
    $portalUser = auth('portal')->user();
    if ($portalUser instanceof \App\Ark\Operations\Customers\Customer) {
        $customerPhone = \App\Ark\Operations\PhoneNumber::normalize((string) ($portalUser->phone ?? ''));
        $portalProof = $customerPhone !== null && $normalizedPhone !== null && $customerPhone === $normalizedPhone;
    }
    $verificationSatisfied = $portalProof || $phoneProof || $emailProof;
    $phoneCodePending = $phoneVerificationReady && ! $phoneProof && ! $portalProof && (
        in_array($bookIntent, ['send_phone_code', 'check_phone_code'], true) || $errors->has('phone_code')
    );
    $emailCodePending = $emailVerificationReady && ! $verificationSatisfied && (
        in_array($bookIntent, ['send_email_code', 'check_email_code'], true) || $errors->has('email_code')
    );
    $emailFieldNeeded = old('contact_preference') === 'email' || $errors->has('contact_email') || $emailCodePending || ($emailProof && ! $phoneProof && ! $portalProof);
    $emailPathOpen = $emailCodePending || $errors->has('contact_email') || in_array($bookIntent, ['send_email_code', 'check_email_code'], true);
    $showVerify = ($phoneVerificationReady || $emailVerificationReady) && ! $portalProof;
@endphp

<x-website.layout :website="$website" :seo="$seo" page="book">
    <div class="public-canvas">
        <h1 class="public-page-title">Request an appointment</h1>
        <p class="public-page-lede">Tell us what the car is doing and when you would like to come in. An advisor confirms the time during business hours. This is a request. It does not reserve a bay.</p>
        <div class="public-canvas__layout public-canvas__layout--form">
            <div>
        @if ($closed)
            <p class="public-page-lede">Online appointment requests are paused. Call or text the shop and we will help you find a time.</p>
        @else
            <form class="public-panel public-book-form public-lead-form" method="post" action="/leads">
                @csrf
                <input type="hidden" name="page" value="book">
                <p class="hidden" aria-hidden="true">
                    <label>Company website <input type="text" name="company_website" tabindex="-1" autocomplete="off"></label>
                </p>

                <section class="public-lead-form__section">
                    <h2 class="public-lead-form__section-title">What's going on?</h2>
                    <div class="public-lead-form__field">
                        <label class="public-lead-form__label" for="concern_category">What is going on?</label>
                        <select class="public-lead-form__input" id="concern_category" name="concern_category" required>
                            @foreach ($concerns as $concern)
                                <option value="{{ $concern }}" @selected(old('concern_category', $selectedConcern) === $concern)>{{ $concern }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="public-lead-form__field">
                        <label class="public-lead-form__label" for="concern_details">Details</label>
                        <textarea class="public-lead-form__textarea" id="concern_details" name="concern_details" rows="3">{{ old('concern_details', $concernDetails) }}</textarea>
                        @error('concern_details')
                            <p class="public-lead-form__error">{{ $message }}</p>
                        @enderror
                    </div>
                </section>

                <section class="public-lead-form__section">
                    <h2 class="public-lead-form__section-title">Vehicle</h2>
                    @if ($vehicles !== [])
                        <div class="public-lead-form__field">
                            <label class="public-lead-form__label" for="vehicle_selection">Vehicle</label>
                            <select class="public-lead-form__input" id="vehicle_selection" name="vehicle_selection">
                                @foreach ($vehicles as $vehicle)
                                    <option value="{{ $vehicle['id'] }}" @selected(old('vehicle_selection') == $vehicle['id'])>{{ $vehicle['label'] }}</option>
                                @endforeach
                                <option value="other" @selected(old('vehicle_selection') === 'other')>A different vehicle</option>
                            </select>
                            @error('vehicle_selection')
                                <p class="public-lead-form__error">{{ $message }}</p>
                            @enderror
                        </div>
                    @endif
                    <div class="public-lead-form__vehicle">
                        <div>
                            <label class="public-lead-form__label" for="vehicle_year">Year</label>
                            <input class="public-lead-form__input" id="vehicle_year" name="vehicle_year" inputmode="numeric" value="{{ old('vehicle_year') }}">
                        </div>
                        <div>
                            <label class="public-lead-form__label" for="vehicle_make">Make</label>
                            <input class="public-lead-form__input" id="vehicle_make" name="vehicle_make" value="{{ old('vehicle_make') }}">
                        </div>
                        <div>
                            <label class="public-lead-form__label" for="vehicle_model">Model</label>
                            <input class="public-lead-form__input" id="vehicle_model" name="vehicle_model" value="{{ old('vehicle_model') }}">
                        </div>
                    </div>
                </section>

                <section class="public-lead-form__section">
                    <h2 class="public-lead-form__section-title">When works for you?</h2>
                    <div class="public-lead-form__field">
                        <label class="public-lead-form__label" for="preferred_date">Preferred day</label>
                        <select class="public-lead-form__input" id="preferred_date" name="preferred_date" required>
                            @foreach ($dates as $date)
                                <option value="{{ $date['date'] }}" @selected(old('preferred_date') === $date['date'])>{{ $date['label'] }}</option>
                            @endforeach
                        </select>
                        @error('preferred_date')
                            <p class="public-lead-form__error">{{ $message }}</p>
                        @enderror
                    </div>
                    <div class="public-lead-form__field">
                        <fieldset>
                            <legend class="public-lead-form__label">Preferred time</legend>
                            <div class="public-lead-form__choices public-lead-form__choices--inline">
                                @foreach ($periods as $period)
                                    <label class="public-lead-form__choice">
                                        <input type="radio" name="preferred_period" value="{{ $period['value'] }}" @checked(old('preferred_period', $periods[0]['value'] ?? '') === $period['value'])>
                                        {{ $period['label'] }}
                                    </label>
                                @endforeach
                            </div>
                        </fieldset>
                    </div>
                </section>

                <section class="public-lead-form__section">
                    <h2 class="public-lead-form__section-title">Your information</h2>
                    <div class="public-lead-form__field">
                        <label class="public-lead-form__label" for="contact_name">Name</label>
                        <input class="public-lead-form__input" id="contact_name" name="contact_name" autocomplete="name" required value="{{ old('contact_name', $contactName) }}">
                    </div>
                    <div class="public-lead-form__field">
                        <label class="public-lead-form__label" for="contact_phone">Phone</label>
                        <input class="public-lead-form__input" id="contact_phone" name="contact_phone" type="tel" autocomplete="tel" required value="{{ old('contact_phone', $contactPhone) }}">
                        @error('contact_phone')
                            <p class="public-lead-form__error">{{ $message }}</p>
                        @enderror
                    </div>
                    <div class="public-lead-form__field">
                        <fieldset>
                            <legend class="public-lead-form__label">How should we reach you?</legend>
                            <div class="public-lead-form__choices">
                                @foreach ($contactPreferences as $preference)
                                    <label class="public-lead-form__choice">
                                        <input type="radio" name="contact_preference" value="{{ $preference->value }}" @checked(old('contact_preference', 'text') === $preference->value)>
                                        {{ $preference->formLabel() }}
                                    </label>
                                @endforeach
                            </div>
                        </fieldset>
                    </div>
                    <div class="public-lead-form__field public-book-form__email @if ($emailFieldNeeded) is-needed @endif">
                        <label class="public-lead-form__label" for="contact_email">Email</label>
                        <input class="public-lead-form__input" id="contact_email" name="contact_email" type="email" autocomplete="email" value="{{ old('contact_email', $contactEmail) }}">
                        @error('contact_email')
                            <p class="public-lead-form__error">{{ $message }}</p>
                        @enderror
                    </div>
                </section>

                @if ($showVerify)
                    <section class="public-lead-form__section">
                        <h2 class="public-lead-form__section-title">Verify</h2>
                        @if ($phoneProof)
                            <p class="public-lead-form__status">Phone verified.</p>
                        @elseif ($emailProof)
                            <p class="public-lead-form__status">Email verified.</p>
                        @else
                            @if (session('book_status'))
                                <p class="public-lead-form__status">{{ session('book_status') }}</p>
                            @endif

                            @if ($phoneVerificationReady)
                                @if ($phoneCodePending)
                                    <div class="public-lead-form__field">
                                        <label class="public-lead-form__label" for="phone_code">Verification code</label>
                                        <input class="public-lead-form__input" id="phone_code" name="phone_code" inputmode="numeric" autocomplete="one-time-code" value="{{ old('phone_code') }}">
                                        @error('phone_code')
                                            <p class="public-lead-form__error">{{ $message }}</p>
                                        @enderror
                                    </div>
                                    <button class="public-book-form__action" type="submit" name="book_intent" value="check_phone_code">Verify</button>
                                @endif
                                <button class="public-book-form__action" type="submit" name="book_intent" value="send_phone_code">Text me a verification code</button>
                            @endif

                            @if ($emailVerificationReady)
                                <details class="public-book-form__email-path" @if ($emailPathOpen) open @endif>
                                    <summary>Verify with email instead</summary>
                                    @if ($emailCodePending)
                                        <div class="public-lead-form__field">
                                            <label class="public-lead-form__label" for="email_code">Verification code</label>
                                            <input class="public-lead-form__input" id="email_code" name="email_code" inputmode="numeric" autocomplete="one-time-code" value="{{ old('email_code') }}">
                                            @error('email_code')
                                                <p class="public-lead-form__error">{{ $message }}</p>
                                            @enderror
                                        </div>
                                        <button class="public-book-form__action" type="submit" name="book_intent" value="check_email_code">Verify email</button>
                                    @endif
                                    <button class="public-book-form__action" type="submit" name="book_intent" value="send_email_code">Email me a code</button>
                                </details>
                            @endif
                        @endif
                    </section>
                @endif

                <div class="public-lead-form__field">
                    <button class="public-cta public-cta--primary" type="submit">Send appointment request</button>
                </div>
            </form>
        @endif
            </div>
            <aside class="public-canvas__rail">
                <ul class="public-canvas__facts">
                    @if ($website->hoursLabel() !== '')
                        <li>Hours: {{ $website->hoursLabel() }}</li>
                    @endif
                    @if ($website->phone() !== '')
                        <li>Phone: <a class="public-link" href="tel:{{ preg_replace('/\D+/', '', $website->phone()) }}">{{ $website->phoneDisplay() }}</a></li>
                    @endif
                    @if ($website->address() !== '')
                        <li>{{ $website->address() }}</li>
                    @endif
                </ul>
                <p class="mt-4"><a class="public-link" href="{{ route('public.contact') }}">Contact the shop</a></p>
            </aside>
        </div>
    </div>
</x-website.layout>
