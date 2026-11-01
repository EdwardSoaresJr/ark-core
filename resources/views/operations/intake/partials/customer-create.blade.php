@can(App\Ark\Runtime\Authorization\ArkCapability::CustomersManage->value)
    @php
        $billingClassHelp = "Sets default billing for new concerns on this customer.\n\nDoes not set visit type. Pick waiting or drop-off when you open the RO.";
        $fieldError = fn (string $field) => $errors->first($field);
    @endphp
    <section class="card" data-intake-customer-create>
        <div class="card-header">
            <h3 class="card-title" x-text="panelLabel"></h3>
            <div class="card-actions">
                <button type="button" x-show="isEditing" x-cloak @click="clearSelectedCustomer()" class="btn btn-ghost-secondary">New customer</button>
            </div>
        </div>
        <form method="POST" :action="formAction" class="card-body p-2 p-md-3">
            @csrf
            <input type="hidden" name="intake" value="1">
            @include('operations.intake.partials.intake-workspace-hidden')
            <template x-if="isEditing">
                <input type="hidden" name="_method" value="PATCH">
            </template>
            <template x-if="isEditing">
                <input type="hidden" name="customer_id" :value="selectedCustomerId">
            </template>

            <div class="row g-3">
                <div class="col-sm-6">
                    <label for="intake-customer-first-name" class="form-label">First name</label>
                    <input
                        id="intake-customer-first-name"
                        name="first_name"
                        value="{{ $leadPrefillFirstName ?? '' }}"
                        x-model="firstName"
                        required
                        @class(['form-control', 'is-invalid' => $fieldError('first_name')])
                        @if ($focusFirstName ?? false) autofocus @endif
                    >
                    @error('first_name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-sm-6">
                    <label for="intake-customer-last-name" class="form-label">Last name</label>
                    <input
                        id="intake-customer-last-name"
                        name="last_name"
                        value="{{ $leadPrefillLastName ?? '' }}"
                        x-model="lastName"
                        @unless ($lastNameOptional ?? false) required @endunless
                        @class(['form-control', 'is-invalid' => $fieldError('last_name')])
                        placeholder="{{ ($lastNameOptional ?? false) ? 'Optional for website leads' : '' }}"
                    >
                    @error('last_name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-sm-6">
                    <label for="intake-customer-phone" class="form-label">Phone</label>
                    <input
                        id="intake-customer-phone"
                        name="phone"
                        x-model="phone"
                        type="tel"
                        @class(['form-control', 'is-invalid' => $fieldError('phone')])
                        :required="!isEditing"
                    >
                    @error('phone')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-sm-6">
                    <label for="intake-customer-email" class="form-label">Email</label>
                    <input id="intake-customer-email" name="email" x-model="email" type="email" @class(['form-control', 'is-invalid' => $fieldError('email')])>
                    @error('email')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-sm-6">
                    @include('operations.customers.partials.contact-preference-select', [
                        'inputId' => 'intake-customer-contact-preference',
                        'labelClass' => 'form-label',
                        'selectClass' => 'form-select mt-2',
                        'xModel' => 'contactPreference',
                    ])
                    @error('contact_preference')<div class="small text-danger mt-1">{{ $message }}</div>@enderror
                </div>
                <div class="col-sm-8">
                    <label for="intake-customer-address-line-1" class="form-label">Address</label>
                    <input id="intake-customer-address-line-1" name="address_line_1" x-model="addressLine1" @class(['form-control', 'is-invalid' => $fieldError('address_line_1')]) placeholder="Street address">
                    @error('address_line_1')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-sm-4">
                    <label for="intake-customer-address-line-2" class="form-label">Apt / unit / suite</label>
                    <input id="intake-customer-address-line-2" name="address_line_2" x-model="addressLine2" @class(['form-control', 'is-invalid' => $fieldError('address_line_2')]) placeholder="Unit 4B, Apt 12, Suite 200">
                    @error('address_line_2')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-sm-6">
                    <label for="intake-customer-city" class="form-label">City</label>
                    <input id="intake-customer-city" name="city" x-model="city" @class(['form-control', 'is-invalid' => $fieldError('city')])>
                    @error('city')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-6 col-sm-3">
                    <label for="intake-customer-state" class="form-label">State</label>
                    <input id="intake-customer-state" name="state" x-model="state" maxlength="32" @class(['form-control', 'is-invalid' => $fieldError('state')])>
                    @error('state')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-6 col-sm-3">
                    <label for="intake-customer-postal-code" class="form-label">ZIP</label>
                    <input id="intake-customer-postal-code" name="postal_code" x-model="postalCode" maxlength="16" @class(['form-control', 'is-invalid' => $fieldError('postal_code')])>
                    @error('postal_code')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-sm-6">
                    <label for="intake-customer-referral-source" class="form-label">How they heard about us</label>
                    <select id="intake-customer-referral-source" name="referral_source" x-model="referralSource" @class(['form-select', 'is-invalid' => $fieldError('referral_source')])>
                        <option value="">Choose one</option>
                        @foreach ($referralSources as $value => $label)
                            <option value="{{ $value }}">{{ $label }}</option>
                        @endforeach
                    </select>
                    @error('referral_source')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-sm-6">
                    <div class="d-flex align-items-center gap-1 mb-2">
                        <label for="intake-customer-type" class="form-label mb-0">Billing class</label>
                        <x-tabler.help-tip label="Billing class help" :text="$billingClassHelp" />
                    </div>
                    <select id="intake-customer-type" name="customer_type" x-model="customerType" class="form-select">
                        @foreach ($customerTypes as $type)
                            <option value="{{ $type['name'] }}">{{ $type['name'] }}</option>
                        @endforeach
                    </select>
                </div>
            </div>

            <div x-show="isEditing && selectedCustomerName" x-cloak class="alert alert-info mt-3 mb-0">
                Selected <span x-text="selectedCustomerName"></span> - review details, then continue.
            </div>

            <div class="d-grid d-sm-block mt-3">
                <button type="submit" class="btn btn-primary" x-text="submitLabel"></button>
            </div>

            <div x-show="duplicateLoading" x-cloak class="form-hint mt-2" aria-live="polite">Checking for similar customers…</div>

            <div x-ref="duplicates"></div>
        </form>
    </section>
@endcan
