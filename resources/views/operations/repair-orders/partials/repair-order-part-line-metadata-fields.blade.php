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

<div x-data="{ partSource: @js($partSource->value) }" class="d-flex flex-column gap-2">
    <div class="row g-2">
        <label class="col-sm-4">
            <span class="form-label">Part source</span>
            <select name="part_source" x-model="partSource" class="form-select">
                @foreach (PartLineSource::cases() as $option)
                    <option value="{{ $option->value }}" @selected($partSource === $option)>{{ $option->label() }}</option>
                @endforeach
            </select>
        </label>
        <label class="col-sm-4">
            <span class="form-label">Part type</span>
            <select name="part_classification" class="form-select">
                <option value="" @selected($partClassification === null)>Not set</option>
                @foreach (PartLineClassification::cases() as $option)
                    <option value="{{ $option->value }}" @selected($partClassification === $option)>{{ $option->label() }}</option>
                @endforeach
            </select>
        </label>
        <label class="col-sm-4">
            <span class="form-label">Warranty impact</span>
            <select name="part_warranty_impact" class="form-select">
                @foreach (PartLineWarrantyImpact::cases() as $option)
                    <option value="{{ $option->value }}" @selected($partWarrantyImpact === $option)>{{ $option->label() }}</option>
                @endforeach
            </select>
        </label>
    </div>

    <div x-show="partSource === '{{ PartLineSource::CustomerSupplied->value }}'" x-cloak>
        <label class="d-block">
            <span class="form-label">Customer part posture</span>
            <select name="customer_part_posture" class="form-select">
                @foreach (CustomerPartPosture::cases() as $option)
                    <option value="{{ $option->value }}" @selected($customerPartPosture === $option)>{{ $option->label() }}</option>
                @endforeach
            </select>
        </label>
        <p class="form-hint mt-1 mb-0">
            Customer supplied parts skip shop ordering. Mark whether the part is already in hand or still waiting on the customer.
        </p>
    </div>
</div>
