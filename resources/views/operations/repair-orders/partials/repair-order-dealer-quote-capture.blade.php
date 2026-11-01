@php
    $singleConcernForCapture = ($repairOrder->concerns->count() === 1)
        ? $repairOrder->concerns->first()
        : null;
    $dealerQuoteShopSettings = App\Ark\Operations\Settings\ShopSettings::current();
    $dealerQuoteCaptureConfig = [
        'analyzeUrl' => route('operations.repair-orders.dealer-quotes.analyze', $repairOrder),
        'importUrl' => route('operations.repair-orders.dealer-quotes.store', $repairOrder),
        'pricingPreviewUrl' => route('operations.repair-orders.lines.pricing-preview', $repairOrder),
        'csrfToken' => csrf_token(),
        'defaultConcernId' => $singleConcernForCapture?->id,
        'initialPartsMatrices' => collect($dealerQuoteShopSettings->partsMatrices())
            ->map(fn (array $matrix): array => [
                'key' => $matrix['key'],
                'name' => $matrix['name'],
            ])
            ->values()
            ->all(),
        'initialConcerns' => $repairOrder->concerns->sortBy('position')->values()->map(function ($concern) use ($dealerQuoteShopSettings): array {
            $matrix = $concern->defaultPartsMatrix($dealerQuoteShopSettings);

            return [
                'id' => $concern->id,
                'summary' => $concern->summary,
                'default_parts_matrix_key' => $matrix['key'],
                'default_parts_matrix_name' => $matrix['name'],
            ];
        })->all(),
    ];
@endphp

<div
    id="dealer-quote-capture"
    x-data="arkDealerQuoteCapture(@js($dealerQuoteCaptureConfig))"
    x-cloak
