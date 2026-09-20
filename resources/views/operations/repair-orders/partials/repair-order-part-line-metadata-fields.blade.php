@php
    use App\Ark\Operations\RepairOrders\CustomerPartPosture;
    use App\Ark\Operations\RepairOrders\PartLineClassification;
    use App\Ark\Operations\RepairOrders\PartLineSource;
    use App\Ark\Operations\RepairOrders\PartLineWarrantyImpact;

    $partSource = PartLineSource::fromStored($partSource ?? null);
    $partClassification = PartLineClassification::tryFromStored($partClassification ?? null);
    $partWarrantyImpact = PartLineWarrantyImpact::fromStored($partWarrantyImpact ?? null);
    $customerPartPosture = CustomerPartPosture::tryFromInput($customerPartPosture ?? null)
        ?? (isset($currentProcurementState) ? CustomerPartPosture::fromProcurementState($currentProcurementState) : null);
@endphp

<div x-data="{ partSource: @js($partSource->value) }" class="space-y-2">
    <div class="grid gap-2 sm:grid-cols-3">
        <label class="ops-field">
            <span class="ops-field-label">Part source</span>
            <select name="part_source" x-model="partSource" class="ops-field-input">
                @foreach (PartLineSource::cases() as $option)
                    <option value="{{ $option->value }}" @selected($partSource === $option)>{{ $option->label() }}</option>
                @endforeach
            </select>
        </label>
        <label class="ops-field">
            <span class="ops-field-label">Part type</span>
            <select name="part_classification" class="ops-field-input">
                <option value="" @selected($partClassification === null)>Not set</option>
                @foreach (PartLineClassification::cases() as $option)
                    <option value="{{ $option->value }}" @selected($partClassification === $option)>{{ $option->label() }}</option>
                @endforeach
            </select>
        </label>
        <label class="ops-field">
            <span class="ops-field-label">Warranty impact</span>
            <select name="part_warranty_impact" class="ops-field-input">
                @foreach (PartLineWarrantyImpact::cases() as $option)
                    <option value="{{ $option->value }}" @selected($partWarrantyImpact === $option)>{{ $option->label() }}</option>
                @endforeach
            </select>
        </label>
    </div>

    <div x-show="partSource === '{{ PartLineSource::CustomerSupplied->value }}'" x-cloak class="grid gap-2 sm:grid-cols-2">
        <label class="ops-field sm:col-span-2">
            <span class="ops-field-label">Customer part posture</span>
            <select name="customer_part_posture" class="ops-field-input">
                @foreach (CustomerPartPosture::cases() as $option)
                    <option value="{{ $option->value }}" @selected($customerPartPosture === $option)>{{ $option->label() }}</option>
                @endforeach
            </select>
        </label>
        <p class="sm:col-span-2 text-[11px] font-medium leading-4 text-slate-500">
            Customer supplied parts skip shop ordering. Mark whether the part is already in hand or still waiting on the customer.
        </p>
    </div>
</div>
