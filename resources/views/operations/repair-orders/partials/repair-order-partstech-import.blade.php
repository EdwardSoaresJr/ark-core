@php
    $canImportQuote = $canImportQuote ?? false;
    $singleConcernForImport = ($repairOrder->concerns->count() === 1)
        ? $repairOrder->concerns->first()
        : null;
    $partstechShopSettings = App\Ark\Operations\Settings\ShopSettings::current();
    $partstechImportConfig = [
        'previewUrl' => route('operations.repair-orders.partstech.import.preview', $repairOrder),
        'importUrl' => route('operations.repair-orders.partstech.import', $repairOrder),
        'pricingPreviewUrl' => route('operations.repair-orders.lines.pricing-preview', $repairOrder),
        'csrfToken' => csrf_token(),
        'poNumber' => $partstechPoNumber,
        'defaultConcernId' => $singleConcernForImport?->id,
        'defaultPartsMatrixKey' => $partstechShopSettings->defaultPartsMatrix()['key'],
        'initialPartsMatrices' => collect($partstechShopSettings->partsMatrices())
            ->map(fn (array $matrix): array => [
                'key' => $matrix['key'],
                'name' => $matrix['name'],
                'is_default' => (bool) ($matrix['is_default'] ?? false),
            ])
            ->values()
            ->all(),
        'initialConcerns' => $repairOrder->concerns->sortBy('position')->values()->map(function ($concern) use ($partstechShopSettings): array {
            $matrix = $concern->defaultPartsMatrix($partstechShopSettings);

            return [
                'id' => $concern->id,
                'summary' => $concern->summary,
                'default_parts_matrix_key' => $matrix['key'],
                'default_parts_matrix_name' => $matrix['name'],
                'work_groups' => $concern->workGroups
                    ->map(fn ($workGroup): array => [
                        'id' => $workGroup->id,
                        'title' => $workGroup->title,
                        'has_labor_anchor' => $workGroup->hasPartsAttachAnchor(),
                    ])
                    ->values()
                    ->all(),
            ];
        })->all(),
    ];
@endphp

