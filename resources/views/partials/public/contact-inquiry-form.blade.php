@php
    use App\Ark\Operations\Leads\Public\PublicLeadFormContactPrefill;

    $contactPrefill = app(PublicLeadFormContactPrefill::class)->forCurrentCustomer();
    $nameValue = old('name', trim(($contactPrefill['first_name'] ?? '').' '.($contactPrefill['last_name'] ?? '')));
    $emailValue = old('email', $contactPrefill['email'] ?? '');
    $phoneValue = old('phone', $contactPrefill['phone'] ?? '');
    $subjectValue = old('subject', '');
    $messageValue = old('message', '');
    $surfaceContext = $surfaceContext ?? \App\Ark\Operations\Leads\Public\PublicSurfaceContext::contact();
@endphp

<section class="public-panel public-panel--accent" id="send-a-message">
    <h2 class="text-xl font-bold tracking-tight text-slate-950">{{ $formHeading ?? 'Send a message' }}</h2>
    @if (filled($formSubheading ?? null))
        <p class="mt-1.5 text-sm leading-relaxed text-slate-600 sm:text-base">{{ $formSubheading }}</p>
    @endif

    <form
        method="POST"
        action="{{ route('public.leads.store') }}"
        class="public-lead-form mt-5 space-y-4"
        data-public-surface-page="{{ $surfaceContext->page }}"
        data-public-surface-variant="contact_inquiry"
        data-public-surface-placement="{{ $surfaceContext->placement }}"
    >
        @csrf
        <input type="hidden" name="source" value="website">
        <input type="hidden" name="form_rendered_at" value="{{ $formRenderedAt ?? now()->timestamp }}">
        <input type="hidden" name="public_surface_page" value="{{ $surfaceContext->page }}">
        <input type="hidden" name="public_surface_variant" value="contact_inquiry">
        <input type="hidden" name="public_surface_placement" value="{{ $surfaceContext->placement }}">
        <input type="hidden" name="contact_inquiry" value="1">

        <div class="hidden" aria-hidden="true">
            <label for="company_website_contact">Company website</label>
            <input type="text" name="company_website" id="company_website_contact" tabindex="-1" autocomplete="off">
        </div>

        <div>
            <label for="contact_name" class="public-lead-form__label">Name</label>
            <input
                id="contact_name"
                name="name"
                type="text"
                required
                autocomplete="name"
                value="{{ $nameValue }}"
                class="public-lead-form__input mt-1.5"
            >
            @error('name')
                <p class="mt-1 text-xs font-semibold text-rose-700">{{ $message }}</p>
            @enderror
        </div>

        <div>
            <label for="contact_email" class="public-lead-form__label">Email</label>
            <input
                id="contact_email"
                name="email"
                type="email"
                required
                autocomplete="email"
                value="{{ $emailValue }}"
                class="public-lead-form__input mt-1.5"
            >
            @error('email')
                <p class="mt-1 text-xs font-semibold text-rose-700">{{ $message }}</p>
            @enderror
        </div>

        <div>
            <label for="contact_phone" class="public-lead-form__label">Phone <span class="font-normal text-slate-400">(optional)</span></label>
            <input
                id="contact_phone"
                name="phone"
                type="tel"
                autocomplete="tel"
                value="{{ $phoneValue }}"
                class="public-lead-form__input mt-1.5"
            >
            @error('phone')
                <p class="mt-1 text-xs font-semibold text-rose-700">{{ $message }}</p>
            @enderror
        </div>

        <div>
            <label for="contact_subject" class="public-lead-form__label">Subject</label>
            <input
                id="contact_subject"
                name="subject"
                type="text"
                required
                maxlength="200"
                value="{{ $subjectValue }}"
                class="public-lead-form__input mt-1.5"
                placeholder="How can we help?"
            >
            @error('subject')
                <p class="mt-1 text-xs font-semibold text-rose-700">{{ $message }}</p>
            @enderror
        </div>

        <div>
            <label for="contact_message" class="public-lead-form__label">Message</label>
            <textarea
                id="contact_message"
                name="message"
                rows="5"
                required
                maxlength="5000"
                class="public-lead-form__textarea mt-1.5"
                placeholder="Write your message…"
            >{{ $messageValue }}</textarea>
            @error('message')
                <p class="mt-1 text-xs font-semibold text-rose-700">{{ $message }}</p>
            @enderror
        </div>

        <button type="submit" class="public-btn-primary w-full sm:w-auto">
            {{ $submitLabel ?? 'Send message' }}
        </button>
    </form>
</section>
