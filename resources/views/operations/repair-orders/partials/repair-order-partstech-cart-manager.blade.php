@php
    $canImportQuote = $canImportQuote ?? false;
    $cartShopSettings = App\Ark\Operations\Settings\ShopSettings::current();
    $cartManagerConfig = [
        'previewUrl' => route('operations.repair-orders.partstech.import.preview', $repairOrder),
        'pricingUrl' => route('operations.repair-orders.partstech.import.pricing', $repairOrder),
        'importUrl' => route('operations.repair-orders.partstech.import', $repairOrder),
        'csrfToken' => csrf_token(),
        'repairOrderId' => $repairOrder->id,
        'cartReference' => app(App\Ark\Operations\Parts\PartsTechCatalogLauncher::class)->partsTechCartReference($repairOrder),
        'estimateVersionField' => App\Ark\Operations\RepairOrders\RepairOrderConcurrency::FIELD,
        'defaultPartsMatrixKey' => $cartShopSettings->defaultPartsMatrix()['key'],
        'partsMatrices' => collect($cartShopSettings->partsMatrices())
            ->map(fn (array $matrix): array => [
                'key' => $matrix['key'],
                'name' => $matrix['name'],
                'is_default' => (bool) ($matrix['is_default'] ?? false),
            ])
            ->values()
            ->all(),
        'concerns' => $repairOrder->concerns->sortBy('position')->values()->map(fn ($concern): array => [
            'id' => $concern->id,
            'summary' => $concern->summary,
            'default_parts_matrix_key' => $concern->defaultPartsMatrix($cartShopSettings)['key'],
            'work_groups' => $concern->workGroups
                ->map(fn ($workGroup): array => [
                    'id' => $workGroup->id,
                    'title' => $workGroup->title,
                    'has_labor_anchor' => $workGroup->hasPartsAttachAnchor(),
                ])
                ->values()
                ->all(),
        ])->all(),
    ];
@endphp

