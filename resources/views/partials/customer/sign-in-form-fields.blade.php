@php
    $formHeading = $formHeading ?? 'Sign in';
    $formSubheading = $formSubheading ?? 'Email address or mobile number on file with the shop.';
    $submitLabel = $submitLabel ?? 'Send code';
    $contactPlaceholder = 'name@example.com or 555-555-0100';
@endphp

<section class="public-panel public-panel--accent">
    <h2 class="text-xl font-bold tracking-tight text-slate-950">{{ $formHeading }}</h2>
    <p class="mt-1.5 text-sm leading-relaxed text-slate-600 sm:text-base">{{ $formSubheading }}</p>

    @if (session('portal_access_easter_egg'))
        <p class="mt-4 rounded-md border border-sky-200 bg-sky-50 px-3 py-2.5 text-sm leading-relaxed text-sky-950 dark:border-sky-800/60 dark:bg-sky-950/40 dark:text-sky-100">
            {{ session('portal_access_easter_egg') }}
        </p>
    @endif

    <form method="POST" action="{{ route('portal.access.challenges.store') }}" class="mt-6 space-y-4">
        @csrf

        <div>
            <label for="contact" class="block text-sm font-semibold text-slate-900">Email or mobile number</label>
            <input
                id="contact"
                name="contact"
                type="text"
                inputmode="email"
                autocomplete="email tel"
                value="{{ old('contact') }}"
                required
                class="mt-2 w-full rounded-md border border-slate-300 px-3 py-2.5 text-sm text-slate-950 shadow-sm focus:border-[#0099cc] focus:outline-none focus:ring-2 focus:ring-sky-100"
                placeholder="{{ $contactPlaceholder }}"
            >
            @error('contact')
                <p class="mt-2 text-sm font-semibold text-red-700">{{ $message }}</p>
            @enderror
        </div>

        <button type="submit" class="public-btn-primary">
            {{ $submitLabel }}
        </button>
    </form>
</section>
