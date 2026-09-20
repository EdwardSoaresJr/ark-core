<x-operations.app title="Create Contact">
    <section class="ops-index space-y-3">
        <div class="ops-board-shell">
            <div class="ops-page-toolbar">
                <div>
                    <h1 class="ops-page-title">Create contact</h1>
                    <p class="ops-page-toolbar-note">{{ $contextTitle }} · {{ $contextDetail }}</p>
                </div>
                <div class="ops-page-toolbar-actions">
                    <a href="{{ $cancelUrl }}" class="ops-page-link">Cancel</a>
                </div>
            </div>
        </div>

        <div class="ops-board-shell px-4 py-4">
            <form method="POST" action="{{ $formAction }}" class="mx-auto max-w-xl space-y-4">
                @csrf

                @if ($conversationId ?? null)
                    <input type="hidden" name="conversation_id" value="{{ $conversationId }}">
                @endif

                @if ($callSessionId ?? null)
                    <input type="hidden" name="call_session_id" value="{{ $callSessionId }}">
                @endif

                <input type="hidden" name="return_url" value="{{ $cancelUrl }}">

                <div class="grid gap-3 sm:grid-cols-2">
                    <div>
                        <label for="ingress-contact-first-name" class="ops-index-field-label">First name</label>
                        <input
                            id="ingress-contact-first-name"
                            name="first_name"
                            value="{{ old('first_name', $firstName) }}"
                            required
                            autofocus
                            class="ops-intake-control mt-1 w-full"
                        >
                        @error('first_name')<p class="mt-1 text-xs font-semibold text-rose-700">{{ $message }}</p>@enderror
                    </div>
                    <div>
                        <label for="ingress-contact-last-name" class="ops-index-field-label">Last name</label>
                        <input
                            id="ingress-contact-last-name"
                            name="last_name"
                            value="{{ old('last_name', $lastName) }}"
                            @unless ($lastNameOptional ?? false) required @endunless
                            class="ops-intake-control mt-1 w-full"
                            placeholder="{{ ($lastNameOptional ?? false) ? 'Optional' : '' }}"
                        >
                        @if ($lastNameOptional ?? false)
                            <p class="mt-1 text-xs text-slate-600">Optional when the lead only gave a first name.</p>
                        @endif
                        @error('last_name')<p class="mt-1 text-xs font-semibold text-rose-700">{{ $message }}</p>@enderror
                    </div>
                </div>

                <div>
                    <label for="ingress-contact-phone" class="ops-index-field-label">Phone</label>
                    <input
                        id="ingress-contact-phone"
                        name="phone"
                        type="tel"
                        value="{{ old('phone', $displayPhone) }}"
                        required
                        class="ops-intake-control mt-1 w-full"
                    >
                    @error('phone')<p class="mt-1 text-xs font-semibold text-rose-700">{{ $message }}</p>@enderror
                </div>

                <div>
                    <label for="ingress-contact-email" class="ops-index-field-label">Email</label>
                    <input
                        id="ingress-contact-email"
                        name="email"
                        type="email"
                        value="{{ old('email', $email) }}"
                        class="ops-intake-control mt-1 w-full"
                    >
                    @error('email')<p class="mt-1 text-xs font-semibold text-rose-700">{{ $message }}</p>@enderror
                </div>

                <div>
                    @include('operations.customers.partials.contact-preference-select', [
                        'inputId' => 'ingress-contact-preference',
                        'labelClass' => 'ops-index-field-label',
                        'selectClass' => 'ops-intake-control mt-1 w-full',
                        'selected' => old('contact_preference', $contactPreference?->value),
                    ])
                    @error('contact_preference')<p class="mt-1 text-xs font-semibold text-rose-700">{{ $message }}</p>@enderror
                </div>

                <div>
                    <label for="ingress-contact-referral" class="ops-index-field-label">Referral source</label>
                    <select id="ingress-contact-referral" name="referral_source" class="ops-intake-control mt-1 w-full">
                        @foreach (\App\Ark\Operations\Encounters\EncounterSource::cases() as $source)
                            <option
                                value="{{ $source->value }}"
                                @selected(old('referral_source', $referralSource?->value) === $source->value)
                            >{{ $source->label() }}</option>
                        @endforeach
                    </select>
                    @error('referral_source')<p class="mt-1 text-xs font-semibold text-rose-700">{{ $message }}</p>@enderror
                </div>

                <div>
                    <label for="ingress-contact-type" class="ops-index-field-label">Customer type</label>
                    <select id="ingress-contact-type" name="customer_type" class="ops-intake-control mt-1 w-full">
                        @foreach ($customerTypes as $customerType)
                            <option
                                value="{{ $customerType }}"
                                @selected(old('customer_type', $defaultCustomerType) === $customerType)
                            >{{ $customerType }}</option>
                        @endforeach
                    </select>
                    @error('customer_type')<p class="mt-1 text-xs font-semibold text-rose-700">{{ $message }}</p>@enderror
                </div>

                <div class="flex flex-wrap gap-2 pt-2">
                    <button type="submit" class="ops-page-link ops-page-link--primary">Save contact</button>
                    <a href="{{ $cancelUrl }}" class="ops-page-link">Cancel</a>
                </div>
            </form>
        </div>
    </section>
</x-operations.app>