@if ($canImportQuote)
    <div
        id="partstech-cart-manager"
        data-partstech-cart-manager
        x-data="arkPartsTechCartManager(@js($cartManagerConfig))"
        @ark:partstech-cart-open.window="openManager($event.detail || {})"
        @ark:partstech-pull-quote.window="openManager({ pull: true, concernId: $event.detail?.concernId })"
        @ark:partstech-launched.window="onLaunched($event.detail || {})"
    >
        <div x-show="open" x-cloak>
            <div class="modal-backdrop show"></div>
            <div
                class="modal d-block"
                role="dialog"
                aria-modal="true"
                aria-labelledby="partstech-cart-title"
                @keydown.escape.window="open && close()"
                @click.self="close()"
            >
                <div class="modal-dialog modal-xl modal-dialog-scrollable modal-fullscreen-md-down" @click.self="close()">
                    <div class="modal-content">
                        <div class="modal-header gap-2">
                            <div class="min-w-0 me-auto">
                                <h2 class="modal-title" id="partstech-cart-title">PartsTech Cart</h2>
                                <div class="small text-secondary text-truncate">
                                    <span x-text="cartReference"></span>
                                    <span x-show="loaded" x-text="' · ' + rows.length + (rows.length === 1 ? ' part' : ' parts')"></span>
                                </div>
                            </div>
                            <button type="button" class="btn btn-sm" @click="openPartsTech()" aria-label="Open PartsTech" title="Open PartsTech for this RO" data-cart-open-partstech>
                                <x-tabler.icon name="external-link" />
                                <span class="d-none d-sm-inline">PartsTech</span>
                            </button>
                            <button
                                type="button"
                                class="btn btn-sm btn-primary"
                                x-ref="pullButton"
                                :disabled="loading || importing"
                                @click="pull()"
                                data-cart-pull
                            >
                                <span x-show="loading" class="spinner-border spinner-border-sm me-1" aria-hidden="true"></span>
                                <x-tabler.icon name="refresh" x-show="! loading" />
                                <span x-text="loaded ? 'Refresh Cart' : 'Pull Cart'">Pull Cart</span>
                            </button>
                            <button type="button" class="btn-close ms-1" aria-label="Close" @click="close()"></button>
                        </div>

                        <div class="modal-body p-0" @focusin="keepFocusedFieldVisible($event)">
                            <input type="hidden" x-ref="estimateVersion" name="{{ App\Ark\Operations\RepairOrders\RepairOrderConcurrency::FIELD }}" value="{{ $estimateVersion }}">

                            <div x-show="loading" x-cloak class="px-3 py-2 border-bottom small text-secondary" role="status" aria-live="polite">
                                <span class="spinner-border spinner-border-sm me-1" aria-hidden="true"></span>
                                <span x-text="loadingStatus"></span>
                            </div>

                            <div x-show="error" x-cloak class="alert alert-danger m-3 mb-0" role="alert">
                                <div x-text="error"></div>
                                <button x-show="cartLocked" x-cloak type="button" class="btn btn-sm btn-danger mt-2" @click="pull(true)">
                                    Switch PartsTech to this RO and pull
                                </button>
                                <button x-show="conflict" x-cloak type="button" class="btn btn-sm mt-2" @click="window.location.reload()">
                                    Reload repair order
                                </button>
                            </div>

                            <template x-for="warning in warnings" :key="warning">
                                <div class="alert alert-warning m-3 mb-0 small" role="status" x-text="warning"></div>
                            </template>

                            <div x-show="! loaded && ! loading && ! error" x-cloak class="p-4 text-center text-secondary">
                                Pull the cart to review its parts here.
                            </div>

                            <div x-show="loaded && ! loading && rows.length > 1 && context.concerns.length > 1" x-cloak data-cart-assign-all>
                                <div class="d-flex flex-wrap align-items-center gap-2 px-3 py-2 border-bottom bg-body-tertiary">
                                    <label class="small text-secondary mb-0" for="partstech-cart-assign-all">Assign selected to</label>
                                    <select
                                        id="partstech-cart-assign-all"
                                        class="form-select form-select-sm w-auto mw-100"
                                        x-model="bulkConcernId"
                                        @change="assignAll(bulkConcernId)"
                                    >
                                        <option value="" disabled>Choose concern…</option>
                                        <template x-for="concern in context.concerns" :key="'bulk-' + concern.id">
                                            <option :value="concern.id" x-text="concern.summary"></option>
                                        </template>
                                    </select>
                                </div>
                            </div>

                            <div x-show="loaded" class="list-group list-group-flush" data-cart-lines>
                                <template x-for="row in rows" :key="row.source_key">
                                    <div
                                        class="list-group-item py-2"
                                        :class="row.selected ? '' : 'bg-body-tertiary'"
                                        :data-cart-line="row.source_key"
                                        :data-import-status="row.import_status"
                                    >
                                        <div class="d-flex gap-2 align-items-start">
                                            <input
                                                type="checkbox"
                                                class="form-check-input mt-1 flex-shrink-0"
                                                x-model="row.selected"
                                                @change="onSelectedChange(row)"
                                                :aria-label="'Import ' + row.description"
                                            >
                                            <div class="flex-fill min-w-0" :class="row.selected ? '' : 'opacity-75'">
                                                <div class="d-flex flex-wrap align-items-baseline column-gap-2">
                                                    <span class="fw-bold text-break" x-text="row.description"></span>
                                                    <span
                                                        class="badge"
                                                        :class="{
                                                            'bg-green-lt': row.import_status === IMPORT_STATUS.NEW,
                                                            'bg-secondary-lt': row.import_status === IMPORT_STATUS.IMPORTED,
                                                            'bg-yellow-lt': row.import_status === IMPORT_STATUS.CHANGED,
                                                        }"
                                                        x-text="{ new: 'New', imported: 'On estimate', changed: 'Changed in cart' }[row.import_status]"
                                                    ></span>
                                                </div>
                                                <div
                                                    class="small text-secondary text-break"
                                                    x-show="row.part_number || row.vendor_name"
                                                    x-text="[row.part_number, row.vendor_name].filter(Boolean).join(' · ')"
                                                ></div>

                                                <div class="small d-flex flex-wrap align-items-center column-gap-2 row-gap-1 mt-1" data-cart-pricing>
                                                    <span>Qty <span class="ark-money" x-text="formatQuantity(row.quantity)"></span></span>
                                                    <span class="text-secondary" aria-hidden="true">•</span>
                                                    <label class="d-inline-flex align-items-center gap-1 mb-0">
                                                        Cost
                                                        <span class="input-group input-group-sm input-group-flat flex-nowrap" style="width: 6.5rem">
                                                            <span class="input-group-text px-1">$</span>
                                                            <input
                                                                type="text"
                                                                inputmode="decimal"
                                                                class="form-control form-control-sm text-end ark-money px-1"
                                                                x-model="row.part_cost"
                                                                @input="onCostInput(row)"
                                                                :aria-label="'Unit cost for ' + row.description"
                                                            >
                                                        </span>
                                                    </label>
                                                    <span class="text-secondary" aria-hidden="true">•</span>
                                                    <span>
                                                        Sell <span class="fw-bold ark-money" x-text="row.pricing ? row.pricing.unit_sell : '—'" data-cart-sell></span><span
                                                            x-show="row.pricing && Number(row.quantity) !== 1"
                                                            x-cloak
                                                            class="ark-money"
                                                            x-text="' ea · ' + row.pricing?.line_sell"
                                                            data-cart-line-sell
                                                        ></span>
                                                    </span>
                                                    <span class="text-secondary" aria-hidden="true">•</span>
                                                    <span :class="row.pricing?.below_cost ? 'text-danger fw-bold' : ''">
                                                        Margin <span class="ark-money" x-text="row.pricing?.margin_percentage != null ? row.pricing.margin_percentage + '%' : '—'" data-cart-margin></span>
                                                    </span>
                                                    <span x-show="row.pricing?.below_cost" x-cloak class="badge bg-red-lt">Below cost</span>
                                                    <span x-show="row.pricing_error" x-cloak class="text-danger" x-text="row.pricing_error"></span>
                                                </div>

                                                <div x-show="row.import_status !== IMPORT_STATUS.NEW" x-cloak class="small mt-1" data-cart-imported>
                                                    <template x-for="line in row.imported_lines" :key="row.source_key + '-l-' + line.line_id">
                                                        <div class="text-secondary">
                                                            <span x-text="'On estimate under ' + (line.concern_summary || 'this RO') + ': qty ' + formatQuantity(line.estimate_quantity) + ' at ' + line.estimate_unit_sell + ' each'"></span><span
                                                                x-show="line.cart_changed && line.cart_quantity"
                                                                x-text="'. Imported as qty ' + formatQuantity(line.cart_quantity) + ' at $' + line.cart_part_cost + ' cost; the cart now has qty ' + formatQuantity(row.quantity) + ' at $' + row.cart_part_cost + '.'"
                                                            ></span>
                                                        </div>
                                                    </template>
                                                    <div x-show="row.selected" x-cloak class="alert alert-warning py-2 px-2 mt-1 mb-0" role="group" :aria-label="'Confirm another copy of ' + row.description" data-cart-duplicate-confirm>
                                                        <label class="form-check mb-0">
                                                            <input type="checkbox" class="form-check-input" x-model="row.duplicate_acknowledged" data-cart-duplicate-ack>
                                                            <span class="form-check-label">Add another copy as a new estimate line. Nothing already on the estimate changes.</span>
                                                        </label>
                                                    </div>
                                                </div>

                                                <div class="row g-2 mt-1">
                                                    <div class="col-12 col-sm-6" :class="workGroups(row.concern_id).length > 0 ? 'col-lg-4' : ''">
                                                        <div class="input-group input-group-sm flex-nowrap">
                                                            <span class="input-group-text">Concern</span>
                                                            <select
                                                                class="form-select form-select-sm"
                                                                :class="row.selected && ! row.concern_id ? 'is-invalid' : ''"
                                                                x-model="row.concern_id"
                                                                @change="onConcernChange(row)"
                                                                :aria-label="'Concern for ' + row.description"
                                                                data-cart-concern
                                                            >
                                                                <option value="" disabled>Choose concern…</option>
                                                                <template x-for="concern in context.concerns" :key="row.source_key + '-c-' + concern.id">
                                                                    <option :value="concern.id" :selected="concern.id === row.concern_id" x-text="concern.summary"></option>
                                                                </template>
                                                            </select>
                                                        </div>
                                                    </div>
                                                    <div class="col-12 col-sm-6" :class="workGroups(row.concern_id).length > 0 ? 'col-lg-4' : ''">
                                                        <div class="input-group input-group-sm flex-nowrap">
                                                            <span class="input-group-text">Matrix</span>
                                                            <select
                                                                class="form-select form-select-sm"
                                                                x-model="row.pricing_matrix_key"
                                                                @change="onMatrixChange(row, $event.target.value)"
                                                                :aria-label="'Parts matrix for ' + row.description"
                                                                data-cart-matrix
                                                            >
                                                                <template x-for="matrix in context.matrices" :key="row.source_key + '-m-' + matrix.key">
                                                                    <option :value="matrix.key" :selected="matrix.key === row.pricing_matrix_key" x-text="matrix.name"></option>
                                                                </template>
                                                            </select>
                                                        </div>
                                                    </div>
                                                    <div class="col-12 col-lg-4" x-show="workGroups(row.concern_id).length > 0" x-cloak>
                                                        <div class="input-group input-group-sm flex-nowrap">
                                                            <span class="input-group-text">Repair</span>
                                                            <select
                                                                class="form-select form-select-sm"
                                                                x-model="row.work_group_id"
                                                                :aria-label="'Repair action for ' + row.description"
                                                            >
                                                                <option value="">Ungrouped in concern</option>
                                                                <template x-for="workGroup in workGroups(row.concern_id)" :key="row.source_key + '-w-' + workGroup.id">
                                                                    <option :value="workGroup.id" :selected="workGroup.id === row.work_group_id" x-text="workGroup.title"></option>
                                                                </template>
                                                            </select>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </template>
                            </div>
                        </div>

                        <div class="modal-footer d-block" data-cart-footer>
                            <div class="d-flex flex-wrap align-items-center gap-2">
                                <div class="me-auto small" data-cart-summary>
                                    <span class="fw-bold" x-text="selectedRows.length + ' selected'"></span>
                                    <span x-show="selectedRows.length > 0" x-text="' · Sell ' + selectedSell"></span>
                                </div>
                                <button type="button" class="btn d-none d-sm-inline-flex" @click="close()" :disabled="importing">Close</button>
                                <button
                                    type="button"
                                    class="btn btn-primary"
                                    :disabled="! canImport"
                                    :aria-disabled="canImport ? 'false' : 'true'"
                                    aria-describedby="partstech-cart-issues"
                                    @click="submit()"
                                    data-cart-import
                                >
                                    <span x-show="importing" class="spinner-border spinner-border-sm me-1" aria-hidden="true"></span>
                                    <span x-text="importing ? 'Importing…' : 'Import selected'">Import selected</span>
                                </button>
                            </div>
                            <div id="partstech-cart-issues" class="small mt-1" role="status" aria-live="polite" data-cart-issues>
                                <template x-for="issue in issues" :key="issue">
                                    <div class="text-danger" x-text="issue"></div>
                                </template>
                                <div x-show="issues.length === 0 && selectedRows.length > 0 && pricingPending" x-cloak class="text-secondary">Pricing parts…</div>
                                <div x-show="loaded && selectedRows.length === 0" x-cloak class="text-secondary">Tick the parts to import.</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endif
