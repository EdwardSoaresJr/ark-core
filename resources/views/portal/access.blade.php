@php
    $shop = \App\Ark\Operations\Settings\ShopSettings::current();
    $shopName = $shop->displayName();
    $bookContinuation = (bool) ($bookContinuation ?? false);
@endphp

<x-portal.app>
    <x-customer.split-page variant="public">
        <x-slot:primary>
            <div class="public-hero">
                @if ($bookContinuation)
                    <p class="public-hero__eyebrow">{{ $shopName }}</p>
                    <h1 class="public-hero__title">Continue with your phone</h1>

                    <div class="public-hero__lede">
                        <p class="font-semibold text-slate-900">
                            We’ll look up your vehicle next.
                        </p>
                        <p>
                            Enter the mobile number or email {{ $shopName }} has for you.
                            We’ll send a 6-digit code. No password needed.
                        </p>
                    </div>
                @else
                    <p class="public-hero__eyebrow">My Account</p>
                    <h1 class="public-hero__title">Sign in</h1>

                    <div class="public-hero__lede">
                        <p class="font-semibold text-slate-900">
                            See your estimates, invoices, and service history.
                        </p>
                        <p>
                            Enter the email or mobile number {{ $shopName }} has for you.
                            We’ll send a 6-digit code by text or email. No password needed.
                        </p>
                    </div>
                @endif
            </div>
        </x-slot:primary>

        <x-slot:rail>
            @include('partials.customer.sign-in-form-fields', [
                'formHeading' => $bookContinuation ? 'Confirm it’s you' : 'Sign in',
                'formSubheading' => $bookContinuation
                    ? 'Mobile number or email on file with the shop.'
                    : 'Use the email or mobile number on your estimate or invoice.',
                'submitLabel' => 'Send code',
            ])

            <div class="public-panel">
                @if ($bookContinuation)
                    <p class="text-sm font-semibold text-slate-900">Prefer text?</p>
                    <p class="mt-2 text-sm leading-relaxed text-slate-600">
                        Enter the mobile number {{ $shopName }} has for you above — we’ll text a 6-digit code.
                    </p>
                @else
                    <p class="text-sm font-semibold text-slate-900">Don’t have a code yet?</p>
                    <p class="mt-2 text-sm leading-relaxed text-slate-600">
                        Call {{ $shopName }} if you need help finding the right email or number on file.
                    </p>
                @endif
            </div>
        </x-slot:rail>
    </x-customer.split-page>
</x-portal.app>
