@php
    $concernDefaultPartPricingMode = $concern->billing_posture->prefersManualPartPricing() ? 'manual' : 'matrix';
    $concernPartsMatrixKey = $concern->billing_posture
        ->defaultPartsMatrix($partstechShopSettings)['key'];
    $concernLaborDefaults = $partstechShopSettings->laborDefaultsForConcern(
        $concern->billing_posture,
        $repairOrder->customer,
    );
    $concernDefaultLaborRate = $concernLaborDefaults['rate'];
    $concernDefaultLaborCategoryKey = $concernLaborDefaults['category_key'];
    $workGroupLaborCount = $workGroup
        ? \App\Ark\Operations\RepairOrders\LaborDescriptionPresentation::laborCountInGroup($workGroup->lines)
        : null;
    $suppressLaborDescription = $workGroup !== null
        && $workGroupLaborCount !== null
        && \App\Ark\Operations\RepairOrders\LaborDescriptionPresentation::shouldSuppressWorksheetDescription(
            $line,
            $workGroup->title,
            (int) $workGroupLaborCount,
        );
    $cancelUrl = route('operations.repair-orders.show', $repairOrder);
@endphp

@if ($line->type->isNote())
    @include('operations.repair-orders.partials.repair-order-note-line-edit', [
        'line' => $line,
        'repairOrder' => $repairOrder,
        'estimateVersion' => $estimateVersion,
        'cancelUrl' => $cancelUrl,
        'privacyInputId' => 'workspace-note-private-edit-'.$line->id,
        'workspaceModalForm' => 'edit-line',
        'hideInlineChrome' => true,
    ])
@elseif ($line->type->isLabor())
    @include('operations.repair-orders.partials.repair-order-labor-line-edit', [
        'line' => $line,
        'repairOrder' => $repairOrder,
        'concern' => $concern,
        'estimateVersion' => $estimateVersion,
        'cancelUrl' => $cancelUrl,
        'totals' => $totals,
        'defaultLaborRate' => $concernDefaultLaborRate,
        'laborCategories' => $laborCategories,
        'defaultLaborCategoryKey' => $concernDefaultLaborCategoryKey,
        'partsMatrices' => $partsMatrices,
        'defaultPartsMatrixKey' => $concernPartsMatrixKey,
        'suppressLaborDescription' => $suppressLaborDescription,
        'workGroupTitle' => $workGroup?->title,
        'workspaceModalForm' => 'edit-line',
        'hideInlineChrome' => true,
    ])