>
    <div x-show="open" x-cloak data-dealer-quote-dialog @keydown.escape.window="closeCapture()">
        <div class="modal-backdrop show"></div>
        <div
            class="modal d-block"
            role="dialog"
            aria-modal="true"
            aria-labelledby="dealer-quote-capture-title"
            @click.self="closeCapture()"
        >
            <div class="modal-dialog modal-xl modal-dialog-scrollable modal-fullscreen-lg-down" @click.self="closeCapture()">
                <div class="modal-content">
                    <div class="modal-header">
                        <div class="overflow-hidden">
                            <h2 id="dealer-quote-capture-title" class="modal-title">Import Dealer Quote</h2>
                            <div class="small text-secondary">PDF or pasted text → review → add parts to this estimate.</div>
                        </div>
                        <button
                            type="button"
                            class="btn-close"
                            aria-label="Close"
                            @click="closeCapture()"
                            :disabled="analyzing || importing"
                        ></button>
                    </div>

                    <div class="modal-body">
                        <div x-show="error" class="alert alert-danger" role="alert" x-text="error"></div>

                        <div x-show="step === 'upload'">
                            <div class="mb-3">
                                <label class="form-label" for="dealer-quote-file">Upload PDF or photo</label>
                                <input
                                    id="dealer-quote-file"
                                    type="file"
                                    accept=".pdf,application/pdf,image/png,image/jpeg,image/webp,.png,.jpg,.jpeg,.webp,.txt,text/plain"
                                    class="form-control"
                                    @change="onFileChange($event)"
                                >
                                <div x-show="quoteFileName" class="small text-secondary mt-1" x-text="quoteFileName"></div>
                                <div class="form-hint">Scanned dealer quotes are read with OCR automatically.</div>
                            </div>

                            <div class="hr-text">or</div>

                            <div class="row g-3">
                                <div class="col-12 col-lg-6">
                                    <label class="form-label" for="dealer-quote-text">Paste copied text</label>
                                    <textarea
                                        id="dealer-quote-text"
                                        x-model="quoteText"
                                        rows="12"
                                        class="form-control font-monospace small"
                                        placeholder="1  06J-103-603-BD  Upper Oil Sump  406.08"
                                    ></textarea>
                                </div>

                                <div class="col-12 col-lg-6">
                                    <div class="form-label">Parts preview</div>
                                    <div class="border rounded p-2 small overflow-auto" style="height: 17.5rem">
                                        <template x-if="uploadPreviewLines().length === 0">
                                            <p class="text-secondary mb-0">Paste a quote to see part lines here · Analyze to confirm.</p>
                                        </template>
                                        <div x-show="uploadPreviewLines().length > 0" class="list-group list-group-flush">
                                            <template x-for="(preview, previewIndex) in uploadPreviewLines()" :key="'preview-' + previewIndex">
                                                <div class="list-group-item d-flex gap-2 px-1">
                                                    <span class="text-secondary ark-money flex-shrink-0" style="width: 1.5rem" x-text="preview.quantity"></span>
                                                    <span class="flex-fill" style="min-width: 0">
                                                        <span class="d-block font-monospace" x-text="preview.part_number || '-'"></span>
                                                        <span class="d-block text-secondary" x-text="preview.description || 'Part line'"></span>
                                                    </span>
                                                    <span class="flex-shrink-0 ark-money" x-text="preview.part_cost ? ('$' + preview.part_cost) : ''"></span>
                                                </div>
                                            </template>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="btn-list justify-content-end mt-3">
                                <button type="button" class="btn" @click="closeCapture()">Cancel</button>
                                <button
                                    type="button"
                                    class="btn btn-primary"
                                    :disabled="analyzing || (! quoteFile && ! quoteText.trim())"
                                    @click="analyzeQuote()"
                                >
                                    <span x-text="analyzing ? 'Reading quote…' : 'Analyze Quote'"></span>
                                </button>
                            </div>
                        </div>

                        <template x-if="step === 'review' && capture">
                        <div>
                            <div class="datagrid mb-3">
                                <div class="datagrid-item">
                                    <div class="datagrid-title">Supplier</div>
                                    <div class="datagrid-content" x-text="capture.supplier_name || '-'"></div>
                                </div>
                                <div class="datagrid-item">
                                    <div class="datagrid-title">Quote #</div>
                                    <div class="datagrid-content" x-text="capture.quote_number || '-'"></div>
                                </div>
                                <div class="datagrid-item">
                                    <div class="datagrid-title">Vehicle</div>
                                    <div class="datagrid-content" x-text="capture.vehicle_description || '-'"></div>
                                </div>
                                <div class="datagrid-item">
                                    <div class="datagrid-title">VIN</div>
                                    <div class="datagrid-content text-break" x-text="capture.vin || '-'"></div>
                                </div>
                            </div>

                            <form method="POST" :action="importUrl" @submit="beforeSubmit($event)" data-dealer-quote-import-form>
                                @csrf
                                <input type="hidden" name="{{ App\Ark\Operations\RepairOrders\RepairOrderConcurrency::FIELD }}" value="{{ $estimateVersion }}">
                                <input type="hidden" name="capture[supplier_name]" :value="capture.supplier_name || ''">
                                <input type="hidden" name="capture[quote_number]" :value="capture.quote_number || ''">
                                <input type="hidden" name="capture[vehicle_description]" :value="capture.vehicle_description || ''">
                                <input type="hidden" name="capture[vin]" :value="capture.vin || ''">
                                <input type="hidden" name="capture[dealer_total_cents]" :value="capture.dealer_total_cents || ''">
                                <input type="hidden" name="capture[raw_text]" :value="capture.raw_text || ''">
                                <input type="hidden" name="capture[original_filename]" :value="capture.original_filename || ''">
                                <input type="hidden" name="capture[temp_storage_path]" :value="capture.temp_storage_path || ''">

                                <template x-for="(line, index) in capture.lines" :key="line.source_key">
                                    <div>
                                        <input type="hidden" :name="'capture[lines][' + index + '][source_key]'" :value="line.source_key">
                                        <input type="hidden" :name="'capture[lines][' + index + '][quantity]'" :value="line.quantity">
                                        <input type="hidden" :name="'capture[lines][' + index + '][part_number]'" :value="line.part_number || ''">
                                        <input type="hidden" :name="'capture[lines][' + index + '][description]'" :value="line.description">
                                        <input type="hidden" :name="'capture[lines][' + index + '][part_cost]'" :value="line.part_cost">
                                        <input type="hidden" :name="'capture[lines][' + index + '][unit_cost_cents]'" :value="line.unit_cost_cents || ''">
                                        <input type="hidden" :name="'capture[lines][' + index + '][extended_cost_cents]'" :value="line.extended_cost_cents || ''">
                                    </div>
                                </template>

                                <template x-for="(row, index) in rows" :key="'assign-' + row.source_key">
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
                                                <input type="hidden" :name="'assignments[' + index + '][description]'" :value="row.description">
                                                <input
                                                    type="hidden"
                                                    :name="'assignments[' + index + '][pricing_matrix_key]'"
                                                    :value="row.pricing_matrix_explicit && row.pricing_matrix_key ? row.pricing_matrix_key : ''"
                                                >
                                            </div>
                                        </template>
                                    </div>
                                </template>

                                <details class="card card-sm mb-3" open>
                                    <summary class="card-body py-2 subheader">
                                        Quote text · <span x-text="rows.length"></span> parts
                                    </summary>
                                    <pre
                                        class="card-body border-top mb-0 small overflow-auto"
                                        style="max-height: 9rem; white-space: pre-wrap"
                                        x-text="capture.raw_text || quoteText || '-'"
                                    ></pre>
                                </details>

                                <div class="row g-2 align-items-end mb-3" data-dealer-quote-bulk>
                                    <div class="col-12 col-sm">
                                        <label class="form-label" for="dealer-quote-bulk-concern">Scope</label>
                                        <select
                                            id="dealer-quote-bulk-concern"
                                            x-model="bulk.concern_id"
                                            @change="onBulkConcernChange()"
                                            class="form-select form-select-sm"
                                        >
                                            <option value="">Choose scope</option>
                                            <template x-for="concern in concerns" :key="'bulk-c-' + concern.id">
                                                <option :value="concern.id" x-text="concern.summary"></option>
                                            </template>
                                        </select>
                                    </div>
                                    <div class="col-6 col-sm-auto">
                                        <label class="form-label" for="dealer-quote-bulk-work-group">Repair action</label>
                                        <select
                                            id="dealer-quote-bulk-work-group"
                                            x-model="bulk.work_group_id"
                                            class="form-select form-select-sm"
                                            :disabled="workGroupsForConcern(bulk.concern_id).length === 0"
                                        >
                                            <option value="">Scope</option>
                                            <template x-for="workGroup in workGroupsForConcern(bulk.concern_id)" :key="'bulk-wg-' + workGroup.id">
                                                <option :value="workGroup.id" x-text="workGroup.title"></option>
                                            </template>
                                        </select>
                                    </div>
                                    <div class="col-6 col-sm-auto">
                                        <label class="form-label" for="dealer-quote-bulk-matrix">Matrix</label>
                                        <select
                                            id="dealer-quote-bulk-matrix"
                                            x-model="bulk.pricing_matrix_key"
                                            class="form-select form-select-sm"
                                        >
                                            <option value="">Default</option>
                                            <template x-for="matrix in partsMatrices" :key="'bulk-m-' + matrix.key">
                                                <option :value="matrix.key" x-text="matrixShortName(matrix)"></option>
                                            </template>
                                        </select>
                                    </div>
                                    <div class="col-12 col-sm-auto">
                                        <button
                                            type="button"
                                            class="btn btn-sm w-100"
                                            @click="applyToSelected()"
                                            :disabled="importing || selectedCount() === 0"
                                        >
                                            Apply to selected
                                        </button>
                                    </div>
                                </div>

                                <div class="table-responsive border rounded">
                                    <table class="table table-sm table-vcenter card-table mb-0">
                                        <thead>
                                            <tr>
                                                <th class="w-1">
                                                    <input
                                                        type="checkbox"
                                                        class="form-check-input"
                                                        aria-label="Select all parts"
                                                        x-model="allSelected"
                                                    >
                                                </th>
                                                <th class="text-end">Qty</th>
                                                <th>Part #</th>
                                                <th style="min-width: 12rem">Name</th>
                                                <th class="text-end">Cost</th>
                                                <th class="text-end">Sell</th>
                                                <th style="width: 5.5rem">Matrix</th>
                                                <th style="min-width: 8rem">Scope</th>
                                                <th style="width: 7.5rem">Action</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <template x-for="row in rows" :key="row.source_key">
                                                <tr :class="row.selected ? '' : 'opacity-50'">
                                                    <td>
                                                        <input type="checkbox" x-model="row.selected" class="form-check-input" :aria-label="'Add ' + (row.description || 'part')">
                                                    </td>
                                                    <td class="text-end ark-money" x-text="row.quantity"></td>
                                                    <td class="font-monospace small" x-text="row.part_number || '-'"></td>
                                                    <td>
                                                        <input
                                                            type="text"
                                                            x-model="row.description"
                                                            aria-label="Part name"
                                                            class="form-control form-control-sm"
                                                            style="min-width: 11rem"
                                                        >
                                                    </td>
                                                    <td class="text-end">
                                                        <input
                                                            type="text"
                                                            inputmode="decimal"
                                                            x-model="row.part_cost"
                                                            @input="onRowCostInput(row)"
                                                            aria-label="Cost"
                                                            class="form-control form-control-sm text-end ark-money"
                                                            style="width: 5rem"
                                                        >
                                                    </td>
                                                    <td class="text-end ark-money" x-text="row.sell || '-'"></td>
                                                    <td>
                                                        <select
                                                            x-model="row.pricing_matrix_key"
                                                            @change="onRowMatrixChange(row)"
                                                            aria-label="Matrix"
                                                            class="form-select form-select-sm"
                                                            style="max-width: 5.5rem"
                                                        >
                                                            <template x-for="matrix in partsMatrices" :key="row.source_key + '-m-' + matrix.key">
                                                                <option :value="matrix.key" x-text="matrixShortName(matrix)"></option>
                                                            </template>
                                                        </select>
                                                    </td>
                                                    <td>
                                                        <select
                                                            x-model="row.concern_id"
                                                            @change="onRowConcernChange(row)"
                                                            aria-label="Scope"
                                                            class="form-select form-select-sm"
                                                        >
                                                            <option value="">Choose scope</option>
                                                            <template x-for="concern in concerns" :key="row.source_key + '-c-' + concern.id">
                                                                <option :value="concern.id" x-text="concern.summary"></option>
                                                            </template>
                                                        </select>
                                                    </td>
                                                    <td>
                                                        <select
                                                            x-model="row.work_group_id"
                                                            @change="onRowWorkGroupChange(row)"
                                                            aria-label="Repair action"
                                                            class="form-select form-select-sm"
                                                            style="max-width: 7.5rem"
                                                            :disabled="workGroupsForConcern(row.concern_id).length === 0"
                                                        >
                                                            <option value="">Scope</option>
                                                            <template x-for="workGroup in workGroupsForConcern(row.concern_id)" :key="row.source_key + '-wg-' + workGroup.id">
                                                                <option :value="workGroup.id" x-text="workGroup.title"></option>
                                                            </template>
                                                        </select>
                                                    </td>
                                                </tr>
                                            </template>
                                        </tbody>
                                    </table>
                                </div>

                                <div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mt-3">
                                    <div class="small text-secondary">
                                        <span class="fw-bold">Dealer total</span>
                                        $<span class="ark-money" x-text="capture.dealer_total || selectedDealerTotal()"></span>
                                        · Detected <span x-text="rows.length"></span> parts
                                        · Adding <span x-text="selectedCount()"></span>
                                    </div>
                                    <div class="btn-list">
                                        <button type="button" class="btn" @click="resetUpload()" :disabled="importing">Back</button>
                                        <button type="submit" class="btn btn-primary" :disabled="importing || selectedCount() === 0">
                                            <span x-text="importing ? 'Adding…' : ('Add ' + selectedCount() + ' Parts to Estimate')"></span>
                                        </button>
                                    </div>
                                </div>
                            </form>
                        </div>
                        </template>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
