@php
    use App\Ark\Operations\Settings\ShopSettings;

    $hubCustomerTypes = $customerTypes ?? ShopSettings::current()->customerTypeRows();
@endphp

<x-operations.workspace-modal
    :initial-task="$initialTask ?? null"
    :initial-context="$initialContext ?? []"
>
    <div class="ops-workspace-modal__panel" x-show="task === 'hub-customer'" x-cloak>
        <form
            method="POST"
            action="{{ route('operations.customers.update', $customer) }}"
            data-workspace-modal-form="hub-customer"
            class="space-y-3"
        >
            @csrf
            @method('PATCH')
            <div class="grid gap-3 md:grid-cols-2">
                <label class="block text-[11px] font-medium text-slate-500">
                    First name
                    <input name="first_name" value="{{ old('first_name', $customer->first_name) }}" required class="mt-1 w-full rounded-sm border border-slate-300 px-3 py-2 text-sm">
                </label>
                <label class="block text-[11px] font-medium text-slate-500">
                    Last name
                    <input name="last_name" value="{{ old('last_name', $customer->last_name) }}" required class="mt-1 w-full rounded-sm border border-slate-300 px-3 py-2 text-sm">
                </label>
                <label class="block text-[11px] font-medium text-slate-500">
                    Phone
                    <input name="phone" value="{{ old('phone', $customer->phone) }}" class="mt-1 w-full rounded-sm border border-slate-300 px-3 py-2 text-sm">
                </label>
                <label class="block text-[11px] font-medium text-slate-500">
                    Email
                    <input name="email" value="{{ old('email', $customer->email) }}" type="email" class="mt-1 w-full rounded-sm border border-slate-300 px-3 py-2 text-sm">
                </label>
                @include('operations.customers.partials.contact-preference-select', [
                    'selected' => old('contact_preference', $customer->contact_preference?->value),
                    'inputId' => 'hub-customer-contact-preference',
                ])
                <label class="block text-[11px] font-medium text-slate-500 md:col-span-2">
                    Messenger PSID
                    <input
                        name="messenger_psid"
                        value="{{ old('messenger_psid', $customer->messenger_psid) }}"
                        class="mt-1 w-full rounded-sm border border-slate-300 px-3 py-2 text-sm"
                        autocomplete="off"
                    >
                </label>
                <label class="block text-[11px] font-medium text-slate-500 md:col-span-2">
                    Billing class
                    <select name="customer_type" class="mt-1 w-full rounded-sm border border-slate-300 px-3 py-2 text-sm">
                        @foreach ($hubCustomerTypes as $type)
                            <option value="{{ $type['name'] }}" @selected(old('customer_type', $customer->customer_type ?: 'Retail') === $type['name'])>{{ $type['name'] }}</option>
                        @endforeach
                    </select>
                </label>
            </div>
            <label class="block text-[11px] font-medium text-slate-500">
                Notes
                <textarea name="notes" rows="8" class="mt-1 w-full rounded-sm border border-slate-300 px-3 py-2 text-sm font-mono leading-5">{{ old('notes', $customer->notes) }}</textarea>
            </label>
        </form>
    </div>

    @foreach ($customer->vehicles as $hubVehicle)
        <div
            class="ops-workspace-modal__panel"
            x-show="task === 'hub-vehicle' && String(context.vehicleId) === '{{ $hubVehicle->id }}'"
            x-cloak
        >
            <form
                method="POST"
                action="{{ route('operations.customers.vehicles.update', [$customer, $hubVehicle]) }}"
                data-workspace-modal-form="hub-vehicle"
                class="space-y-3"
                x-data="arkVehicleDecode(@js([
                    'decodeUrl' => route('operations.vehicles.decode-vin'),
                    'csrfToken' => csrf_token(),
                ]))"
            >
                @csrf
                @method('PATCH')
                @include('operations.vehicles.partials.workspace-vehicle-fields', [
                    'vehicle' => $hubVehicle,
                    'inputClass' => 'mt-1 w-full rounded-sm border border-slate-300 px-3 py-2 text-sm',
                    'buttonClass' => 'self-end rounded-sm border border-slate-300 px-3 py-2 text-xs font-semibold text-slate-700 hover:border-slate-400 disabled:opacity-60',
                ])
            </form>
        </div>
    @endforeach

    <div class="ops-workspace-modal__panel" x-show="task === 'hub-vehicle-create'" x-cloak>
        <form
            method="POST"
            action="{{ route('operations.customers.vehicles.store', $customer) }}"
            data-workspace-modal-form="hub-vehicle-create"
            class="space-y-3"
            x-data="arkVehicleDecode(@js([
                'decodeUrl' => route('operations.vehicles.decode-vin'),
                'csrfToken' => csrf_token(),
            ]))"
        >
            @csrf
            @include('operations.vehicles.partials.workspace-vehicle-fields', [
                'vehicle' => null,
                'inputClass' => 'mt-1 w-full rounded-sm border border-slate-300 px-3 py-2 text-sm',
                'buttonClass' => 'self-end rounded-sm border border-slate-300 px-3 py-2 text-xs font-semibold text-slate-700 hover:border-slate-400 disabled:opacity-60',
            ])
        </form>
    </div>

    <div class="ops-workspace-modal__panel" x-show="task === 'hub-document'" x-cloak>
        @include('operations.documents.partials.add-document-panel', [
            'customer' => $customer,
            'storeUrl' => route('operations.customers.documents.store', $customer),
            'scanUrl' => route('operations.customers.documents.scan', $customer),
            'customerRepairOrders' => $customer->repairOrders ?? collect(),
            'repairOrder' => null,
        ])
    </div>
</x-operations.workspace-modal>