@if ($canImportQuote)
    <div
        id="partstech-quote-import"
        class="card mb-3"
        data-partstech-quote-import
        x-data="arkPartsTechQuoteImport(@js($partstechImportConfig))"
        @ark:partstech-pull-quote.window="loadQuote(false, $event.detail?.concernId)"
        x-show="loaded || error || loading"
        x-cloak
    >
        <div x-show="loading" x-cloak class="card-body" role="status" aria-live="polite">
            <div class="d-flex align-items-center gap-3">
                <span class="spinner-border text-secondary flex-shrink-0" aria-hidden="true"></span>
                <div>
                    <div class="fw-bold" x-text="loadingStatus"></div>
                    <div class="small text-secondary">ARK is syncing the PartsTech cart and pricing parts. This may take a few seconds.</div>
                </div>
            </div>
        </div>

        <div x-show="loaded && ! loading" class="card-header">
            <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 w-100">
                <div class="small text-secondary">
                    <span x-text="rows.length + ' part' + (rows.length === 1 ? '' : 's') + ' ready to import'"></span>
                    <span x-show="hasSingleScope"> · default scope <strong x-text="singleScopeLabel()"></strong> (change per line if needed)</span>
                    <span x-show="! hasSingleScope"> · assign scope and repair per line</span>
                    <span> · matrix shows the sell matrix that will be used (change per part if needed)</span>
                </div>
                <button
                    type="button"
                    class="btn btn-sm"
                    :disabled="loading"
                    @click="loadQuote()"
                >
                    <span x-text="loading ? 'Pulling…' : 'Pull again'"></span>
                </button>
            </div>
        </div>

        <div x-show="error && ! loading" x-cloak class="card-body">
            <div class="alert alert-danger mb-0" role="alert">
                <div x-text="error"></div>
                <button
                    x-show="cartLocked"
                    x-cloak
                    type="button"
                    class="btn btn-sm btn-danger mt-2"
                    @click="loadQuote(true)"
                >
                    Switch PartsTech to this RO and pull
                </button>
            </div>
        </div>

        <div x-show="loaded && ! loading" x-cloak>
            <form method="POST" :action="importUrl" @submit="beforeSubmit($event)">
                @csrf
                <input type="hidden" name="{{ App\Ark\Operations\RepairOrders\RepairOrderConcurrency::FIELD }}" value="{{ $estimateVersion }}">
                <template x-for="(row, index) in rows" :key="row.source_key">
                    <div>
                        <input type="hidden" :name="'assignments[' + index + '][source_key]'" :value="row.source_key">
                        <input
                            type="hidden"
                            :name="'assignments[' + index + '][repair_order_concern_id]'"
                            :value="row.selected && row.concern_id ? row.concern_id : ''"
                        >
                        <input
                            type="hidden"
                            :name="'assignments[' + index + '][repair_order_work_group_id]'"
                            :value="row.selected && row.work_group_id ? row.work_group_id : ''"
                        >
                        <template x-if="row.selected && row.concern_id">
                            <div>
                                <input type="hidden" :name="'assignments[' + index + '][part_cost]'" :value="row.part_cost">
                                <input
                                    type="hidden"
                                    :name="'assignments[' + index + '][pricing_matrix_key]'"
                                    :value="row.pricing_matrix_key"
                                >
                            </div>
                        </template>
                    </div>
                </template>

                <div class="table-responsive">
                    <table class="table table-sm table-vcenter card-table mb-0">
                        <thead>
                            <tr>
                                <th class="w-1"><span class="visually-hidden">Import</span></th>
                                <th style="min-width: 12rem">Part</th>
                                <th>Position</th>
                                <th class="text-end">Qty</th>
                                <th class="text-end">Cost</th>
                                <th style="min-width: 9rem">Matrix</th>
                                <th class="text-end">Sell</th>
                                <th>Vendor</th>
                                <th style="min-width: 10rem">Scope</th>
                                <th style="min-width: 10rem">Repair action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <template x-for="row in rows" :key="row.source_key">
                                <tr>
                                    <td>
                                        <input type="checkbox" class="form-check-input" x-model="row.selected" :aria-label="'Import ' + (row.description || 'part')">
                                    </td>
                                    <td>
                                        <div class="fw-medium" x-text="row.description"></div>
                                        <div class="small text-secondary" x-show="row.part_number" x-text="row.part_number"></div>
                                        <div class="small text-secondary" x-show="row.guidance" x-text="row.guidance"></div>
                                    </td>
                                    <td>
                                        <span
                                            x-show="row.position_label"
                                            class="badge bg-azure-lt"
                                            x-text="row.position_label"
                                        ></span>
                                        <span x-show="! row.position_label" class="small text-secondary">-</span>
                                    </td>
                                    <td class="text-end ark-money" x-text="row.quantity"></td>
                                    <td>
                                        <input
                                            type="text"
                                            x-model="row.part_cost"
                                            @input="onRowCostInput(row)"
                                            inputmode="decimal"
                                            aria-label="Cost"
                                            class="form-control form-control-sm text-end ark-money"
                                            style="width: 5rem"
                                            :disabled="! row.selected"
                                        >
                                    </td>
                                    <td>
                                        <select
                                            x-model="row.pricing_matrix_key"
                                            @change="onRowMatrixChange(row)"
                                            aria-label="Matrix"
                                            class="form-select form-select-sm"
                                            style="max-width: 9rem"
                                            :disabled="! row.selected || ! row.concern_id"
                                        >
                                            <template x-for="matrix in partsMatrices" :key="row.source_key + '-m-' + matrix.key">
                                                <option
                                                    :value="matrix.key"
                                                    :selected="matrix.key === row.pricing_matrix_key"
                                                    x-text="matrixShortName(matrix)"
                                                ></option>
                                            </template>
                                        </select>
                                    </td>
                                    <td class="text-end">
                                        <span
                                            class="fw-medium ark-money"
                                            x-text="row.sell || (row.previewing ? '…' : '-')"
                                        ></span>
                                        <div class="small text-secondary ark-money" x-show="row.margin_percentage">
                                            <span x-text="row.margin_percentage"></span>% margin
                                            <span x-show="row.previewing"> · pricing…</span>
                                        </div>
                                    </td>
                                    <td class="text-secondary" x-text="row.vendor_name || '-'"></td>
                                    <td>
                                        <select
                                            x-model="row.concern_id"
                                            @change="onRowConcernChange(row)"
                                            aria-label="Scope"
                                            class="form-select form-select-sm"
                                            :disabled="! row.selected"
                                        >
                                            <option value="">Choose scope…</option>
                                            <template x-for="concern in concerns" :key="concern.id">
                                                <option :value="String(concern.id)" x-text="concern.summary"></option>
                                            </template>
                                        </select>
                                    </td>
                                    <td>
                                        <select
                                            x-model="row.work_group_id"
                                            @change="onRowWorkGroupChange(row)"
                                            aria-label="Repair action"
                                            class="form-select form-select-sm"
                                            :disabled="! row.selected || workGroupsForConcern(row.concern_id).length === 0"
                                        >
                                            <option value="">Ungrouped in scope</option>
                                            <template x-for="workGroup in workGroupsForConcern(row.concern_id)" :key="workGroup.id">
                                                <option :value="String(workGroup.id)" x-text="workGroup.title"></option>
                                            </template>
                                        </select>
                                        <div
                                            class="small text-secondary mt-1"
                                            x-show="row.selected && row.concern_id && workGroupsForConcern(row.concern_id).length === 0"
                                        >
                                            Add a repair with labor on this scope first.
                                        </div>
                                    </td>
                                </tr>
                            </template>
                        </tbody>
                    </table>
                </div>

                <div class="card-footer d-flex justify-content-end">
                    <button
                        id="partstech-import-submit-btn"
                        type="submit"
                        class="btn btn-primary"
                        :disabled="importing || selectedCount() === 0"
                    >
                        <span x-text="importing ? 'Importing…' : 'Import selected parts'"></span>
                    </button>
                </div>
            </form>
        </div>
    </div>
@endif
