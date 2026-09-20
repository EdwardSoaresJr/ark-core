@php
    $bookContinuation = (bool) ($bookContinuation ?? false);
    $retryAccessUrl = $bookContinuation
        ? route('portal.access', ['return' => '/book'])
        : route('portal.access');
@endphp

<x-portal.app>
    <x-customer.split-page variant="public" :sticky-rail="false">
        <x-slot:primary>
            <div class="public-hero">
                @if ($bookContinuation)
                    <p class="public-hero__eyebrow">Almost there</p>
                    <h1 class="public-hero__title">Enter your 6-digit code</h1>
                @else
                    <p class="public-hero__eyebrow">My Account</p>
                    <h1 class="public-hero__title">Enter your 6-digit code</h1>
                @endif

                <div class="public-hero__lede">
                    @if (filled($destinationLabel ?? null))
                        <p>
                            We sent a code to <span class="font-semibold text-slate-900">{{ $destinationLabel }}</span>.
                            Enter it below to continue.
                        </p>
                    @else
                        <p>Enter the 6-digit code we sent to your email or phone.</p>
                    @endif
                    <p class="mt-2 text-sm text-slate-600">
                        Don’t see it? Check texts or spam, wait a minute, then try again — or use a different email or number.
                    </p>
                </div>
            </div>
        </x-slot:primary>

        <x-slot:rail>
            <section class="public-panel public-panel--accent">
                @if (session('portal_access_notice'))
                    <p class="rounded-lg border border-slate-200 bg-slate-50 px-4 py-3 text-sm leading-6 text-slate-700">
                        {{ session('portal_access_notice') }}
                    </p>
                @endif

                <form method="POST" action="{{ route('portal.access.verify.store') }}" @class(['space-y-4', 'mt-6' => session('portal_access_notice')])>
                    @csrf

                    <div>
                        <label for="code" class="block text-sm font-semibold text-slate-900">6-digit code</label>
                        <input
                            id="code"
                            name="code"
                            type="text"
                            inputmode="numeric"
                            autocomplete="one-time-code"
                            pattern="\d{6}"
                            maxlength="6"
                            required
                            class="mt-2 w-full rounded-md border border-slate-300 px-3 py-2.5 text-center text-lg tracking-[0.35em] text-slate-950 shadow-sm focus:border-[#0099cc] focus:outline-none focus:ring-2 focus:ring-sky-100"
                            placeholder="000000"
                        >
                        @error('code')
                            <p class="mt-2 text-sm font-semibold text-red-700">{{ $message }}</p>
                        @enderror
                    </div>

                    <button type="submit" class="public-btn-primary">
                        Continue
                    </button>
                </form>

                <p class="mt-4 text-center text-sm text-slate-600">
                    <a href="{{ $retryAccessUrl }}" class="font-semibold text-[#0099cc] no-underline hover:text-[#0088b8]">
                        Use a different email or number
                    </a>
                </p>
            </section>
        </x-slot:rail>
    </x-customer.split-page>
</x-portal.app>
