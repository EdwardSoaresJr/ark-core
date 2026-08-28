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
    <div
        x-show="open"
        x-cloak
        class="ops-workspace-modal"
        role="dialog"
        aria-modal="true"
        @keydown.escape.window="closeCapture()"
    >
        <button
            type="button"
            class="ops-workspace-modal__backdrop"
            aria-label="Close"
            :disabled="analyzing || importing"
            @click="closeCapture()"
        ></button>
        <div
            class="ops-workspace-modal__dialog"
            style="width: min(96vw, 72rem); max-height: min(92dvh, 56rem);"
            x-show="open"
            x-transition:enter="ops-workspace-modal--enter"
            x-transition:enter-start="ops-workspace-modal--enter-start"
            x-transition:enter-end="ops-workspace-modal--enter-end"
            x-transition:leave="ops-workspace-modal--leave"
            x-transition:leave-start="ops-workspace-modal--leave-start"
            x-transition:leave-end="ops-workspace-modal--leave-end"
            @click.stop
        >
            <header class="ops-workspace-modal__header">
                <div class="ops-workspace-modal__heading min-w-0">
                    <h2 class="ops-workspace-modal__title">Import Dealer Quote</h2>
                    <p class="ops-workspace-modal__helper">PDF or pasted text → review → add parts to this estimate.</p>
                </div>
                <button
                    type="button"
                    class="ops-workspace-modal__close"
                    @click="closeCapture()"
                    :disabled="analyzing || importing"
                >
                    Close
                </button>
            </header>

            <div class="ops-workspace-modal__body">
            <div x-show="error" class="mb-3 border border-rose-200 bg-rose-50 px-3 py-2 text-xs font-semibold text-rose-900" x-text="error"></div>

            <div x-show="step === 'upload'" class="space-y-4 px-4 py-5">
                <label class="block">
                    <span class="text-[11px] font-bold uppercase tracking-[0.08em] text-slate-500">Upload PDF or photo</span>
                    <input
                        type="file"
                        accept=".pdf,application/pdf,image/png,image/jpeg,image/webp,.png,.jpg,.jpeg,.webp,.txt,text/plain"
                        class="mt-1 block w-full text-sm text-slate-700 file:mr-3 file:rounded-sm file:border-0 file:bg-slate-900 file:px-3 file:py-1.5 file:text-xs file:font-bold file:uppercase file:tracking-[0.08em] file:text-white"
                        @change="onFileChange($event)"
                    >
                    <span x-show="quoteFileName" class="mt-1 block text-xs text-slate-500" x-text="quoteFileName"></span>
                    <p class="mt-1 text-[11px] text-slate-500">Scanned dealer quotes are read with OCR automatically.</p>
                </label>

                <div class="text-center text-[11px] font-bold uppercase tracking-[0.08em] text-slate-400">or</div>

                <div class="grid gap-4 lg:grid-cols-2">
                    <label class="block">
                        <span class="text-[11px] font-bold uppercase tracking-[0.08em] text-slate-500">Paste copied text</span>
                        <textarea
                            x-model="quoteText"
                            rows="12"
                            class="mt-1 w-full rounded-sm border border-slate-300 px-3 py-2 font-mono text-xs text-slate-800"
                            placeholder="1  06J-103-603-BD  Upper Oil Sump  406.08"
                        ></textarea>
                    </label>

                    <div class="block">
                        <span class="text-[11px] font-bold uppercase tracking-[0.08em] text-slate-500">Parts preview</span>
                        <div class="mt-1 h-[17.5rem] overflow-y-auto rounded-sm border border-slate-300 bg-slate-50 px-3 py-2 text-xs text-slate-700">
                            <template x-if="uploadPreviewLines().length === 0">
                                <p class="text-slate-400">Paste a quote to see part lines here · Analyze to confirm.</p>
                            </template>
                            <ul x-show="uploadPreviewLines().length > 0" class="space-y-1.5">
                                <template x-for="(preview, previewIndex) in uploadPreviewLines()" :key="'preview-' + previewIndex">
                                    <li class="flex gap-2 border-b border-slate-200/80 pb-1.5 last:border-0 last:pb-0">
                                        <span class="w-6 shrink-0 tabular-nums text-slate-500" x-text="preview.quantity"></span>
                                        <span class="min-w-0 flex-1">
                                            <span class="font-mono text-[11px] text-slate-800" x-text="preview.part_number || '—'"></span>
                                            <span class="mt-0.5 block text-slate-600" x-text="preview.description || 'Part line'"></span>
                                        </span>
                                        <span class="shrink-0 tabular-nums text-slate-700" x-text="preview.part_cost ? ('$' + preview.part_cost) : ''"></span>
                                    </li>
                                </template>
                            </ul>
                        </div>
                    </div>
                </div>

                <div class="flex justify-end gap-2">
                    <button type="button" class="ops-review-action" @click="closeCapture()">Cancel</button>
                    <button
                        type="button"
                        class="ops-review-action ops-review-action--primary"
                        :disabled="analyzing || (! quoteFile && ! quoteText.trim())"
                        @click="analyzeQuote()"
                    >
                        <span x-text="analyzing ? 'Reading quote…' : 'Analyze Quote'"></span>
                    </button>
                </div>
            </div>

            <div x-show="step === 'review' && capture" class="border-t border-slate-200">
                <div class="grid gap-2 border-b border-slate-200 bg-slate-50 px-4 py-3 text-xs text-slate-700 sm:grid-cols-2 lg:grid-cols-4">
                    <div>
                        <p class="text-[10px] font-bold uppercase tracking-[0.08em] text-slate-500">Supplier</p>
                        <p class="font-semibold" x-text="capture.supplier_name || '—'"></p>
                    </div>
                    <div>
                        <p class="text-[10px] font-bold uppercase tracking-[0.08em] text-slate-500">Quote #</p>
                        <p class="font-semibold" x-text="capture.quote_number || '—'"></p>
                    </div>
                    <div>
                        <p class="text-[10px] font-bold uppercase tracking-[0.08em] text-slate-500">Vehicle</p>
                        <p class="font-semibold" x-text="capture.vehicle_description || '—'"></p>
                    </div>
                    <div>
                        <p class="text-[10px] font-bold uppercase tracking-[0.08em] text-slate-500">VIN</p>
                        <p class="font-semibold break-all" x-text="capture.vin || '—'"></p>
                    </div>
                </div>

                <form method="POST" :action="importUrl" @submit="beforeSubmit($event)">
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

                    <details class="border-b border-slate-200 bg-slate-50/60 px-3 py-2" open>
                        <summary class="cursor-pointer text-[10px] font-bold uppercase tracking-[0.08em] text-slate-500">
                            Quote text · <span x-text="rows.length"></span> parts
                        </summary>
                        <pre
                            class="mt-2 max-h-36 overflow-auto whitespace-pre-wrap rounded-sm border border-slate-200 bg-white px-3 py-2 font-mono text-[11px] leading-snug text-slate-700"
                            x-text="capture.raw_text || quoteText || '—'"
                        ></pre>
                    </details>

                    <div class="flex flex-wrap items-end gap-2 border-b border-slate-200 bg-slate-50/90 px-3 py-2.5">
                        <div class="min-w-[10rem] flex-[1.4]">
                            <label class="block text-[10px] font-bold uppercase tracking-[0.08em] text-slate-500">Scope</label>
                            <select
                                x-model="bulk.concern_id"
                                @change="onBulkConcernChange()"
                                class="mt-0.5 w-full rounded-sm border border-slate-300 px-1.5 py-1 text-xs"
                            >
                                <option value="">Choose scope</option>
                                <template x-for="concern in concerns" :key="'bulk-c-' + concern.id">
                                    <option :value="concern.id" x-text="concern.summary"></option>
                                </template>
                            </select>
                        </div>
                        <div class="w-[7.5rem] shrink-0">
                            <label class="block text-[10px] font-bold uppercase tracking-[0.08em] text-slate-500">Repair action</label>
                            <select
                                x-model="bulk.work_group_id"
                                class="mt-0.5 w-full rounded-sm border border-slate-300 px-1.5 py-1 text-xs"
                                :disabled="workGroupsForConcern(bulk.concern_id).length === 0"
                            >
                                <option value="">Scope</option>
                                <template x-for="workGroup in workGroupsForConcern(bulk.concern_id)" :key="'bulk-wg-' + workGroup.id">
                                    <option :value="workGroup.id" x-text="workGroup.title"></option>
                                </template>
                            </select>
                        </div>
                        <div class="w-[5.5rem] shrink-0">
                            <label class="block text-[10px] font-bold uppercase tracking-[0.08em] text-slate-500">Matrix</label>
                            <select
                                x-model="bulk.pricing_matrix_key"
                                class="mt-0.5 w-full rounded-sm border border-slate-300 px-1.5 py-1 text-xs"
                            >
                                <option value="">Default</option>
                                <template x-for="matrix in partsMatrices" :key="'bulk-m-' + matrix.key">
                                    <option :value="matrix.key" x-text="matrixShortName(matrix)"></option>
                                </template>
                            </select>
                        </div>
                        <button
                            type="button"
                            class="ops-review-action shrink-0"
                            @click="applyToSelected()"
                            :disabled="importing || selectedCount() === 0"
                        >
                            Apply to selected
                        </button>
                    </div>

                    <div class="overflow-x-auto">
                        <table class="min-w-full text-left text-xs">
                            <thead class="bg-slate-100 text-[10px] font-bold uppercase tracking-[0.08em] text-slate-500">
                                <tr>
                                    <th class="w-8 px-2 py-1.5">
                                        <input
                                            type="checkbox"
                                            class="rounded-sm border-slate-300 text-slate-900"
                                            x-model="allSelected"
                                        >
                                    </th>
                                    <th class="px-2 py-1.5 text-right">Qty</th>
                                    <th class="px-2 py-1.5">Part #</th>
                                    <th class="min-w-[12rem] px-2 py-1.5">Name</th>
                                    <th class="px-2 py-1.5 text-right">Cost</th>
                                    <th class="px-2 py-1.5 text-right">Sell</th>
                                    <th class="w-[5.5rem] px-2 py-1.5">Matrix</th>
                                    <th class="min-w-[8rem] px-2 py-1.5">Scope</th>
                                    <th class="w-[7.5rem] px-2 py-1.5">Action</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100 bg-white">
                                <template x-for="row in rows" :key="row.source_key">
                                    <tr :class="row.selected ? '' : 'opacity-50'">
                                        <td class="px-2 py-1.5">
                                            <input type="checkbox" x-model="row.selected" class="rounded-sm border-slate-300 text-slate-900">
                                        </td>
                                        <td class="px-2 py-1.5 text-right tabular-nums" x-text="row.quantity"></td>
                                        <td class="px-2 py-1.5 font-mono text-[11px]" x-text="row.part_number || '—'"></td>
                                        <td class="px-2 py-1.5">
                                            <input
                                                type="text"
                                                x-model="row.description"
                                                class="w-full min-w-[11rem] rounded-sm border border-slate-300 px-1.5 py-1 text-xs"
                                            >
                                        </td>
                                        <td class="px-2 py-1.5 text-right">
                                            <input
                                                type="text"
                                                inputmode="decimal"
                                                x-model="row.part_cost"
                                                @input="onRowCostInput(row)"
                                                class="w-20 rounded-sm border border-slate-300 px-1.5 py-1 text-right text-xs"
                                            >
                                        </td>
                                        <td class="px-2 py-1.5 text-right tabular-nums text-slate-700" x-text="row.sell || '—'"></td>
                                        <td class="w-[5.5rem] px-2 py-1.5">
                                            <select
                                                x-model="row.pricing_matrix_key"
                                                @change="onRowMatrixChange(row)"
                                                class="w-full max-w-[5.5rem] rounded-sm border border-slate-300 px-1 py-1 text-xs"
                                            >
                                                <template x-for="matrix in partsMatrices" :key="row.source_key + '-m-' + matrix.key">
                                                    <option :value="matrix.key" x-text="matrixShortName(matrix)"></option>
                                                </template>
                                            </select>
                                        </td>
                                        <td class="px-2 py-1.5">
                                            <select
                                                x-model="row.concern_id"
                                                @change="onRowConcernChange(row)"
                                                class="w-full rounded-sm border border-slate-300 px-1.5 py-1 text-xs"
                                            >
                                                <option value="">Choose scope</option>
                                                <template x-for="concern in concerns" :key="row.source_key + '-c-' + concern.id">
                                                    <option :value="concern.id" x-text="concern.summary"></option>
                                                </template>
                                            </select>
                                        </td>
                                        <td class="w-[7.5rem] px-2 py-1.5">
                                            <select
                                                x-model="row.work_group_id"
                                                @change="onRowWorkGroupChange(row)"
                                                class="w-full max-w-[7.5rem] rounded-sm border border-slate-300 px-1 py-1 text-xs"
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

                    <div class="flex flex-wrap items-center justify-between gap-3 border-t border-slate-200 bg-slate-50 px-4 py-3">
                        <div class="text-xs text-slate-600">
                            <span class="font-semibold">Dealer total</span>
                            $<span x-text="capture.dealer_total || selectedDealerTotal()"></span>
                            · Detected <span x-text="rows.length"></span> parts
                            · Adding <span x-text="selectedCount()"></span>
                        </div>
                        <div class="flex gap-2">
                            <button type="button" class="ops-review-action" @click="resetUpload()" :disabled="importing">Back</button>
                            <button type="submit" class="ops-review-action ops-review-action--primary" :disabled="importing || selectedCount() === 0">
                                <span x-text="importing ? 'Adding…' : ('Add ' + selectedCount() + ' Parts to Estimate')"></span>
                            </button>
                        </div>
                    </div>
                </form>
            </div>
            </div>
        </div>
    </div>
</div>
