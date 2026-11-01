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
    <x-operations.worksheet-island>
        @include('operations.repair-orders.partials.repair-order-note-line-edit', [
            'line' => $line,
            'repairOrder' => $repairOrder,
            'estimateVersion' => $estimateVersion,
            'cancelUrl' => $cancelUrl,
            'privacyInputId' => 'workspace-note-private-edit-'.$line->id,
            'workspaceModalForm' => 'edit-line',
            'hideInlineChrome' => true,
        ])
    </x-operations.worksheet-island>
@elseif ($line->type->isLabor())
    <x-operations.worksheet-island>
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
    </x-operations.worksheet-island>
@else
    {{-- Part / sublet / fee - reuse the existing inline editor body inside the modal --}}
    <div id="line-editor-{{ $line->id }}">
        <div
            x-data="arkPartPricing({ type: '{{ $line->type->value }}', concernSummary: @js($concern->summary), pricingMode: '{{ $line->pricing_mode ?: $concernDefaultPartPricingMode }}', defaultPricingMode: '{{ $concernDefaultPartPricingMode }}', defaultPartSell: '{{ $concernDefaultPartPricingMode === 'manual' ? '0' : '' }}', matrixKey: '{{ $line->pricing_matrix_key }}', explicitMatrix: true, cost: '{{ $line->part_cost_cents === null ? '' : $totals->decimal($line->part_cost_cents) }}', sell: '{{ $totals->decimal($line->unit_price_cents) }}', sellEdited: {{ $line->is_overridden || $line->pricing_mode === 'manual' ? 'true' : 'false' }}, defaultLaborRate: '{{ $defaultLaborRate }}' }, partsMatrices, @js($concernPartsMatrixKey), '{{ route('operations.repair-orders.lines.pricing-preview', $repairOrder) }}')"
        >
            <form
                id="line-update-{{ $line->id }}"
                method="POST"
                action="{{ route('operations.repair-orders.lines.update', [$repairOrder, $line]) }}"
                data-workspace-modal-form="edit-line"
                data-worksheet-continuity
                data-refresh-scope="worksheet"
                data-continuity-focus="#line-{{ $line->id }}"
                @submit.prevent="submitLine($event)"
            >
                @csrf
                @method('PATCH')
                <input type="hidden" name="{{ App\Ark\Operations\RepairOrders\RepairOrderConcurrency::FIELD }}" value="{{ $estimateVersion }}">
                <input type="hidden" name="repair_order_concern_id" value="{{ $line->repair_order_concern_id }}">
                <input type="hidden" name="repair_order_work_group_id" value="{{ $line->repair_order_work_group_id }}">
                <input type="hidden" name="type" value="{{ $line->type->value }}">
                <div class="row g-3">
                    <div class="col-12">
                        <label class="form-label" for="workspace-line-edit-description-{{ $line->id }}">Part description</label>
                        <input id="workspace-line-edit-description-{{ $line->id }}" name="description" value="{{ $line->description }}" required class="form-control">
                        <div class="form-hint">Internal / ordering identity</div>
                    </div>
                    @if ($line->type->isPart() && \App\Ark\Operations\Settings\ShopSettings::current()->customerPartAllowDescriptionOverride())
                        <div class="col-12">
                            <label class="form-label" for="workspace-line-edit-customer-description-{{ $line->id }}">Customer description</label>
                            <input id="workspace-line-edit-customer-description-{{ $line->id }}" name="customer_description" value="{{ $line->customer_description }}" placeholder="Clean label for estimate / PDF" class="form-control">
                            <div class="form-hint">Leave blank to regenerate from part description</div>
                        </div>
                    @endif
                    <div class="col-12 col-sm-4" x-show="type === 'part' || type === 'sublet'">
                        <label class="form-label" for="workspace-line-edit-cost-{{ $line->id }}">Cost</label>
                        <input id="workspace-line-edit-cost-{{ $line->id }}" name="part_cost" x-model="cost" @input="type === 'sublet' ? onSubletCostInput() : onCostInput()" @blur="onCostBlur()" inputmode="decimal" class="form-control">
                    </div>
                    <div class="col-12 col-sm-4" x-show="type === 'part'">
                        <label class="form-label" for="workspace-line-edit-pricing-{{ $line->id }}">Pricing</label>
                        <select id="workspace-line-edit-pricing-{{ $line->id }}" x-model="pricingSelection" @change="onPricingSelectionChange($event)" class="form-select">
                            @foreach ($partsMatrices as $matrix)
                                <option value="{{ $matrix['key'] }}">{{ $matrix['name'] }}</option>
                            @endforeach
                            <option value="manual">Manual Price</option>
                        </select>
                    </div>
                    <div class="col-6 col-sm-4">
                        <label class="form-label" for="workspace-line-edit-sell-{{ $line->id }}">Sell</label>
                        <input id="workspace-line-edit-sell-{{ $line->id }}" name="unit_price" x-model="sell" @input="onSellInput()" :required="type !== 'part' || pricingMode === 'manual'" inputmode="decimal" class="form-control">
                    </div>
                    <div class="col-6 col-sm-4">
                        <label class="form-label" for="workspace-line-edit-quantity-{{ $line->id }}">Qty</label>
                        <input id="workspace-line-edit-quantity-{{ $line->id }}" name="quantity" value="{{ $line->quantity }}" required inputmode="decimal" class="form-control">
                    </div>
                </div>
                <input type="hidden" name="pricing_mode" :value="pricingMode">
                <input type="hidden" name="pricing_matrix_key" :value="pricingMode === 'matrix' ? matrixKey : ''">
                <input type="hidden" name="pricing_matrix_explicit" :value="explicitMatrix ? '1' : '0'">
                <input type="hidden" name="unit_price_override" :value="sellEdited ? '1' : '0'">
                <div x-show="type === 'part'">
                    <div class="row g-3 mt-0">
                        <div class="col-12 col-sm-6">
                            <input name="vendor_name" value="{{ $line->vendor_name }}" placeholder="Vendor" aria-label="Vendor" class="form-control">
                        </div>
                        <div class="col-12 col-sm-6">
                            <input name="part_number" value="{{ $line->part_number }}" placeholder="Part #" aria-label="Part number" class="form-control">
                        </div>
                        <div class="col-12">
                            <input name="sourcing_notes" value="{{ $line->sourcing_notes }}" placeholder="Sourcing notes" aria-label="Sourcing notes" class="form-control">
                        </div>
                    </div>
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
            data-worksheet-continuity
            data-refresh-scope="worksheet"
            data-continuity-focus="#estimate-lines"
            data-saving-label="Removing…"
            class="visually-hidden"
            tabindex="-1"
            aria-hidden="true"
        >
            @csrf
            @method('DELETE')
            <input type="hidden" name="{{ App\Ark\Operations\RepairOrders\RepairOrderConcurrency::FIELD }}" value="{{ $estimateVersion }}">
        </form>
    @endunless
@endcan
