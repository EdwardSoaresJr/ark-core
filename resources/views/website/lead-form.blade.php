<form class="public-panel public-lead-form" method="post" action="/leads">
    @csrf
    <input type="hidden" name="page" value="{{ $page ?? 'contact' }}">
    <p class="hidden" aria-hidden="true">
        <label>Company website <input type="text" name="company_website" tabindex="-1" autocomplete="off"></label>
    </p>

    <div class="public-lead-form__field">
        <label class="public-lead-form__label" for="contact_name">Name</label>
        <input class="public-lead-form__input" id="contact_name" name="contact_name" autocomplete="name" value="{{ old('contact_name') }}">
    </div>

    <div class="public-lead-form__field">
        <label class="public-lead-form__label" for="contact_phone">Phone</label>
        <input class="public-lead-form__input" id="contact_phone" name="contact_phone" type="tel" autocomplete="tel" required value="{{ old('contact_phone') }}">
        @error('contact_phone')
            <p class="public-lead-form__error">{{ $message }}</p>
        @enderror
    </div>

    <div class="public-lead-form__field">
        <label class="public-lead-form__label" for="contact_email">Email</label>
        <input class="public-lead-form__input" id="contact_email" name="contact_email" type="email" autocomplete="email" value="{{ old('contact_email') }}">
        @error('contact_email')
            <p class="public-lead-form__error">{{ $message }}</p>
        @enderror
    </div>

    <div class="public-lead-form__field">
        <label class="public-lead-form__label" for="concern">What is going on?</label>
        <textarea class="public-lead-form__textarea" id="concern" name="concern" rows="4" required>{{ old('concern', $concern ?? '') }}</textarea>
        @error('concern')
            <p class="public-lead-form__error">{{ $message }}</p>
        @enderror
    </div>

    <div class="public-lead-form__field">
        <button class="public-cta public-cta--primary" type="submit">Send message</button>
    </div>
</form>
