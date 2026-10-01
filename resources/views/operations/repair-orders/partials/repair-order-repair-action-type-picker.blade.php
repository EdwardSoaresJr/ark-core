@php
    $activeType = $activeType ?? '';
    $allowedTypes = collect($allowedTypes ?? App\Ark\Operations\RepairOrders\RepairOrderLineType::cases())
        ->map(fn ($type) => $type instanceof App\Ark\Operations\RepairOrders\RepairOrderLineType ? $type : App\Ark\Operations\RepairOrders\RepairOrderLineType::from($type));
@endphp

<div class="ops-line-type-picker" role="group" aria-label="Add supporting line">
    <input type="hidden" name="type" :value="type">
    <div class="ops-line-type-picker__buttons">
        @foreach ($allowedTypes as $allowedType)
            @php
                $buttonClass = match ($allowedType) {
                    App\Ark\Operations\RepairOrders\RepairOrderLineType::Labor => 'ops-line-type-btn--labor',
                    App\Ark\Operations\RepairOrders\RepairOrderLineType::Part => 'ops-line-type-btn--part',
                    App\Ark\Operations\RepairOrders\RepairOrderLineType::Note => 'ops-line-type-btn--note',
                    App\Ark\Operations\RepairOrders\RepairOrderLineType::Fee => 'ops-line-type-btn--fee',
                    App\Ark\Operations\RepairOrders\RepairOrderLineType::Sublet => 'ops-line-type-btn--sublet',
                    App\Ark\Operations\RepairOrders\RepairOrderLineType::Package => 'ops-line-type-btn--fee',
                };
                $buttonLabel = match ($allowedType) {
                    App\Ark\Operations\RepairOrders\RepairOrderLineType::Labor => '+ Labor',
                    App\Ark\Operations\RepairOrders\RepairOrderLineType::Part => '+ Supporting Part',
                    App\Ark\Operations\RepairOrders\RepairOrderLineType::Note => isset($laborAnchored) && $laborAnchored ? '+ Supporting Note' : '+ Note',
                    App\Ark\Operations\RepairOrders\RepairOrderLineType::Fee => '+ Fee',
                    App\Ark\Operations\RepairOrders\RepairOrderLineType::Sublet => isset($laborAnchored) && $laborAnchored ? '+ Supporting Sublet' : '+ Sublet',
                };
            @endphp
            <button
                type="button"
                class="ops-line-type-btn {{ $buttonClass }}"
                :class="type === '{{ $allowedType->value }}' ? 'ops-line-type-btn--active' : ''"
                @click="selectLineType('{{ $allowedType->value }}')"
            >
                {{ $buttonLabel }}
            </button>
        @endforeach
    </div>
</div>
