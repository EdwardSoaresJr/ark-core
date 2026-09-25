<x-website.layout :website="$website" :seo="$seo" page="book">
    <article class="public-static-page">
        <h1 class="public-page-title">Request an appointment</h1>
        <p class="public-page-lede">Tell us what the car is doing and when you would like to come in. An advisor confirms the time during business hours. This is a request. It does not reserve a bay.</p>

        @if ($closed)
            <p class="public-page-lede mt-4">Online appointment requests are paused. Call or text the shop and we will help you find a time.</p>
            <p class="mt-4"><a class="public-link" href="{{ route('public.contact') }}">Contact the shop</a></p>
        @else
            @if (session('book_status'))
                <p class="mt-4">{{ session('book_status') }}</p>
            @endif

            <form class="public-panel mt-6 max-w-xl" method="post" action="/leads">
                @csrf
                <input type="hidden" name="page" value="book">
                <p class="hidden" aria-hidden="true">
                    <label>Company website <input type="text" name="company_website" tabindex="-1" autocomplete="off"></label>
                </p>

                <label class="block text-sm font-medium" for="concern_category">What is going on?</label>
                <select class="mt-1 w-full rounded border border-slate-300 px-3 py-2" id="concern_category" name="concern_category" required>
                    @foreach ($concerns as $concern)
                        <option value="{{ $concern }}" @selected(old('concern_category', $selectedConcern) === $concern)>{{ $concern }}</option>
                    @endforeach
                </select>

                <label class="mt-4 block text-sm font-medium" for="concern_details">Details</label>
                <textarea class="mt-1 w-full rounded border border-slate-300 px-3 py-2" id="concern_details" name="concern_details" rows="3">{{ old('concern_details', $concernDetails) }}</textarea>
                @error('concern_details')
                    <p class="mt-1 text-sm text-red-700">{{ $message }}</p>
                @enderror

                @if ($vehicles !== [])
                    <label class="mt-4 block text-sm font-medium" for="vehicle_selection">Vehicle</label>
                    <select class="mt-1 w-full rounded border border-slate-300 px-3 py-2" id="vehicle_selection" name="vehicle_selection">
                        @foreach ($vehicles as $vehicle)
                            <option value="{{ $vehicle['id'] }}" @selected(old('vehicle_selection') == $vehicle['id'])>{{ $vehicle['label'] }}</option>
                        @endforeach
                        <option value="other" @selected(old('vehicle_selection') === 'other')>A different vehicle</option>
                    </select>
                    @error('vehicle_selection')
                        <p class="mt-1 text-sm text-red-700">{{ $message }}</p>
                    @enderror
                @endif

                <div class="mt-4 grid gap-3 sm:grid-cols-3">
                    <div>
                        <label class="block text-sm font-medium" for="vehicle_year">Year</label>
                        <input class="mt-1 w-full rounded border border-slate-300 px-3 py-2" id="vehicle_year" name="vehicle_year" inputmode="numeric" value="{{ old('vehicle_year') }}">
                    </div>
                    <div>
                        <label class="block text-sm font-medium" for="vehicle_make">Make</label>
                        <input class="mt-1 w-full rounded border border-slate-300 px-3 py-2" id="vehicle_make" name="vehicle_make" value="{{ old('vehicle_make') }}">
                    </div>
                    <div>
                        <label class="block text-sm font-medium" for="vehicle_model">Model</label>
                        <input class="mt-1 w-full rounded border border-slate-300 px-3 py-2" id="vehicle_model" name="vehicle_model" value="{{ old('vehicle_model') }}">
                    </div>
                </div>

                <label class="mt-4 block text-sm font-medium" for="preferred_date">Preferred day</label>
                <select class="mt-1 w-full rounded border border-slate-300 px-3 py-2" id="preferred_date" name="preferred_date" required>
                    @foreach ($dates as $date)
                        <option value="{{ $date['date'] }}" @selected(old('preferred_date') === $date['date'])>{{ $date['label'] }}</option>
                    @endforeach
                </select>
                @error('preferred_date')
                    <p class="mt-1 text-sm text-red-700">{{ $message }}</p>
                @enderror

                <fieldset class="mt-4">
                    <legend class="text-sm font-medium">Preferred time</legend>
                    @foreach ($periods as $period)
                        <label class="mt-2 block">
                            <input type="radio" name="preferred_period" value="{{ $period['value'] }}" @checked(old('preferred_period', $periods[0]['value'] ?? '') === $period['value'])>
                            {{ $period['label'] }}
                        </label>
                    @endforeach
                </fieldset>

                <label class="mt-4 block text-sm font-medium" for="contact_name">Name</label>
                <input class="mt-1 w-full rounded border border-slate-300 px-3 py-2" id="contact_name" name="contact_name" required value="{{ old('contact_name', $contactName) }}">

                <label class="mt-4 block text-sm font-medium" for="contact_phone">Phone</label>
                <input class="mt-1 w-full rounded border border-slate-300 px-3 py-2" id="contact_phone" name="contact_phone" required value="{{ old('contact_phone', $contactPhone) }}">
                @error('contact_phone')
                    <p class="mt-1 text-sm text-red-700">{{ $message }}</p>
                @enderror
                @if ($phoneVerificationReady)
                    <label class="mt-3 block text-sm font-medium" for="phone_code">Phone code</label>
                    <input class="mt-1 w-full rounded border border-slate-300 px-3 py-2" id="phone_code" name="phone_code" inputmode="numeric" autocomplete="one-time-code" value="{{ old('phone_code') }}">
                    @error('phone_code')
                        <p class="mt-1 text-sm text-red-700">{{ $message }}</p>
                    @enderror
                    <button class="mt-2 text-sm underline" type="submit" name="book_intent" value="send_phone_code">Text me a code</button>
                    <button class="mt-2 ml-3 text-sm underline" type="submit" name="book_intent" value="check_phone_code">Verify phone</button>
                @endif

                <label class="mt-4 block text-sm font-medium" for="contact_email">Email</label>
                <input class="mt-1 w-full rounded border border-slate-300 px-3 py-2" id="contact_email" name="contact_email" value="{{ old('contact_email', $contactEmail) }}">
                @error('contact_email')
                    <p class="mt-1 text-sm text-red-700">{{ $message }}</p>
                @enderror
                @if ($emailVerificationReady)
                    <label class="mt-3 block text-sm font-medium" for="email_code">Email code</label>
                    <input class="mt-1 w-full rounded border border-slate-300 px-3 py-2" id="email_code" name="email_code" inputmode="numeric" autocomplete="one-time-code" value="{{ old('email_code') }}">
                    @error('email_code')
                        <p class="mt-1 text-sm text-red-700">{{ $message }}</p>
                    @enderror
                    <button class="mt-2 text-sm underline" type="submit" name="book_intent" value="send_email_code">Email me a code</button>
                    <button class="mt-2 ml-3 text-sm underline" type="submit" name="book_intent" value="check_email_code">Verify email</button>
                @endif

                <fieldset class="mt-4">
                    <legend class="text-sm font-medium">How should we reach you?</legend>
                    @foreach ($contactPreferences as $preference)
                        <label class="mt-2 block">
                            <input type="radio" name="contact_preference" value="{{ $preference->value }}" @checked(old('contact_preference', 'text') === $preference->value)>
                            {{ $preference->formLabel() }}
                        </label>
                    @endforeach
                </fieldset>

                <button class="public-cta public-cta--primary mt-6" type="submit">Send appointment request</button>
            </form>
        @endif
    </article>
</x-website.layout>
