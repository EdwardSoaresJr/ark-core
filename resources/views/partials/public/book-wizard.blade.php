@php
    use App\Ark\Operations\Leads\LeadContactNameParser;
    use App\Ark\Operations\Leads\LeadContactPreference;
    use App\Ark\Operations\Leads\Public\PublicBookWizardConcerns;
    use App\Ark\Operations\Leads\Public\PublicLeadFormContactPrefill;
    use App\Ark\Operations\Leads\Public\PublicLeadFormCopy;

    $contactPrefill = app(PublicLeadFormContactPrefill::class)->forCurrentCustomer();
    $concernOptions = PublicBookWizardConcerns::options();
    $concernFromQuery = trim((string) old('concern', request('concern', '')));
    $matchedCategory = old('concern_category');
    $concernDetails = old('concern_details', '');

    if (! is_string($matchedCategory) || $matchedCategory === '') {
        $matchedCategory = PublicBookWizardConcerns::matchCategory($concernFromQuery) ?? '';
        if ($matchedCategory === PublicBookWizardConcerns::SOMETHING_ELSE) {
            $concernDetails = $concernDetails !== '' ? $concernDetails : $concernFromQuery;
        } elseif ($matchedCategory !== '' && strcasecmp($concernFromQuery, $matchedCategory) !== 0 && $concernFromQuery !== '') {
            $concernDetails = $concernDetails !== '' ? $concernDetails : $concernFromQuery;
        }
    }

    $fullName = trim((string) old('full_name', ''));
    if ($fullName === '') {
        $fullName = LeadContactNameParser::formatFullName(
            old('first_name', $contactPrefill['first_name']),
            old('last_name', $contactPrefill['last_name']),
        );
    }
    $gateVerifiedPhone = trim((string) ($gateVerifiedPhone ?? ''));
    $gateVerifiedEmail = trim((string) ($gateVerifiedEmail ?? ''));
    $gateVerifiedDisplay = $gateVerifiedPhone !== ''
        ? (\App\Ark\Operations\PhoneNumber::display($gateVerifiedPhone) ?? $gateVerifiedPhone)
        : '';
    $leadPrefillPhone = old('phone', $gateVerifiedDisplay !== '' ? $gateVerifiedDisplay : $contactPrefill['phone']);
    $leadPrefillEmail = old('email', $gateVerifiedEmail !== '' ? $gateVerifiedEmail : $contactPrefill['email']);
    $contactPreference = old('contact_preference', $contactPrefill['contact_preference'] ?: LeadContactPreference::Text->value);
    $trustedAccountPhone = $contactPrefill['skips_phone_verification']
        ? $contactPrefill['phone']
        : ($gateVerifiedDisplay !== '' ? $gateVerifiedDisplay : '');
    // Identity gate already verified (SMS or email) — don't ask again on the guest contact step.
    $phoneVerificationRequired = (bool) ($phoneVerificationRequired ?? false)
        && $gateVerifiedDisplay === ''
        && $gateVerifiedEmail === '';
    $requestAvailability = $requestAvailability ?? \App\Ark\Operations\Leads\Public\PublicAppointmentRequest::availabilityProjection();
    $preferredDate = old('preferred_date', '');
    $preferredPeriod = old('preferred_period', '');
    $vehicleSelection = old('vehicle_selection', $contactPrefill['default_vehicle_selection'] ?? ($contactPrefill['vehicles'] !== [] ? (string) $contactPrefill['vehicles'][0]['id'] : 'other'));
    $closeUrl = \App\Ark\Customer\CustomerSurfaceUrls::publicHome();
    $hasServerErrors = $errors->any();
    $recognizedSchedule = (bool) ($recognizedSchedule ?? false);
    $scheduleVehicleId = $scheduleVehicleId ?? null;
    $scheduleRadarIds = is_array($scheduleRadarIds ?? null) ? $scheduleRadarIds : [];
    $scheduleIntent = trim((string) ($scheduleIntent ?? ''));
    $scheduleIntentDetails = trim((string) ($scheduleIntentDetails ?? ''));
    $vehicleHome = is_array($vehicleHome ?? null) ? $vehicleHome : null;
    $radarItems = collect($vehicleHome['relationship']['still_on_radar'] ?? [])
        ->filter(fn (array $item): bool => in_array((int) ($item['concern_id'] ?? 0), array_map('intval', $scheduleRadarIds), true))
        ->values()
        ->all();
    if ($recognizedSchedule) {
        if ($scheduleIntent !== '') {
            $matchedCategory = $scheduleIntent;
        }
        $detailParts = [];
        if ($scheduleIntentDetails !== '') {
            $detailParts[] = $scheduleIntentDetails;
        }
        if ($radarItems !== []) {
            $detailParts[] = "While it's here:\n".collect($radarItems)->pluck('summary')->filter()->implode("\n");
        }
        if ($concernDetails === '' && $detailParts !== []) {
            $concernDetails = implode("\n\n", $detailParts);
        }
        if ($scheduleVehicleId) {
            $vehicleSelection = (string) $scheduleVehicleId;
        }
    }
