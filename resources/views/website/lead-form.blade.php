<form class="public-panel mt-6 max-w-xl" method="post" action="/leads">
    @csrf
    <input type="hidden" name="page" value="{{ $page ?? 'contact' }}">
    <p class="hidden" aria-hidden="true">
        <label>Company website <input type="text" name="company_website" tabindex="-1" autocomplete="off"></label>
    </p>

    <label class="block text-sm font-medium" for="contact_name">Name</label>
    <input class="mt-1 w-full rounded border border-slate-300 px-3 py-2" id="contact_name" name="contact_name" value="{{ old('contact_name') }}">

    <label class="mt-4 block text-sm font-medium" for="contact_phone">Phone</label>
    <input class="mt-1 w-full rounded border border-slate-300 px-3 py-2" id="contact_phone" name="contact_phone" required value="{{ old('contact_phone') }}">
    @error('contact_phone')
        <p class="mt-1 text-sm text-red-700">{{ $message }}</p>
    @enderror

    <label class="mt-4 block text-sm font-medium" for="contact_email">Email</label>
    <input class="mt-1 w-full rounded border border-slate-300 px-3 py-2" id="contact_email" name="contact_email" type="email" value="{{ old('contact_email') }}">

    <label class="mt-4 block text-sm font-medium" for="concern">What is going on?</label>
    <textarea class="mt-1 w-full rounded border border-slate-300 px-3 py-2" id="concern" name="concern" rows="4" required>{{ old('concern', $concern ?? '') }}</textarea>
    @error('concern')
        <p class="mt-1 text-sm text-red-700">{{ $message }}</p>
    @enderror

    <button class="public-cta public-cta--primary mt-4" type="submit">Send</button>
</form>
