<x-operations.app title="Create Contact" ui="tabler">
    <x-tabler.page layout="focused" title="Create contact">
        <x-slot:actions>
            <x-tabler.page-action :href="$cancelUrl" icon="x" label="Cancel" />
        </x-slot:actions>

        <form method="POST" action="{{ $formAction }}" class="card" data-create-contact-form>
            @csrf

            @if ($conversationId ?? null)
                <input type="hidden" name="conversation_id" value="{{ $conversationId }}">
            @endif

            @if ($callSessionId ?? null)
                <input type="hidden" name="call_session_id" value="{{ $callSessionId }}">
            @endif

            <input type="hidden" name="return_url" value="{{ $cancelUrl }}">

            <div class="card-header">
                <div>
                    <h2 class="card-title">{{ $contextTitle }}</h2>
                    <div class="card-subtitle">{{ $contextDetail }}</div>
                </div>
            </div>

            <div class="card-body p-2 p-md-3">
                <div class="row g-3">
                    <div class="col-12 col-sm-6">
                        <label for="ingress-contact-first-name" class="form-label">First name</label>
                        <input
                            id="ingress-contact-first-name"
                            name="first_name"
                            value="{{ old('first_name', $firstName) }}"
                            required
                            autofocus
                            @class(['form-control', 'is-invalid' => $errors->has('first_name')])
                        >
                        @error('first_name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-12 col-sm-6">
                        <label for="ingress-contact-last-name" class="form-label">Last name</label>
                        <input
                            id="ingress-contact-last-name"
                            name="last_name"
                            value="{{ old('last_name', $lastName) }}"
                            @unless ($lastNameOptional ?? false) required @endunless
                            @class(['form-control', 'is-invalid' => $errors->has('last_name')])
                            placeholder="{{ ($lastNameOptional ?? false) ? 'Optional' : '' }}"
                        >
                        @if ($lastNameOptional ?? false)
                            <div class="form-hint">Optional when the lead only gave a first name.</div>
                        @endif
                        @error('last_name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>

                    <div class="col-12">
                        <label for="ingress-contact-phone" class="form-label">Phone</label>
                        <input
                            id="ingress-contact-phone"
                            name="phone"
                            type="tel"
                            value="{{ old('phone', $displayPhone) }}"
                            required
                            @class(['form-control', 'is-invalid' => $errors->has('phone')])
                        >
                        @error('phone')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>

                    <div class="col-12">
                        <label for="ingress-contact-email" class="form-label">Email</label>
                        <input
                            id="ingress-contact-email"
                            name="email"
                            type="email"
                            value="{{ old('email', $email) }}"
                            @class(['form-control', 'is-invalid' => $errors->has('email')])
                        >
                        @error('email')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>

                    <div class="col-12">
                        @include('operations.customers.partials.contact-preference-select', [
                            'inputId' => 'ingress-contact-preference',
                            'labelClass' => 'form-label mb-0',
                            'selectClass' => 'form-select mt-2'.($errors->has('contact_preference') ? ' is-invalid' : ''),
                            'selected' => old('contact_preference', $contactPreference?->value),
                        ])
                        @error('contact_preference')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                    </div>

                    <div class="col-12 col-sm-6">
                        <label for="ingress-contact-referral" class="form-label">Referral source</label>
                        <select id="ingress-contact-referral" name="referral_source" @class(['form-select', 'is-invalid' => $errors->has('referral_source')])>
                            @foreach (\App\Ark\Operations\Encounters\EncounterSource::cases() as $source)
                                <option
                                    value="{{ $source->value }}"
                                    @selected(old('referral_source', $referralSource?->value) === $source->value)
                                >{{ $source->label() }}</option>
                            @endforeach
                        </select>
                        @error('referral_source')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>

                    <div class="col-12 col-sm-6">
                        <label for="ingress-contact-type" class="form-label">Customer type</label>
                        <select id="ingress-contact-type" name="customer_type" @class(['form-select', 'is-invalid' => $errors->has('customer_type')])>
                            @foreach ($customerTypes as $customerType)
                                <option
                                    value="{{ $customerType }}"
                                    @selected(old('customer_type', $defaultCustomerType) === $customerType)
                                >{{ $customerType }}</option>
                            @endforeach
                        </select>
                        @error('customer_type')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                </div>
            </div>

            <div class="card-footer">
                <div class="btn-list justify-content-end">
                    <a href="{{ $cancelUrl }}" class="btn btn-ghost-secondary">Cancel</a>
                    <button type="submit" class="btn btn-primary">Save contact</button>
                </div>
            </div>
        </form>
    </x-tabler.page>
</x-operations.app>