@endphp

<div
    class="public-book-wizard"
    x-data="publicBookWizard({
        dates: @js($requestAvailability['dates'] ?? []),
        vehicles: @js($contactPrefill['vehicles'] ?? []),
        somethingElse: @js(PublicBookWizardConcerns::SOMETHING_ELSE),
        closeUrl: @js($closeUrl),
        hasServerErrors: @js($hasServerErrors),
        concernFromQuery: @js($concernFromQuery !== ''),
        concernCategory: @js($matchedCategory),
        concernDetails: @js($concernDetails),
        vehicleSelection: @js((string) $vehicleSelection),
        vehicleYear: @js(old('vehicle_year', '')),
        vehicleMake: @js(old('vehicle_make', '')),
        vehicleModel: @js(old('vehicle_model', '')),
        preferredDate: @js($preferredDate),
        preferredPeriod: @js($preferredPeriod),
        fullName: @js($fullName),
        phone: @js($leadPrefillPhone),
        email: @js($leadPrefillEmail),
        contactPreference: @js($contactPreference),
        phoneVerificationRequired: @js($phoneVerificationRequired),
        recognizedSchedule: @js($recognizedSchedule),
        radarItems: @js($radarItems),
        startStep: @js($recognizedSchedule ? 'when' : 'concern'),
    })"
