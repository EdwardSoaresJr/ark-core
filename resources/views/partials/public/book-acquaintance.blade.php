@php
    $closeUrl = \App\Ark\Customer\CustomerSurfaceUrls::publicHome();
    $identityGateReady = (bool) ($identityGateReady ?? false);
    $shopPhoneDigits = preg_replace('/\D+/', '', (string) ($shop->phone ?? '')) ?: '7194136227';
    $concernQuery = trim((string) request('concern', ''));
@endphp

<div
    class="public-book-wizard public-book-identity"
    @if ($identityGateReady)
        x-data="publicBookIdentity({
            sendUrl: @js(route('public.leads.verify.send')),
            checkUrl: @js(route('public.leads.verify.check')),
            completeUrl: @js(route('public.book.identity')),
            emailSendUrl: @js(route('public.book.identity.email.send')),
            emailCheckUrl: @js(route('public.book.identity.email.check')),
            csrf: @js(csrf_token()),
            concern: @js($concernQuery),
        })"
    @endif
>
    <div class="public-book-wizard__chrome public-book-wizard__chrome--identity">
        <div class="public-book-wizard__toolbar">
            <span class="public-book-wizard__toolbar-spacer" aria-hidden="true"></span>
            <a
                href="{{ $closeUrl }}"
                class="public-book-wizard__close"
                data-public-book-close
                aria-label="Close"
            >
                <span aria-hidden="true">×</span>
            </a>
        </div>
    </div>

    <p class="public-book-wizard__kicker">{{ $shopName }}</p>

    @if (! $identityGateReady)
        @php
            $shopPhoneDisplay = \App\Ark\Operations\PhoneNumber::display($shop->phone ?? null) ?: '(719) 413-6227';
        @endphp
        <h1 class="public-book-wizard__question">Online booking is temporarily unavailable.</h1>
        <p class="public-book-wizard__lede">
            Please call or text us at {{ $shopPhoneDisplay }} and we’ll be happy to get you scheduled.
        </p>
        <div class="public-book-wizard__actions public-book-wizard__actions--row">
            <a href="tel:{{ $shopPhoneDigits }}" class="public-book-wizard__primary">Call {{ $shopPhoneDisplay }}</a>
            <a href="sms:{{ $shopPhoneDigits }}" class="public-book-wizard__secondary-link">Text us</a>
        </div>
    @else
        <h1 class="public-book-wizard__question">Let’s get your vehicle taken care of</h1>

        <template x-if="channel === 'sms'">
            <div class="public-book-identity__panel">
                <p class="public-book-wizard__lede">
                    Enter your mobile number. We’ll text a code to confirm it’s you — then we’ll look up your vehicle if we know you.
                </p>

                @if ($errors->has('phone'))
                    <div class="public-book-wizard__errors" role="alert">
                        <ul>
                            @foreach ($errors->get('phone') as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                <div class="public-book-wizard__fields">
                    <div>
                        <label for="book_identity_phone" class="public-book-wizard__label">Mobile number</label>
                        <div class="public-book-wizard__phone-row">
                            <input
                                type="tel"
                                id="book_identity_phone"
                                inputmode="tel"
                                autocomplete="tel"
                                maxlength="14"
                                class="public-book-wizard__input"
                                x-model="phone"
                                @input="formatPhone()"
                                :disabled="verified"
                                placeholder="(719) 555-0100"
                            >
                            <button
                                type="button"
                                class="public-book-wizard__secondary public-book-wizard__send"
                                :class="{ 'is-ready': canSendCode() && ! sending && ! verified }"
                                :disabled="! canSendCode() || sending || verified"
                                @click="sendCode()"
                                x-text="sending ? 'Sending…' : (codeSent ? 'Resend' : 'Send code')"
                                x-show="! verified"
                            ></button>
                        </div>
                    </div>

                    <div class="public-book-wizard__verify" x-show="codeSent && ! verified" x-cloak>
                        <label for="book_identity_code" class="public-book-wizard__label">6-digit code</label>
                        <div class="public-book-wizard__phone-row">
                            <input
                                type="text"
                                id="book_identity_code"
                                inputmode="numeric"
                                autocomplete="one-time-code"
                                maxlength="6"
                                class="public-book-wizard__input"
                                x-model="code"
                                placeholder="000000"
                            >
                            <button
                                type="button"
                                class="public-book-wizard__secondary public-book-wizard__send"
                                :class="{ 'is-ready': canCheckCode() && ! checking }"
                                :disabled="! canCheckCode() || checking"
                                @click="checkCode()"
                                x-text="checking ? 'Checking…' : 'Verify'"
                            ></button>
                        </div>
                    </div>

                    <p class="public-book-wizard__hint public-book-wizard__hint--ok" x-show="verified" x-cloak>
                        Phone verified. Continuing…
                    </p>
                    <p class="public-book-wizard__hint public-book-wizard__hint--error" x-show="error" x-text="error" x-cloak></p>
                </div>

                <form method="POST" action="{{ route('public.book.identity') }}" x-ref="completeForm" class="hidden" aria-hidden="true">
                    @csrf
                    <input type="hidden" name="phone" :value="phone">
                    @if ($concernQuery !== '')
                        <input type="hidden" name="concern" value="{{ $concernQuery }}">
                    @endif
                </form>

                <p class="public-book-identity__switch">
                    <button type="button" class="public-book-wizard__text-btn" @click="useEmailInstead()">
                        Use email instead
                    </button>
                </p>
            </div>
        </template>

        <template x-if="channel === 'email'">
            <div class="public-book-identity__panel">
                <p class="public-book-wizard__lede">
                    Enter your email. We’ll send a code to confirm it’s you — then we’ll look up your vehicle if we know you.
                </p>

                <div class="public-book-wizard__fields">
                    <div>
                        <label for="book_identity_email" class="public-book-wizard__label">Email</label>
                        <div class="public-book-wizard__phone-row">
                            <input
                                type="email"
                                id="book_identity_email"
                                inputmode="email"
                                autocomplete="email"
                                class="public-book-wizard__input"
                                x-model="email"
                                :disabled="verified"
                                placeholder="you@email.com"
                            >
                            <button
                                type="button"
                                class="public-book-wizard__secondary public-book-wizard__send"
                                :class="{ 'is-ready': canSendEmailCode() && ! sending && ! verified }"
                                :disabled="! canSendEmailCode() || sending || verified"
                                @click="sendEmailCode()"
                                x-text="sending ? 'Sending…' : (codeSent ? 'Resend' : 'Send code')"
                                x-show="! verified"
                            ></button>
                        </div>
                    </div>

                    <div class="public-book-wizard__verify" x-show="codeSent && ! verified" x-cloak>
                        <label for="book_identity_email_code" class="public-book-wizard__label">6-digit code</label>
                        <div class="public-book-wizard__phone-row">
                            <input
                                type="text"
                                id="book_identity_email_code"
                                inputmode="numeric"
                                autocomplete="one-time-code"
                                maxlength="6"
                                class="public-book-wizard__input"
                                x-model="code"
                                placeholder="000000"
                            >
                            <button
                                type="button"
                                class="public-book-wizard__secondary public-book-wizard__send"
                                :class="{ 'is-ready': canCheckEmailCode() && ! checking }"
                                :disabled="! canCheckEmailCode() || checking"
                                @click="checkEmailCode()"
                                x-text="checking ? 'Checking…' : 'Verify'"
                            ></button>
                        </div>
                    </div>

                    <p class="public-book-wizard__hint" x-show="notice" x-text="notice" x-cloak></p>
                    <p class="public-book-wizard__hint public-book-wizard__hint--ok" x-show="verified" x-cloak>
                        Email verified. Continuing…
                    </p>
                    <p class="public-book-wizard__hint public-book-wizard__hint--error" x-show="error" x-text="error" x-cloak></p>
                </div>

                <p class="public-book-identity__switch">
                    <button type="button" class="public-book-wizard__text-btn" @click="useSmsInstead()">
                        Use mobile number instead
                    </button>
                </p>
            </div>
        </template>
    @endif
</div>
