@php
    use App\Ark\Operations\Settings\ShopSettings;

    $hubCustomerTypes = $customerTypes ?? ShopSettings::current()->customerTypeRows();
    $customerFieldInvalid = fn (string $field): bool => $errors->has($field);
@endphp

<x-tabler.workspace-modal
    :initial-task="$initialTask ?? null"
    :initial-context="$initialContext ?? []"
>
    <div data-workspace-modal-panel x-show="task === 'hub-customer'" x-cloak>
        <form
            method="POST"
            action="{{ route('operations.customers.update', $customer) }}"
            data-workspace-modal-form="hub-customer"
        >
            @csrf
            @method('PATCH')
            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label" for="hub-customer-first-name">First name</label>
                    <input id="hub-customer-first-name" name="first_name" value="{{ old('first_name', $customer->first_name) }}" required @class(['form-control', 'is-invalid' => $customerFieldInvalid('first_name')])>
                    @error('first_name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-md-6">
                    <label class="form-label" for="hub-customer-last-name">Last name</label>
                    <input id="hub-customer-last-name" name="last_name" value="{{ old('last_name', $customer->last_name) }}" required @class(['form-control', 'is-invalid' => $customerFieldInvalid('last_name')])>
                    @error('last_name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-md-6">
                    <label class="form-label" for="hub-customer-phone">Phone</label>
                    <input id="hub-customer-phone" name="phone" value="{{ old('phone', $customer->phone) }}" @class(['form-control', 'is-invalid' => $customerFieldInvalid('phone')])>
                    @error('phone')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-md-6">
                    <label class="form-label" for="hub-customer-email">Email</label>
                    <input id="hub-customer-email" name="email" value="{{ old('email', $customer->email) }}" type="email" @class(['form-control', 'is-invalid' => $customerFieldInvalid('email')])>
                    @error('email')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-md-6">
                    @include('operations.customers.partials.contact-preference-select', [
                        'selected' => old('contact_preference', $customer->contact_preference?->value),
                        'inputId' => 'hub-customer-contact-preference',
                        'labelClass' => 'form-label',
                        'selectClass' => 'form-select mt-2',
                    ])
                    @error('contact_preference')<div class="small text-danger mt-1">{{ $message }}</div>@enderror
                </div>
                <div class="col-12">
                    <label class="form-label" for="hub-customer-messenger-psid">Messenger PSID</label>
                    <input id="hub-customer-messenger-psid" name="messenger_psid" value="{{ old('messenger_psid', $customer->messenger_psid) }}" autocomplete="off" @class(['form-control', 'is-invalid' => $customerFieldInvalid('messenger_psid')])>
                    @error('messenger_psid')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-12">
                    <label class="form-label" for="hub-customer-type">Billing class</label>
                    <select id="hub-customer-type" name="customer_type" @class(['form-select', 'is-invalid' => $customerFieldInvalid('customer_type')])>
                        @foreach ($hubCustomerTypes as $type)
                            <option value="{{ $type['name'] }}" @selected(old('customer_type', $customer->customer_type ?: 'Retail') === $type['name'])>{{ $type['name'] }}</option>
                        @endforeach
                    </select>
                    @error('customer_type')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-12">
                    <label class="form-label" for="hub-customer-notes">Notes</label>
                    <textarea id="hub-customer-notes" name="notes" rows="8" @class(['form-control font-monospace', 'is-invalid' => $customerFieldInvalid('notes')])>{{ old('notes', $customer->notes) }}</textarea>
                    @error('notes')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
            </div>
        </form>
    </div>

    @foreach ($customer->vehicles as $hubVehicle)
        <div
            data-workspace-modal-panel
            x-show="task === 'hub-vehicle' && String(context.vehicleId) === '{{ $hubVehicle->id }}'"
            x-cloak
        >
            <form
                method="POST"
                action="{{ route('operations.customers.vehicles.update', [$customer, $hubVehicle]) }}"
                data-workspace-modal-form="hub-vehicle"
                x-data="arkVehicleDecode(@js([
                    'decodeUrl' => route('operations.vehicles.decode-vin'),
                    'csrfToken' => csrf_token(),
                ]))"
            >
                @csrf
                @method('PATCH')
                @include('operations.vehicles.partials.workspace-vehicle-fields-tabler', [
                    'vehicle' => $hubVehicle,
                    'idPrefix' => 'hub-vehicle-'.$hubVehicle->id,
                ])
            </form>
        </div>
    @endforeach

    <div data-workspace-modal-panel x-show="task === 'hub-vehicle-create'" x-cloak>
        <form
            method="POST"
            action="{{ route('operations.customers.vehicles.store', $customer) }}"
            data-workspace-modal-form="hub-vehicle-create"
            x-data="arkVehicleDecode(@js([
                'decodeUrl' => route('operations.vehicles.decode-vin'),
                'csrfToken' => csrf_token(),
            ]))"
        >
            @csrf
            @include('operations.vehicles.partials.workspace-vehicle-fields-tabler', [
                'vehicle' => null,
                'idPrefix' => 'hub-vehicle-new',
            ])
        </form>
    </div>

    <div data-workspace-modal-panel x-show="task === 'hub-document'" x-cloak>
        @include('operations.documents.partials.add-document-panel-tabler', [
            'customer' => $customer,
            'storeUrl' => route('operations.customers.documents.store', $customer),
            'scanUrl' => route('operations.customers.documents.scan', $customer),
            'customerRepairOrders' => $customer->repairOrders ?? collect(),
            'repairOrder' => null,
        ])
    </div>
</x-tabler.workspace-modal>
