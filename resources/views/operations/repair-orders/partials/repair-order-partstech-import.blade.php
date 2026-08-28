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
        'initialPartsMatrices' => collect($partstechShopSettings->partsMatrices())
            ->map(fn (array $matrix): array => [
                'key' => $matrix['key'],
                'name' => $matrix['name'],
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
        class="ops-worksheet-procurement-panel"
        x-data="arkPartsTechQuoteImport(@js($partstechImportConfig))"
        @ark:partstech-pull-quote.window="loadQuote(false, $event.detail?.concernId)"
        x-show="loaded || error || loading"
        x-cloak
    >
        <div
            x-show="loading"
            x-cloak
            class="flex items-center gap-3 px-4 py-5"
            role="status"
            aria-live="polite"
        >
            <div class="ops-partstech-loader shrink-0" aria-hidden="true"></div>
            <div>
                <p class="text-xs font-bold uppercase tracking-[0.08em] text-slate-700" x-text="loadingStatus"></p>
                <p class="mt-0.5 text-[11px] text-slate-500">ARK is syncing the PartsTech cart and pricing parts. This may take a few seconds.</p>
            </div>
        </div>

        <div x-show="loaded && ! loading" class="flex flex-wrap items-center justify-between gap-2 border-b border-slate-200 bg-slate-50/90 px-3 py-2">
            <p class="text-xs font-medium text-slate-600">
                <span x-text="rows.length + ' part' + (rows.length === 1 ? '' : 's') + ' ready to import'"></span>
                <span x-show="hasSingleScope"> · default scope <strong x-text="singleScopeLabel()"></strong> (change per line if needed)</span>
                <span x-show="! hasSingleScope"> · assign scope and repair per line</span>
                <span> · matrix defaults from scope (change per part if needed)</span>
            </p>
            <button
                type="button"
                class="text-xs font-bold uppercase tracking-[0.08em] text-slate-500 hover:text-slate-800"
                :disabled="loading"
                @click="loadQuote()"
            >
                <span x-text="loading ? 'Pulling…' : 'Pull again'"></span>
            </button>
        </div>

        <div x-show="error && ! loading" x-cloak class="border-t border-rose-200 bg-rose-50 px-3 py-2 text-xs font-semibold text-rose-900">
            <p x-text="error"></p>
            <button
                x-show="cartLocked"
                x-cloak
                type="button"
                class="mt-2 rounded-sm bg-rose-900 px-2.5 py-1.5 text-[10px] font-bold uppercase tracking-[0.08em] text-rose-50 hover:bg-rose-950"
                @click="loadQuote(true)"
            >
                Switch PartsTech to this RO and pull
            </button>
        </div>

        <div x-show="loaded && ! loading" x-cloak class="border-t border-slate-200">
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
                                    :value="row.pricing_matrix_explicit && row.pricing_matrix_key ? row.pricing_matrix_key : ''"
                                >
                            </div>
                        </template>
                    </div>
                </template>

                <div class="overflow-x-auto">
                    <table class="min-w-full text-left text-xs">
                        <thead class="bg-slate-100 text-[10px] font-bold uppercase tracking-[0.08em] text-slate-500">
                            <tr>
                                <th class="w-8 px-2 py-1.5"></th>
                                <th class="min-w-[12rem] px-2 py-1.5">Part</th>
                                <th class="px-2 py-1.5">Position</th>
                                <th class="px-2 py-1.5 text-right">Qty</th>
                                <th class="px-2 py-1.5 text-right">Cost</th>
                                <th class="min-w-[9rem] px-2 py-1.5">Matrix</th>
                                <th class="px-2 py-1.5 text-right">Sell</th>
                                <th class="px-2 py-1.5">Vendor</th>
                                <th class="min-w-[10rem] px-2 py-1.5">Scope</th>
                                <th class="min-w-[10rem] px-2 py-1.5">Repair action</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 bg-white">
                            <template x-for="row in rows" :key="row.source_key">
                                <tr class="align-top hover:bg-slate-50/80">
                                    <td class="px-2 py-1.5">
                                        <input type="checkbox" class="rounded border-slate-300" x-model="row.selected">
                                    </td>
                                    <td class="px-2 py-1.5">
                                        <p class="font-semibold text-slate-950" x-text="row.description"></p>
                                        <p class="text-[11px] text-slate-500" x-show="row.part_number" x-text="row.part_number"></p>
                                        <p class="text-[10px] text-slate-400" x-show="row.guidance" x-text="row.guidance"></p>
                                    </td>
                                    <td class="px-2 py-1.5">
                                        <span
                                            x-show="row.position_label"
                                            class="ops-partstech-position-chip"
                                            x-text="row.position_label"
                                        ></span>
                                        <span x-show="! row.position_label" class="text-[11px] text-slate-400">—</span>
                                    </td>
                                    <td class="px-2 py-1.5 text-right tabular-nums text-slate-800" x-text="row.quantity"></td>
                                    <td class="px-2 py-1.5">
                                        <input
                                            type="text"
                                            x-model="row.part_cost"
                                            @input="onRowCostInput(row)"
                                            inputmode="decimal"
                                            class="w-20 rounded-sm border border-slate-300 bg-white px-2 py-1 text-right text-xs font-semibold tabular-nums text-slate-800"
                                            :disabled="! row.selected"
                                        >
                                    </td>
                                    <td class="px-2 py-1.5">
                                        <select
                                            x-model="row.pricing_matrix_key"
                                            @change="onRowMatrixChange(row)"
                                            class="w-full max-w-[9rem] rounded-sm border border-slate-300 bg-white px-1.5 py-1 text-xs font-semibold text-slate-800"
                                            :disabled="! row.selected || ! row.concern_id"
                                        >
                                            <template x-for="matrix in partsMatrices" :key="row.source_key + '-m-' + matrix.key">
                                                <option :value="matrix.key" x-text="matrixShortName(matrix)"></option>
                                            </template>
                                        </select>
                                    </td>
                                    <td class="px-2 py-1.5 text-right">
                                        <span
                                            class="text-xs font-semibold tabular-nums text-slate-800"
                                            x-text="row.sell || (row.previewing ? '…' : '—')"
                                        ></span>
                                        <p class="mt-0.5 text-[10px] tabular-nums text-slate-400" x-show="row.margin_percentage">
                                            <span x-text="row.margin_percentage"></span>% margin
                                            <span x-show="row.previewing"> · pricing…</span>
                                        </p>
                                    </td>
                                    <td class="px-2 py-1.5 text-slate-600" x-text="row.vendor_name || '—'"></td>
                                    <td class="px-2 py-1.5">
                                        <select
                                            x-model="row.concern_id"
                                            @change="onRowConcernChange(row)"
                                            class="w-full rounded-sm border border-slate-300 bg-white px-2 py-1 text-xs font-semibold text-slate-800"
                                            :disabled="! row.selected"
                                        >
                                            <option value="">Choose scope…</option>
                                            <template x-for="concern in concerns" :key="concern.id">
                                                <option :value="String(concern.id)" x-text="concern.summary"></option>
                                            </template>
                                        </select>
                                    </td>
                                    <td class="px-2 py-1.5">
                                        <select
                                            x-model="row.work_group_id"
                                            @change="onRowWorkGroupChange(row)"
                                            class="w-full rounded-sm border border-slate-300 bg-white px-2 py-1 text-xs font-semibold text-slate-800"
                                            :disabled="! row.selected || workGroupsForConcern(row.concern_id).length === 0"
                                        >
                                            <option value="">Ungrouped in scope</option>
                                            <template x-for="workGroup in workGroupsForConcern(row.concern_id)" :key="workGroup.id">
                                                <option :value="String(workGroup.id)" x-text="workGroup.title"></option>
                                            </template>
                                        </select>
                                        <p
                                            class="mt-0.5 text-[10px] text-slate-400"
                                            x-show="row.selected && row.concern_id && workGroupsForConcern(row.concern_id).length === 0"
                                        >
                                            Add a repair with labor on this scope first.
                                        </p>
                                    </td>
                                </tr>
                            </template>
                        </tbody>
                    </table>
                </div>

                <div class="flex justify-end border-t border-slate-100 bg-slate-50/80 px-3 py-2">
                    <button
                        id="partstech-import-submit-btn"
                        type="submit"
                        class="rounded-sm border border-slate-800 bg-slate-900 px-3 py-1.5 text-xs font-bold uppercase tracking-[0.08em] text-white hover:bg-slate-800"
                        :disabled="importing || selectedCount() === 0"
                    >
                        <span x-text="importing ? 'Importing…' : 'Import selected parts'"></span>
                    </button>
                </div>
            </form>
        </div>
    </div>
@endif
