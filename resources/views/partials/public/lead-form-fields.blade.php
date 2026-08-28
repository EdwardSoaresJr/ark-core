@php
    use App\Ark\Operations\Leads\LeadContactPreference;
    use App\Ark\Operations\Leads\Public\PublicLeadFormContactPrefill;
    use App\Ark\Operations\Leads\Public\PublicLeadFormPlaceholders;

    $contactPrefill = app(PublicLeadFormContactPrefill::class)->forCurrentCustomer();
    $placeholders = PublicLeadFormPlaceholders::random();
    $concernValue = old('concern', $prefilledConcern ?? request('concern', ''));
    $leadPrefillFirstName = old('first_name', $contactPrefill['first_name']);
    $leadPrefillLastName = old('last_name', $contactPrefill['last_name']);
    $leadPrefillPhone = old('phone', $contactPrefill['phone']);
    $leadPrefillEmail = old('email', $contactPrefill['email']);
    $contactPreference = old('contact_preference', $contactPrefill['contact_preference']);
    $autofocusConcern = $autofocusConcern ?? filled($concernValue) || ! filled($leadPrefillFirstName);
    $submitLabel = $submitLabel ?? \App\Ark\Operations\Leads\Public\PublicLeadFormCopy::SUBMIT_LABEL;
    $surfaceContext = $surfaceContext ?? null;
    $appointmentRequest = (bool) ($appointmentRequest ?? false);
    $phoneVerificationRequired = (bool) ($phoneVerificationRequired ?? false);
    $trustedAccountPhone = $contactPrefill['skips_phone_verification'] ? $contactPrefill['phone'] : '';
    $contactSectionTitle = $contactPrefill['signed_in']
        ? 'Confirm how we should reach you'
        : 'Tell us a little about yourself';
    $stagedContactTitle = $contactPrefill['signed_in']
        ? 'Confirm how we should reach you'
        : 'How should we reach you?';
    $marketingBook = (bool) ($marketingBook ?? false);
    $concernLabel = $appointmentRequest ? 'What’s happening with the vehicle?' : 'What\'s going on?';
    $concernPlaceholder = $appointmentRequest
        ? 'Example: Grinding when I brake. Check engine light came on yesterday. Want to bring it in this week.'
        : 'Describe what\'s happening...';
    $concernHint = $appointmentRequest
        ? 'A few sentences are enough. After we confirm, you can text photos of warning lights or leaks.'
        : 'A few details help. Photos of warning lights or leaks are useful too.';
    $preferredAvailability = old('preferred_availability', '');
@endphp

<form
    method="POST"
    action="{{ route('public.leads.store') }}"
    @class([
        'public-lead-form',
        'public-lead-form--staged' => $staged ?? false,
        'public-lead-form--book' => $marketingBook,
        'mt-5 space-y-4' => ! ($staged ?? false) && ! $marketingBook,
        'public-book-form__fields' => $marketingBook,
    ])
    @if ($staged ?? false)
        x-data="publicLeadStagedForm({ initialStep: @js(old('first_name') || old('last_name') || old('phone') || $errors->any() ? 2 : 1) })"
    @endif
    @if ($surfaceContext)
        data-public-surface-page="{{ $surfaceContext->page }}"
        data-public-surface-variant="{{ $surfaceContext->variant }}"
        data-public-surface-placement="{{ $surfaceContext->placement }}"
    @endif
    @if ($phoneVerificationRequired)
        x-data="publicLeadPhoneVerify({
            sendUrl: @js(route('public.leads.verify.send')),
            checkUrl: @js(route('public.leads.verify.check')),
            csrf: @js(csrf_token()),
            initialPhone: @js($leadPrefillPhone),
            trustedPhone: @js($trustedAccountPhone),
        })"
    @endif