@else
    {{-- Part / sublet / fee — reuse the existing inline editor body inside the modal --}}
    <div id="line-{{ $line->id }}" class="min-h-[70px]">
        <div
            x-data="arkPartPricing({ type: '{{ $line->type->value }}', concernSummary: @js($concern->summary), pricingMode: '{{ $line->pricing_mode ?: $concernDefaultPartPricingMode }}', defaultPricingMode: '{{ $concernDefaultPartPricingMode }}', defaultPartSell: '{{ $concernDefaultPartPricingMode === 'manual' ? '0' : '' }}', matrixKey: '{{ $line->pricing_matrix_key }}', explicitMatrix: true, cost: '{{ $line->part_cost_cents === null ? '' : $totals->decimal($line->part_cost_cents) }}', sell: '{{ $totals->decimal($line->unit_price_cents) }}', sellEdited: {{ $line->is_overridden || $line->pricing_mode === 'manual' ? 'true' : 'false' }}, defaultLaborRate: '{{ $defaultLaborRate }}' }, partsMatrices, @js($concernPartsMatrixKey), '{{ route('operations.repair-orders.lines.pricing-preview', $repairOrder) }}')"
            class="space-y-1.5 text-sm"
        >
            <form
                id="line-update-{{ $line->id }}"
                method="POST"
                action="{{ route('operations.repair-orders.lines.update', [$repairOrder, $line]) }}"
                data-workspace-modal-form="edit-line"
                data-refresh-scope="worksheet"
                data-continuity-focus="#line-{{ $line->id }}"
                @submit.prevent="submitLine($event)"
                class="grid gap-2"
            >
                @csrf
                @method('PATCH')
                <input type="hidden" name="{{ App\Ark\Operations\RepairOrders\RepairOrderConcurrency::FIELD }}" value="{{ $estimateVersion }}">
                <input type="hidden" name="repair_order_concern_id" value="{{ $line->repair_order_concern_id }}">
                <input type="hidden" name="repair_order_work_group_id" value="{{ $line->repair_order_work_group_id }}">
                <input type="hidden" name="type" value="{{ $line->type->value }}">
                <label class="block text-[11px] font-medium text-slate-500">
                    Description
                    <input name="description" value="{{ $line->description }}" required class="mt-1 w-full rounded-sm border border-slate-300 px-3 py-1.5 text-sm">
                </label>
                <div class="grid gap-2 sm:grid-cols-3">
                    <label class="block text-[11px] font-medium text-slate-500" x-show="type === 'part' || type === 'sublet'">
                        Cost
                        <input name="part_cost" x-model="cost" @input="type === 'sublet' ? onSubletCostInput() : onCostInput()" @blur="onCostBlur()" inputmode="decimal" class="mt-1 w-full rounded-sm border border-slate-300 px-3 py-1.5 text-sm">
                    </label>
                    <label class="block text-[11px] font-medium text-slate-500" x-show="type === 'part'">
                        Pricing
                        <select x-model="pricingSelection" @change="onPricingSelectionChange($event)" class="mt-1 w-full rounded-sm border border-slate-300 px-2 py-1.5 text-sm">
                            @foreach ($partsMatrices as $matrix)
                                <option value="{{ $matrix['key'] }}">{{ $matrix['name'] }}</option>
                            @endforeach
                            <option value="manual">Manual Price</option>
                        </select>
                    </label>
                    <label class="block text-[11px] font-medium text-slate-500">
                        Sell
                        <input name="unit_price" x-model="sell" @input="onSellInput()" :required="type !== 'part' || pricingMode === 'manual'" inputmode="decimal" class="mt-1 w-full rounded-sm border border-slate-300 px-3 py-1.5 text-sm">
                    </label>
                    <label class="block text-[11px] font-medium text-slate-500">
                        Qty
                        <input name="quantity" value="{{ $line->quantity }}" required inputmode="decimal" class="mt-1 w-full rounded-sm border border-slate-300 px-3 py-1.5 text-sm">
                    </label>
                </div>
                <input type="hidden" name="pricing_mode" :value="pricingMode">
                <input type="hidden" name="pricing_matrix_key" :value="pricingMode === 'matrix' ? matrixKey : ''">
                <input type="hidden" name="pricing_matrix_explicit" :value="explicitMatrix ? '1' : '0'">
                <input type="hidden" name="unit_price_override" :value="sellEdited ? '1' : '0'">
                <div class="grid gap-2 sm:grid-cols-2" x-show="type === 'part'">
                    <input name="vendor_name" value="{{ $line->vendor_name }}" placeholder="Vendor" class="rounded-sm border border-slate-300 px-3 py-1.5 text-sm">
                    <input name="part_number" value="{{ $line->part_number }}" placeholder="Part #" class="rounded-sm border border-slate-300 px-3 py-1.5 text-sm">
                    <input name="sourcing_notes" value="{{ $line->sourcing_notes }}" placeholder="Sourcing notes" class="rounded-sm border border-slate-300 px-3 py-1.5 text-sm sm:col-span-2">
                </div>
            </form>
        </div>
    </div>
@endif

@can(App\Ark\Runtime\Authorization\ArkCapability::RepairOrdersDestructive->value)
    @unless ($isTerminal ?? false)
        <form
            method="POST"
            action="{{ route('operations.repair-orders.lines.destroy', [$repairOrder, $line]) }}"
            data-workspace-modal-delete-line
            data-refresh-scope="worksheet"
            data-continuity-focus="#estimate-lines"
            data-saving-label="Removing…"
            class="sr-only"
            tabindex="-1"
            aria-hidden="true"
        >
            @csrf
            @method('DELETE')
            <input type="hidden" name="{{ App\Ark\Operations\RepairOrders\RepairOrderConcurrency::FIELD }}" value="{{ $estimateVersion }}">
        </form>
    @endunless
@endcan
