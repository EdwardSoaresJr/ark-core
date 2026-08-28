<x-portal.app>
    <x-customer.split-page variant="public">
        <x-slot:primary>
            <h1 class="text-3xl font-bold tracking-tight text-slate-950 sm:text-4xl">Your vehicles and visits</h1>
            <p class="mt-4 text-base leading-relaxed text-slate-600">
                Sign in to see estimates, invoices, and service history.
            </p>
        </x-slot:primary>

        <x-slot:rail>
            <section class="public-panel public-panel--accent">
                <h2 class="text-xl font-bold tracking-tight text-slate-950">Sign in</h2>
                <p class="mt-1.5 text-sm leading-relaxed text-slate-600 sm:text-base">
                    We’ll send a 6-digit code to the email or mobile number on file.
                </p>
                <a
                    href="{{ route('portal.access') }}"
                    class="public-btn-primary mt-6"
                >
                    Continue to sign in
                </a>
            </section>
        </x-slot:rail>
    </x-customer.split-page>
</x-portal.app>