>
    <div class="public-book-wizard__chrome">
        <div class="public-book-wizard__progress" aria-hidden="true">
            <div class="public-book-wizard__progress-bar" :style="`width: ${progressFraction() * 100}%`"></div>
        </div>

        <div class="public-book-wizard__toolbar">
            <button
                type="button"
                class="public-book-wizard__text-btn"
                x-show="stepIndex() > 0"
                x-cloak
                @click="back()"
            >
                Back
            </button>
            <span class="public-book-wizard__toolbar-spacer" x-show="stepIndex() === 0"></span>

            <div class="public-book-wizard__toolbar-actions">
                <button
                    type="button"
                    class="public-book-wizard__text-btn"
                    x-show="draftNotice"
                    x-cloak
                    @click="discardDraft()"
                >
                    Start over
                </button>
                <a
                    href="{{ $closeUrl }}"
                    class="public-book-wizard__close"
                    data-public-book-close
                    aria-label="Close"
                    title="Close — your progress is saved for a few hours"
                >
                    <span aria-hidden="true">×</span>
                </a>
            </div>
        </div>

        <p class="public-book-wizard__restored" x-show="draftNotice" x-cloak>
            Picking up where you left off.
        </p>
    </div>

    @if ($errors->any())
        <div class="public-book-wizard__errors" role="alert">
            <ul>
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <p class="public-book-wizard__error" x-show="errorMessage" x-text="errorMessage" x-cloak></p>

    <form
        method="POST"
        action="{{ route('public.leads.store') }}"
        class="public-book-wizard__form"
        @submit="prepareSubmit($event)"
        @keydown.enter="if (step !== 'contact') { $event.preventDefault() }"
        @if ($surfaceContext ?? null)
            data-public-surface-page="{{ $surfaceContext->page }}"
            data-public-surface-variant="{{ $surfaceContext->variant }}"
            data-public-surface-placement="{{ $surfaceContext->placement }}"
        @endif
    >
        @csrf
        <input type="hidden" name="source" value="website">
        <input type="hidden" name="form_rendered_at" value="{{ $formRenderedAt ?? now()->timestamp }}">
        @if ($surfaceContext ?? null)
            <input type="hidden" name="public_surface_page" value="{{ $surfaceContext->page }}">
            <input type="hidden" name="public_surface_variant" value="{{ $surfaceContext->variant }}">
            <input type="hidden" name="public_surface_placement" value="{{ $surfaceContext->placement }}">
        @endif

        <div class="hidden" aria-hidden="true">
            <label for="company_website">Company website</label>
            <input type="text" name="company_website" id="company_website" tabindex="-1" autocomplete="off">
        </div>

        <textarea x-ref="concern" name="concern" class="sr-only" tabindex="-1" aria-hidden="true">{{ old('concern', PublicBookWizardConcerns::composeConcern($matchedCategory, $concernDetails)) }}</textarea>
        <input type="hidden" name="preferred_date" :value="preferredDate">
        <input type="hidden" name="preferred_period" :value="preferredPeriod">
        <input type="hidden" name="contact_preference" :value="contactPreference">
        <input type="hidden" name="vehicle_selection" :value="vehicleSelection">
        <input x-ref="firstName" type="hidden" name="first_name" value="{{ old('first_name', $contactPrefill['first_name']) }}">
        <input x-ref="lastName" type="hidden" name="last_name" value="{{ old('last_name', $contactPrefill['last_name']) }}">
        @foreach ($radarItems as $radarItem)
            <input type="hidden" name="still_on_radar[]" value="{{ (int) $radarItem['concern_id'] }}">
        @endforeach

        @unless ($recognizedSchedule)
        {{-- Step: concern (guest path only — recognized chose intent on Vehicle Home) --}}
        <section class="public-book-wizard__step" x-show="step === 'concern'">
            <p class="public-book-wizard__kicker">Let’s get you scheduled</p>
            <h1 class="public-book-wizard__question">What can we help you with?</h1>
            <div class="public-book-wizard__choices">
                @foreach ($concernOptions as $option)
                    <button
                        type="button"
                        class="public-book-wizard__choice"
                        :class="{ 'is-selected': concernCategory === @js($option) }"
                        @click="selectConcern(@js($option))"
                    >
                        {{ $option }}
                    </button>
                @endforeach
            </div>
        </section>

        {{-- Step: vehicle (guest / unrecognized only) --}}
        <section class="public-book-wizard__step" x-show="step === 'vehicle'" x-cloak>
            <p class="public-book-wizard__kicker">Great.</p>
            <h2 class="public-book-wizard__question">Which vehicle?</h2>

            @if (($contactPrefill['vehicles'] ?? []) !== [])
                <div class="public-book-wizard__choices">
                    @foreach ($contactPrefill['vehicles'] as $vehicle)
                        <button
                            type="button"
                            class="public-book-wizard__choice"
                            :class="{ 'is-selected': vehicleSelection === @js((string) $vehicle['id']) }"
                            @click="vehicleSelection = @js((string) $vehicle['id']); vehicleYear = ''; vehicleMake = ''; vehicleModel = ''; continueVehicle()"
                        >
                            {{ $vehicle['label'] }}
                        </button>
                    @endforeach
                    <button
                        type="button"
                        class="public-book-wizard__choice public-book-wizard__choice--muted"
                        :class="{ 'is-selected': vehicleSelection === 'other' }"
                        @click="vehicleSelection = 'other'"
                    >
                        + Another Vehicle
                    </button>
                </div>
            @endif

            <div
                class="public-book-wizard__vehicle-entry"
                x-show="needsVehicleEntry()"
                @if (($contactPrefill['vehicles'] ?? []) !== []) x-cloak @endif
            >
                <p class="public-book-wizard__hint">Year / Make / Model</p>
                <div class="public-book-wizard__vehicle-grid">
                    <label class="sr-only" for="book_vehicle_year">Year</label>
                    <input
                        id="book_vehicle_year"
                        type="number"
                        name="vehicle_year"
                        min="1900"
                        max="2100"
                        inputmode="numeric"
                        placeholder="Year"
                        class="public-book-wizard__input"
                        x-model="vehicleYear"
                    >
                    <label class="sr-only" for="book_vehicle_make">Make</label>
                    <input
                        id="book_vehicle_make"
                        type="text"
                        name="vehicle_make"
                        placeholder="Make"
                        autocomplete="organization"
                        class="public-book-wizard__input"
                        x-model="vehicleMake"
                    >
                    <label class="sr-only" for="book_vehicle_model">Model</label>
                    <input
                        id="book_vehicle_model"
                        type="text"
                        name="vehicle_model"
                        placeholder="Model"
                        class="public-book-wizard__input"
                        x-model="vehicleModel"
                    >
                </div>
            </div>

            <div class="public-book-wizard__actions">
                <button type="button" class="public-book-wizard__primary" @click="continueVehicle()">
                    Continue
                </button>
                <button type="button" class="public-book-wizard__text-btn" @click="skipVehicle()">
                    Skip for now
                </button>
            </div>
        </section>
        @endunless

        {{-- Step: when --}}
        <section class="public-book-wizard__step" x-show="step === 'when'" x-cloak>
            <template x-if="whenPhase === 'period'">
                <div>
                    <p class="public-book-wizard__kicker">{{ $recognizedSchedule ? 'Next up' : 'Almost there.' }}</p>
                    <h2 class="public-book-wizard__question">When works best?</h2>
                    <div class="public-book-wizard__choices">
                        <button type="button" class="public-book-wizard__choice" :class="{ 'is-selected': timeChoice === 'morning' }" @click="selectTimeChoice('morning')">Morning</button>
                        <button type="button" class="public-book-wizard__choice" :class="{ 'is-selected': timeChoice === 'afternoon' }" @click="selectTimeChoice('afternoon')">Afternoon</button>
                        <button type="button" class="public-book-wizard__choice" :class="{ 'is-selected': timeChoice === 'first_available' }" @click="selectTimeChoice('first_available')">First Available</button>
                        <button type="button" class="public-book-wizard__choice" :class="{ 'is-selected': timeChoice === 'any' }" @click="selectTimeChoice('any')">No Preference</button>
                    </div>
                </div>
            </template>

            <template x-if="whenPhase === 'day'">
                <div>
                    <p class="public-book-wizard__kicker">Got it.</p>
                    <h2 class="public-book-wizard__question">Which day?</h2>
                    <div class="public-book-wizard__choices">
                        <template x-for="day in dates" :key="day.date">
                            <button
                                type="button"
                                class="public-book-wizard__choice"
                                :class="{ 'is-selected': preferredDate === day.date }"
                                @click="selectDay(day.date)"
                                x-text="day.label"
                            ></button>
                        </template>
                    </div>
                    <p class="public-book-wizard__hint" x-show="dates.length === 0">No requestable days are available right now.</p>
                </div>
            </template>
        </section>

        @unless ($recognizedSchedule)
        {{-- Step: details (guest path only) --}}
        <section class="public-book-wizard__step" x-show="step === 'details'" x-cloak>
            <p class="public-book-wizard__kicker">Optional.</p>
            <h2 class="public-book-wizard__question">Tell us a little more</h2>
            <label class="sr-only" for="book_concern_details">Tell us a little more</label>
            <textarea
                id="book_concern_details"
                class="public-book-wizard__textarea"
                rows="4"
                maxlength="5000"
                placeholder="Anything that helps — noises, warning lights, when it started…"
                x-model="concernDetails"
            ></textarea>
            <div class="public-book-wizard__actions">
                <button type="button" class="public-book-wizard__primary" @click="continueDetails()">
                    Continue
                </button>
            </div>
        </section>
        @endunless

        {{-- Step: contact --}}
        <section class="public-book-wizard__step" x-show="step === 'contact'" x-cloak>
            <p class="public-book-wizard__kicker">{{ $recognizedSchedule ? 'Confirm' : 'Last step.' }}</p>
            <h2 class="public-book-wizard__question">{{ $recognizedSchedule ? 'Still the best way to reach you?' : 'How can we reach you?' }}</h2>

            <div class="public-book-wizard__fields">
                <div>
                    <label for="book_full_name" class="public-book-wizard__label">Name</label>
                    <input
                        id="book_full_name"
                        type="text"
                        class="public-book-wizard__input"
                        autocomplete="name"
                        :required="step === 'contact'"
                        x-model="fullName"
                        @input="syncNameParts()"
                        placeholder="Your name"
                    >
                </div>

                <div
                    @if ($phoneVerificationRequired)
                        x-data="publicLeadPhoneVerify({
                            sendUrl: @js(route('public.leads.verify.send')),
                            checkUrl: @js(route('public.leads.verify.check')),
                            csrf: @js(csrf_token()),
                            initialPhone: @js($leadPrefillPhone),
                            trustedPhone: @js($trustedAccountPhone),
                        })"
                        x-effect="$parent.phone = phone; $parent.phoneVerified = verified; $parent.formatPhone()"
                    @endif
                >
                    <label for="phone" class="public-book-wizard__label">Phone</label>
                    @if ($gateVerifiedDisplay !== '')
                        <p class="public-book-wizard__hint public-book-wizard__hint--ok">Verified by text</p>
                    @endif
                    <div class="public-book-wizard__phone-row">
                        <input
                            type="tel"
                            id="phone"
                            name="phone"
                            inputmode="tel"
                            autocomplete="tel"
                            maxlength="14"
                            :required="step === 'contact'"
                            class="public-book-wizard__input"
                            @if ($gateVerifiedDisplay !== '') readonly @endif
                            @if ($phoneVerificationRequired)
                                x-model="phone"
                                @input="formatPhone()"
                            @else
                                x-model="phone"
                                @input="formatPhone()"
                            @endif
                            placeholder="(719) 555-0100"
                        >
                        @if ($phoneVerificationRequired)
                            <button
                                type="button"
                                class="public-book-wizard__secondary"
                                :disabled="! canSendCode() || sending || verified"
                                @click="sendCode()"
                                x-text="sending ? 'Sending…' : (codeSent ? 'Resend' : 'Send code')"
                                x-show="! verified || codeSent"
                            ></button>
                        @endif
                    </div>
                    @if ($phoneVerificationRequired)
                        <div class="public-book-wizard__verify" x-show="codeSent && ! verified" x-cloak>
                            <label for="phone_verify_code" class="public-book-wizard__label">Verification code</label>
                            <div class="public-book-wizard__phone-row">
                                <input
                                    type="text"
                                    id="phone_verify_code"
                                    inputmode="numeric"
                                    autocomplete="one-time-code"
                                    maxlength="10"
                                    class="public-book-wizard__input"
                                    x-model="code"
                                    placeholder="6-digit code"
                                >
                                <button
                                    type="button"
                                    class="public-book-wizard__secondary"
                                    :disabled="! canCheckCode() || checking"
                                    @click="checkCode()"
                                    x-text="checking ? 'Checking…' : 'Verify'"
                                ></button>
                            </div>
                        </div>
                        <p class="public-book-wizard__hint public-book-wizard__hint--ok" x-show="verified" x-cloak>
                            Phone verified.
                        </p>
                        <p class="public-book-wizard__hint public-book-wizard__hint--error" x-show="error" x-text="error" x-cloak></p>
                    @endif
                </div>

                <div>
                    <label for="book_email" class="public-book-wizard__label">Email <span class="public-book-wizard__optional">optional</span></label>
                    <input
                        id="book_email"
                        type="email"
                        name="email"
                        class="public-book-wizard__input"
                        autocomplete="email"
                        x-model="email"
                        placeholder="you@email.com"
                    >
                </div>
            </div>

            <div class="public-book-wizard__actions">
                <button
                    type="submit"
                    class="public-book-wizard__primary"
                    :disabled="submitting || (phoneVerificationRequired && ! phoneVerified)"
                    x-text="submitting ? 'Sending…' : @js(PublicLeadFormCopy::BOOK_SUBMIT_LABEL)"
                ></button>
            </div>
        </section>
    </form>
</div>