>
    @csrf
    <input type="hidden" name="source" value="website">
    <input type="hidden" name="form_rendered_at" value="{{ $formRenderedAt ?? now()->timestamp }}">
    @if ($surfaceContext)
        <input type="hidden" name="public_surface_page" value="{{ $surfaceContext->page }}">
        <input type="hidden" name="public_surface_variant" value="{{ $surfaceContext->variant }}">
        <input type="hidden" name="public_surface_placement" value="{{ $surfaceContext->placement }}">
    @endif

    <div class="hidden" aria-hidden="true">
        <label for="company_website">Company website</label>
        <input type="text" name="company_website" id="company_website" tabindex="-1" autocomplete="off">
    </div>

    <div @if ($staged ?? false) x-show="step === 1" @endif @class(['public-book-form__section' => $marketingBook])>
        @if ($marketingBook)
            <h2 class="public-book-form__section-title">What’s going on?</h2>
        @endif
        <label for="concern" class="public-lead-form__label">{{ $concernLabel }}</label>
        <textarea
            id="concern"
            name="concern"
            rows="{{ ($staged ?? false) ? 4 : ($marketingBook ? 4 : 5) }}"
            required
            @if ($autofocusConcern)
                autofocus
            @endif
            @if ($staged ?? false)
                x-model="concern"
            @endif
            class="public-lead-form__textarea"
            placeholder="{{ $concernPlaceholder }}"
        >{{ $concernValue }}</textarea>
        <p class="public-lead-form__hint">{{ $concernHint }}</p>

        @if ($appointmentRequest)
            @php
                $requestAvailability = $requestAvailability ?? \App\Ark\Operations\Leads\Public\PublicAppointmentRequest::availabilityProjection();
                $preferredDate = old('preferred_date', '');
                $preferredPeriod = old('preferred_period', \App\Ark\Operations\Appointments\AppointmentRequestAvailability::PERIOD_ANY);
            @endphp
            <div @class(['mt-4 space-y-4', 'public-book-form__section public-book-form__section--nested' => $marketingBook])>
                @if ($marketingBook)
                    <h2 class="public-book-form__section-title">When would you like to bring it in?</h2>
                @endif
                <fieldset>
                    <legend class="public-lead-form__label">Preferred day</legend>
                    <div @class(['mt-2 flex flex-col gap-2', 'public-book-form__options' => $marketingBook])>
                        @forelse ($requestAvailability['dates'] as $dayOption)
                            <label @class([
                                'flex cursor-pointer items-start gap-2 rounded-lg border border-slate-200 bg-white px-3 py-2.5 text-sm text-slate-900 has-[:checked]:border-[#0099cc] has-[:checked]:bg-sky-50',
                                'public-book-form__option' => $marketingBook,
                            ])>
                                <input
                                    type="radio"
                                    name="preferred_date"
                                    value="{{ $dayOption['date'] }}"
                                    class="mt-0.5 border-slate-300"
                                    required
                                    @checked($preferredDate === $dayOption['date'])
                                >
                                <span>{{ $dayOption['label'] }}</span>
                            </label>
                        @empty
                            <p class="text-sm text-slate-600">No requestable days are available right now.</p>
                        @endforelse
                    </div>
                </fieldset>

                <fieldset>
                    <legend class="public-lead-form__label">Preferred time</legend>
                    <div @class(['mt-2 flex flex-col gap-2', 'public-book-form__options public-book-form__options--period' => $marketingBook])>
                        @foreach ($requestAvailability['periods'] as $periodOption)
                            <label @class([
                                'flex cursor-pointer items-start gap-2 rounded-lg border border-slate-200 bg-white px-3 py-2.5 text-sm text-slate-900 has-[:checked]:border-[#0099cc] has-[:checked]:bg-sky-50',
                                'public-book-form__option' => $marketingBook,
                            ])>
                                <input
                                    type="radio"
                                    name="preferred_period"
                                    value="{{ $periodOption['value'] }}"
                                    class="mt-0.5 border-slate-300"
                                    required
                                    @checked($preferredPeriod === $periodOption['value'])
                                >
                                <span>{{ $periodOption['label'] }}</span>
                            </label>
                        @endforeach
                    </div>
                </fieldset>

                <p class="public-lead-form__hint">This is your preference. We’ll confirm the exact time with you — nothing is reserved yet.</p>
            </div>
        @endif

        @if ($staged ?? false)
            <button type="button" class="public-btn-primary mt-4 w-full sm:w-auto" @click="continueToContact()">
                Continue
            </button>
        @endif
    </div>

    <div @class([
        'public-form-section',
        'public-book-form__section' => $marketingBook,
        'space-y-4' => ! ($staged ?? false),
        'mt-0' => $staged ?? false,
    ]) @if ($staged ?? false) x-show="step === 2" x-cloak @endif>
        @if ($staged ?? false)
            <div class="public-lead-form__step-header">
                <p class="text-sm font-semibold text-slate-900">{{ $stagedContactTitle }}</p>
                <button type="button" class="text-sm font-semibold text-[#0099cc] hover:text-[#0088b8]" @click="step = 1">
                    ← Edit concern
                </button>
            </div>
        @elseif ($marketingBook)
            <h2 class="public-book-form__section-title">Your information</h2>
        @else
            <p class="text-sm font-semibold text-slate-900">{{ $contactSectionTitle }}</p>
        @endif

        <div class="space-y-4" x-data="{
            preference: @js($contactPreference),
            vehicleChoice: @js(old('vehicle_selection', $contactPrefill['default_vehicle_selection'])),
        }">
            <div class="grid gap-3 sm:grid-cols-2">
                <div>
                    <label for="first_name" class="block text-sm font-semibold text-slate-900">
                        First name <span class="text-rose-600" aria-hidden="true">*</span>
                    </label>
                    <input
                        type="text"
                        id="first_name"
                        name="first_name"
                        value="{{ $leadPrefillFirstName }}"
                        required
                        autocomplete="given-name"
                        class="mt-1.5 block w-full rounded-lg border border-slate-300 bg-white px-3 py-2.5 text-base shadow-sm transition focus:border-[#0099cc] focus:outline-none focus:ring-2 focus:ring-[#0099cc]/20"
                        placeholder="{{ $placeholders['first_name'] }}"
                    >
                </div>
                <div>
                    <label for="last_name" class="block text-sm font-semibold text-slate-900">
                        Last name <span class="text-rose-600" aria-hidden="true">*</span>
                    </label>
                    <input
                        type="text"
                        id="last_name"
                        name="last_name"
                        value="{{ $leadPrefillLastName }}"
                        required
                        autocomplete="family-name"
                        class="mt-1.5 block w-full rounded-lg border border-slate-300 bg-white px-3 py-2.5 text-base shadow-sm transition focus:border-[#0099cc] focus:outline-none focus:ring-2 focus:ring-[#0099cc]/20"
                        placeholder="{{ $placeholders['last_name'] }}"
                    >
                </div>
            </div>
            <p class="text-xs text-slate-500">
                @if ($contactPrefill['signed_in'])
                    From your account — change anything that looks wrong.
                @else
                    So we know who we&apos;re helping.
                @endif
            </p>

            <div role="group" aria-labelledby="contact-preference-label">
                <p id="contact-preference-label" class="text-sm font-semibold text-slate-900">How should we reach you?</p>
                <div class="mt-2 flex flex-wrap gap-2" role="radiogroup" aria-label="How should we reach you?">
                    @foreach (LeadContactPreference::cases() as $option)
                        <label
                            class="inline-flex cursor-pointer items-center rounded-full border px-3.5 py-2 text-sm font-semibold transition-colors"
                            :class="preference === @js($option->value)
                                ? 'border-[#0099cc] bg-[#0099cc]/10 text-[#007aa3] ring-1 ring-[#0099cc]/25'
                                : 'border-slate-200 bg-white text-slate-700 hover:border-slate-300'"
                        >
                            <input
                                type="radio"
                                name="contact_preference"
                                value="{{ $option->value }}"
                                class="sr-only"
                                x-model="preference"
                                @checked($contactPreference === $option->value)
                            >
                            {{ $option->formLabel() }}
                        </label>
                    @endforeach
                </div>
            </div>

            <div
                @unless ($phoneVerificationRequired)
                    x-data="publicLeadPhoneField(@js($leadPrefillPhone))"
                @endunless
            >
                <label for="phone" class="block text-sm font-semibold text-slate-900">
                    Phone number <span class="text-rose-600" aria-hidden="true">*</span>
                </label>
                <div class="mt-1.5 flex gap-2">
                    <input
                        type="tel"
                        id="phone"
                        name="phone"
                        inputmode="tel"
                        autocomplete="tel"
                        maxlength="14"
                        required
                        x-model="phone"
                        @input="formatPhone()"
                        class="block min-w-0 flex-1 rounded-lg border border-slate-300 bg-white px-3 py-2.5 text-base shadow-sm transition focus:border-[#0099cc] focus:outline-none focus:ring-2 focus:ring-[#0099cc]/20"
                        placeholder="{{ $placeholders['phone'] }}"
                    >
                    @if ($phoneVerificationRequired)
                        <button
                            type="button"
                            class="shrink-0 rounded-md border border-slate-300 bg-white px-3 py-2 text-sm font-semibold text-slate-800 hover:bg-slate-50 disabled:cursor-not-allowed disabled:opacity-50"
                            :disabled="! canSendCode() || sending || verified"
                            @click="sendCode()"
                            x-text="sending ? 'Sending…' : (codeSent ? 'Resend' : 'Send code')"
                            x-show="! verified || codeSent"
                        ></button>
                    @endif
                </div>
                @if ($phoneVerificationRequired)
                    <div class="mt-3 space-y-2" x-show="codeSent && ! verified" x-cloak>
                        <label for="phone_verify_code" class="block text-sm font-medium text-slate-800">Verification code</label>
                        <div class="flex gap-2">
                            <input
                                type="text"
                                id="phone_verify_code"
                                inputmode="numeric"
                                autocomplete="one-time-code"
                                maxlength="10"
                                x-model="code"
                                class="block min-w-0 flex-1 rounded-md border border-slate-300 px-3 py-2.5 text-base tracking-widest shadow-sm focus:border-sky-500 focus:outline-none focus:ring-1 focus:ring-sky-500"
                                placeholder="6-digit code"
                            >
                            <button
                                type="button"
                                class="shrink-0 rounded-md bg-slate-800 px-3 py-2 text-sm font-semibold text-white hover:bg-slate-900 disabled:cursor-not-allowed disabled:opacity-50"
                                :disabled="! canCheckCode() || checking"
                                @click="checkCode()"
                                x-text="checking ? 'Checking…' : 'Verify'"
                            ></button>
                        </div>
                    </div>
                    <p class="mt-2 text-xs font-semibold text-emerald-800" x-show="verified" x-cloak>
                        <span x-show="isTrustedPhone()">Using the number on your account.</span>
                        <span x-show="! isTrustedPhone()">Phone verified — you can send your request.</span>
                    </p>
                    <p class="mt-2 text-xs text-rose-700" x-show="error" x-text="error" x-cloak></p>
                    <p class="mt-1 text-xs text-slate-500" x-show="! verified">We text a short code to confirm this number before your request reaches the shop.</p>
                @else
                    <p
                        class="mt-1 text-xs text-slate-500"
                        x-text="({
                            text: 'The number we\'ll text you at.',
                            call: 'The number we\'ll call you at.',
                            email: 'So we can reach you if email isn\'t enough.',
                        })[preference]"
                    ></p>
                @endif
            </div>

            <div>
                <label for="email" class="block text-sm font-medium text-slate-700">
                    Email
                    <span class="font-normal text-slate-500" x-show="preference !== 'email'">(optional)</span>
                    <span class="text-rose-600" x-show="preference === 'email'" x-cloak aria-hidden="true">*</span>
                </label>
                <input
                    type="email"
                    id="email"
                    name="email"
                    value="{{ $leadPrefillEmail }}"
                    autocomplete="email"
                    :required="preference === 'email'"
                    class="mt-1.5 block w-full rounded-lg border border-slate-300 bg-white px-3 py-2.5 text-base shadow-sm transition focus:border-[#0099cc] focus:outline-none focus:ring-2 focus:ring-[#0099cc]/20"
                    placeholder="{{ $placeholders['email'] }}"
                >
                <p class="mt-1 text-xs text-slate-500" x-show="preference === 'email'" x-cloak>We&apos;ll reply to this address first.</p>
            </div>

            <div @class(['public-book-form__vehicle' => $marketingBook])>
                @if ($marketingBook)
                    <h3 class="public-book-form__section-title public-book-form__section-title--sub">Your vehicle</h3>
                @endif
                <p class="text-sm font-medium text-slate-700">
                    Vehicle <span class="font-normal text-slate-500">(optional)</span>
                </p>
                @if ($contactPrefill['vehicles'] !== [])
                    <p class="mt-1 text-xs text-slate-500">Pick a vehicle on file, or enter a different one.</p>
                    <div class="mt-2 flex flex-col gap-2" role="radiogroup" aria-label="Which vehicle?">
                        @foreach ($contactPrefill['vehicles'] as $vehicle)
                            <label
                                class="flex cursor-pointer items-center rounded-lg border px-3.5 py-2.5 text-sm font-semibold transition-colors"
                                :class="vehicleChoice === @js((string) $vehicle['id'])
                                    ? 'border-[#0099cc] bg-[#0099cc]/10 text-[#007aa3] ring-1 ring-[#0099cc]/25'
                                    : 'border-slate-200 bg-white text-slate-800 hover:border-slate-300'"
                            >
                                <input
                                    type="radio"
                                    name="vehicle_selection"
                                    value="{{ $vehicle['id'] }}"
                                    class="sr-only"
                                    x-model="vehicleChoice"
                                >
                                {{ $vehicle['label'] }}
                            </label>
                        @endforeach
                        <label
                            class="flex cursor-pointer items-center rounded-lg border px-3.5 py-2.5 text-sm font-semibold transition-colors"
                            :class="vehicleChoice === 'other'
                                ? 'border-[#0099cc] bg-[#0099cc]/10 text-[#007aa3] ring-1 ring-[#0099cc]/25'
                                : 'border-slate-200 bg-white text-slate-800 hover:border-slate-300'"
                        >
                            <input
                                type="radio"
                                name="vehicle_selection"
                                value="other"
                                class="sr-only"
                                x-model="vehicleChoice"
                            >
                            A different vehicle
                        </label>
                        <label
                            class="flex cursor-pointer items-center rounded-lg border px-3.5 py-2.5 text-sm font-semibold transition-colors"
                            :class="vehicleChoice === ''
                                ? 'border-[#0099cc] bg-[#0099cc]/10 text-[#007aa3] ring-1 ring-[#0099cc]/25'
                                : 'border-slate-200 bg-white text-slate-800 hover:border-slate-300'"
                        >
                            <input
                                type="radio"
                                name="vehicle_selection"
                                value=""
                                class="sr-only"
                                x-model="vehicleChoice"
                            >
                            Skip for now
                        </label>
                    </div>
                    <div class="mt-3 grid gap-3 sm:grid-cols-3" x-show="vehicleChoice === 'other'" x-cloak>
                        <div>
                            <label for="vehicle_year" class="sr-only">Year</label>
                            <input type="number" id="vehicle_year" name="vehicle_year" value="{{ old('vehicle_year') }}" min="1900" max="2100" placeholder="Year" class="block w-full rounded-lg border border-slate-300 bg-white px-3 py-2.5 text-base shadow-sm transition focus:border-[#0099cc] focus:outline-none focus:ring-2 focus:ring-[#0099cc]/20">
                        </div>
                        <div>
                            <label for="vehicle_make" class="sr-only">Make</label>
                            <input type="text" id="vehicle_make" name="vehicle_make" value="{{ old('vehicle_make') }}" placeholder="Make" class="block w-full rounded-lg border border-slate-300 bg-white px-3 py-2.5 text-base shadow-sm transition focus:border-[#0099cc] focus:outline-none focus:ring-2 focus:ring-[#0099cc]/20">
                        </div>
                        <div>
                            <label for="vehicle_model" class="sr-only">Model</label>
                            <input type="text" id="vehicle_model" name="vehicle_model" value="{{ old('vehicle_model') }}" placeholder="Model" class="block w-full rounded-lg border border-slate-300 bg-white px-3 py-2.5 text-base shadow-sm transition focus:border-[#0099cc] focus:outline-none focus:ring-2 focus:ring-[#0099cc]/20">
                        </div>
                    </div>
                @else
                    <p class="mt-1 text-xs text-slate-500">Skip if it&apos;s not your car or you&apos;re not sure of the year.</p>
                    <div class="mt-2 grid gap-3 sm:grid-cols-3">
                        <div>
                            <label for="vehicle_year" class="sr-only">Year</label>
                            <input type="number" id="vehicle_year" name="vehicle_year" value="{{ old('vehicle_year') }}" min="1900" max="2100" placeholder="Year" class="block w-full rounded-lg border border-slate-300 bg-white px-3 py-2.5 text-base shadow-sm transition focus:border-[#0099cc] focus:outline-none focus:ring-2 focus:ring-[#0099cc]/20">
                        </div>
                        <div>
                            <label for="vehicle_make" class="sr-only">Make</label>
                            <input type="text" id="vehicle_make" name="vehicle_make" value="{{ old('vehicle_make') }}" placeholder="Make" class="block w-full rounded-lg border border-slate-300 bg-white px-3 py-2.5 text-base shadow-sm transition focus:border-[#0099cc] focus:outline-none focus:ring-2 focus:ring-[#0099cc]/20">
                        </div>
                        <div>
                            <label for="vehicle_model" class="sr-only">Model</label>
                            <input type="text" id="vehicle_model" name="vehicle_model" value="{{ old('vehicle_model') }}" placeholder="Model" class="block w-full rounded-lg border border-slate-300 bg-white px-3 py-2.5 text-base shadow-sm transition focus:border-[#0099cc] focus:outline-none focus:ring-2 focus:ring-[#0099cc]/20">
                        </div>
                    </div>
                @endif
            </div>
        </div>
    </div>

    <button
        type="submit"
        @class([
            'mt-5',
            'public-cta public-cta--primary public-cta--xl public-book-form__submit' => $marketingBook,
            'public-btn-primary' => ! $marketingBook,
        ])
        @if ($staged ?? false)
            x-show="step === 2"
            x-cloak
        @endif
        @if ($phoneVerificationRequired)
            :disabled="! verified"
        @endif
    >
        {{ $submitLabel }}
    </button>
</form>
