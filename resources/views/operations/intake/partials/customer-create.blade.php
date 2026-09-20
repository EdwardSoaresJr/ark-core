@can(App\Ark\Runtime\Authorization\ArkCapability::CustomersManage->value)
    @php
        $billingClassHelp = "Sets default billing for new concerns on this customer.\n\nDoes not set visit type. Pick waiting or drop-off when you open the RO.";
    @endphp
    <section class="ops-intake-customer-create">
        <div class="ops-intake-customer-create-head">
            <p class="ops-intake-customer-create-label" x-text="panelLabel"></p>
            <button
                type="button"
                x-show="isEditing"
                x-cloak
                @click="clearSelectedCustomer()"
                class="ops-intake-customer-create-reset"
            >New customer</button>
        </div>
        <form
            method="POST"
            :action="formAction"
            class="ops-intake-customer-create-form"
        >
            @csrf
            <input type="hidden" name="intake" value="1">
            @include('operations.intake.partials.intake-workspace-hidden')
            <template x-if="isEditing">
                <input type="hidden" name="_method" value="PATCH">
            </template>
            <template x-if="isEditing">
                <input type="hidden" name="customer_id" :value="selectedCustomerId">
            </template>
            <div class="ops-intake-fields ops-intake-fields--2">
                <div class="ops-intake-field">
                    <label for="intake-customer-first-name" class="ops-index-field-label">First name</label>
                    <input
                        id="intake-customer-first-name"
                        name="first_name"
                        value="{{ $leadPrefillFirstName ?? '' }}"
                        x-model="firstName"
                        required
                        class="ops-intake-control"
                        @if ($focusFirstName ?? false) autofocus @endif
                    >
                    @error('first_name')<p class="text-xs font-semibold text-rose-700">{{ $message }}</p>@enderror
                </div>
                <div class="ops-intake-field">
                    <label for="intake-customer-last-name" class="ops-index-field-label">Last name</label>
                    <input
                        id="intake-customer-last-name"
                        name="last_name"
                        value="{{ $leadPrefillLastName ?? '' }}"
                        x-model="lastName"
                        @unless ($lastNameOptional ?? false) required @endunless
                        class="ops-intake-control"
                        placeholder="{{ ($lastNameOptional ?? false) ? 'Optional for website leads' : '' }}"
                    >
                    @error('last_name')<p class="text-xs font-semibold text-rose-700">{{ $message }}</p>@enderror
                </div>
            </div>
            <div class="ops-intake-field">
                <label for="intake-customer-phone" class="ops-index-field-label">Phone</label>
                <input
                    id="intake-customer-phone"
                    name="phone"
                    x-model="phone"
                    type="tel"
                    class="ops-intake-control"
                    :required="!isEditing"
                >
                @error('phone')<p class="text-xs font-semibold text-rose-700">{{ $message }}</p>@enderror
            </div>
            <div class="ops-intake-field">
                <label for="intake-customer-email" class="ops-index-field-label">Email</label>
                <input id="intake-customer-email" name="email" x-model="email" type="email" class="ops-intake-control">
                @error('email')<p class="text-xs font-semibold text-rose-700">{{ $message }}</p>@enderror
            </div>
            <div class="ops-intake-field">
                @include('operations.customers.partials.contact-preference-select', [
                    'inputId' => 'intake-customer-contact-preference',
                    'labelClass' => 'ops-index-field-label',
                    'selectClass' => 'ops-intake-control',
                    'xModel' => 'contactPreference',
                ])
                @error('contact_preference')<p class="text-xs font-semibold text-rose-700">{{ $message }}</p>@enderror
            </div>

            <div class="ops-intake-customer-create-details">
                    <div class="ops-intake-fields ops-intake-fields--address">
                        <div class="ops-intake-field">
                            <label for="intake-customer-address-line-1" class="ops-index-field-label">Address</label>
                            <input id="intake-customer-address-line-1" name="address_line_1" x-model="addressLine1" class="ops-intake-control" placeholder="Street address">
                            @error('address_line_1')<p class="text-xs font-semibold text-rose-700">{{ $message }}</p>@enderror
                        </div>
                        <div class="ops-intake-field">
                            <label for="intake-customer-address-line-2" class="ops-index-field-label">Apt / unit / suite</label>
                            <input id="intake-customer-address-line-2" name="address_line_2" x-model="addressLine2" class="ops-intake-control" placeholder="Unit 4B, Apt 12, Suite 200">
                            @error('address_line_2')<p class="text-xs font-semibold text-rose-700">{{ $message }}</p>@enderror
                        </div>
                    </div>
                    <div class="ops-intake-fields ops-intake-fields--locality">
                        <div class="ops-intake-field">
                            <label for="intake-customer-city" class="ops-index-field-label">City</label>
                            <input id="intake-customer-city" name="city" x-model="city" class="ops-intake-control">
                            @error('city')<p class="text-xs font-semibold text-rose-700">{{ $message }}</p>@enderror
                        </div>
                        <div class="ops-intake-field">
                            <label for="intake-customer-state" class="ops-index-field-label">State</label>
                            <input id="intake-customer-state" name="state" x-model="state" maxlength="32" class="ops-intake-control">
                            @error('state')<p class="text-xs font-semibold text-rose-700">{{ $message }}</p>@enderror
                        </div>
                        <div class="ops-intake-field">
                            <label for="intake-customer-postal-code" class="ops-index-field-label">ZIP</label>
                            <input id="intake-customer-postal-code" name="postal_code" x-model="postalCode" maxlength="16" class="ops-intake-control">
                            @error('postal_code')<p class="text-xs font-semibold text-rose-700">{{ $message }}</p>@enderror
                        </div>
                    </div>
                    <div class="ops-intake-fields ops-intake-fields--2">
                        <div class="ops-intake-field">
                            <label for="intake-customer-referral-source" class="ops-index-field-label">How they heard about us</label>
                            <select id="intake-customer-referral-source" name="referral_source" x-model="referralSource" class="ops-intake-control">
                                <option value="">Choose one</option>
                                @foreach ($referralSources as $value => $label)
                                    <option value="{{ $value }}">{{ $label }}</option>
                                @endforeach
                            </select>
                            @error('referral_source')<p class="text-xs font-semibold text-rose-700">{{ $message }}</p>@enderror
                        </div>
                        <div class="ops-intake-field">
                            <div class="flex items-center gap-1">
                                <label for="intake-customer-type" class="ops-index-field-label">Billing class</label>
                                <x-operations.help-tip
                                    label="Billing class help"
                                    :text="$billingClassHelp"
                                />
                            </div>
                            <select id="intake-customer-type" name="customer_type" x-model="customerType" class="ops-intake-control">
                                @foreach ($customerTypes as $type)
                                    <option value="{{ $type['name'] }}">{{ $type['name'] }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>
            </div>

            <p
                x-show="isEditing && selectedCustomerName"
                x-cloak
                class="ops-intake-customer-create-selected"
            >
                Selected <span x-text="selectedCustomerName"></span> — review details, then continue.
            </p>

            <div class="ops-intake-field ops-intake-field--action">
                <button type="submit" class="ops-index-btn ops-index-btn--primary ops-intake-customer-create-btn" x-text="submitLabel"></button>
            </div>

            <p
                x-show="duplicateLoading"
                x-cloak
                class="ops-intake-duplicate-hint-status"
                aria-live="polite"
            >Checking for similar customers…</p>

            <div x-ref="duplicates"></div>
        </form>
    </section>
@endcan
