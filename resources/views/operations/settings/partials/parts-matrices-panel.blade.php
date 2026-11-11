@php
    $partsMatrices = $settings->partsMatrices();
    $defaultPartsMatrixKey = $settings->defaultPartsMatrix()['key'];
    $partsMatricesPayload = collect($partsMatrices)
        ->values()
        ->map(fn (array $matrix, int $index): array => [
            'key' => $matrix['key'],
            'name' => $matrix['name'],
            'is_default' => ($matrix['key'] === $defaultPartsMatrixKey) || (bool) ($matrix['is_default'] ?? false),
            'index' => $index,
            'rows' => collect($matrix['rows'] ?? [])
                ->values()
                ->map(fn (array $row, int $rowIndex): array => [
                    'uid' => $matrix['key'].'-'.$rowIndex,
                    'min_cost' => $row['min_cost'] ?? '',
                    'max_cost' => $row['max_cost'] ?? '',
                    'markup_percentage' => $row['markup_percentage'] ?? '',
                    'margin_percentage' => $row['margin_percentage'] ?? null,
                    'sort_order' => $rowIndex + 1,
                ])
                ->all(),
        ])
        ->all();
@endphp

@foreach ($partsMatrices as $matrix)
    <form id="delete-parts-matrix-{{ $matrix['key'] }}" method="POST" action="{{ route('operations.settings.shop.parts-matrices.destroy', $matrix['key']) }}" class="d-none">
        @csrf
        @method('DELETE')
    </form>
@endforeach

<script>
    window.partsMatricesPanel = function partsMatricesPanel(initialMatrices, initialDefaultKey) {
        const storageKey = 'ark:shop-settings:parts-matrix-key';
        const knownKeys = initialMatrices.map((matrix) => matrix.key);
        let storedKey = null;

        try {
            storedKey = localStorage.getItem(storageKey);
        } catch (error) {
            storedKey = null;
        }

        const selectedKey = knownKeys.includes(storedKey)
            ? storedKey
            : (knownKeys.includes(initialDefaultKey) ? initialDefaultKey : (knownKeys[0] || null));

        return {
            matrices: initialMatrices.map((matrix) => ({
                ...matrix,
                rows: (matrix.rows || []).map((row, index) => ({
                    ...row,
                    uid: row.uid || `${matrix.key}-${index}-${Math.random().toString(36).slice(2)}`,
                })),
            })),
            defaultPartsMatrixKey: initialDefaultKey,
            selectedKey,
            statusText: 'Autosaves edits',
            deleteConfirming: false,
            deleteTypedName: '',
            deleteMatrixName: '',
            deleteMatrixKey: null,
            autosaveTimer: null,
            autosaveRequestId: 0,
            get selectedMatrix() {
                return this.matrices.find((matrix) => matrix.key === this.selectedKey) || this.matrices[0] || null;
            },
            get updateAction() {
                const key = this.selectedKey || this.defaultPartsMatrixKey;

                return key ? `/app/settings/shop/parts-matrices/${key}` : '#';
            },
            async selectMatrix(key) {
                if (key === this.selectedKey) {
                    return;
                }

                // Flush pending/debounced edits before switching so sibling state is persisted.
                if (this.autosaveTimer !== null || this.statusText === 'Saving...' || this.statusText === 'Save needed') {
                    clearTimeout(this.autosaveTimer);
                    this.autosaveTimer = null;
                    await this.autosave(true);

                    if (this.statusText === 'Save needed') {
                        if (! window.confirm('This matrix has unsaved changes. Switch anyway?')) {
                            return;
                        }
                    }
                }

                this.cancelDeleteConfirm();
                this.selectedKey = key;

                try {
                    localStorage.setItem(storageKey, key);
                } catch (error) {
                    // Ignore storage failures; selection still works for the session.
                }
            },
            canDelete(matrix) {
                return this.matrices.length > 1 && matrix.key !== this.defaultPartsMatrixKey;
            },
            addRow(matrixIndex) {
                const matrix = this.matrices[matrixIndex];

                if (! matrix) {
                    return;
                }

                matrix.rows.push({
                    uid: `${Date.now()}-${Math.random().toString(36).slice(2)}`,
                    min_cost: '',
                    max_cost: '',
                    markup_percentage: '',
                    margin_percentage: null,
                    sort_order: matrix.rows.length + 1,
                });
                this.queueAutosave();
            },
            removeRow(matrixIndex, rowIndex) {
                const matrix = this.matrices[matrixIndex];

                if (! matrix || matrix.rows.length === 1) {
                    return;
                }

                matrix.rows.splice(rowIndex, 1);
                this.queueAutosave();
            },
            openDeleteConfirm(matrix) {
                this.deleteMatrixKey = matrix.key;
                this.deleteMatrixName = (matrix.name || '').trim();
                this.deleteConfirming = true;
                this.deleteTypedName = '';
                this.$nextTick(() => this.$refs.deleteConfirmInput?.focus());
            },
            cancelDeleteConfirm() {
                this.deleteConfirming = false;
                this.deleteTypedName = '';
                this.deleteMatrixKey = null;
                this.deleteMatrixName = '';
            },
            canConfirmDelete() {
                return this.deleteTypedName.trim() === this.deleteMatrixName.trim();
            },
            submitDelete() {
                if (! this.canConfirmDelete() || ! this.deleteMatrixKey) {
                    return;
                }

                const form = document.getElementById(`delete-parts-matrix-${this.deleteMatrixKey}`);

                if (! form) {
                    return;
                }

                let input = form.querySelector('[name=confirm_name]');

                if (! input) {
                    input = document.createElement('input');
                    input.type = 'hidden';
                    input.name = 'confirm_name';
                    form.appendChild(input);
                }

                input.value = this.deleteTypedName.trim();
                form.submit();
            },
            queueAutosave() {
                this.statusText = 'Save needed';
                this.autosave(false);
            },
            buildPartsMatrixFormData() {
                const form = document.getElementById('parts-matrix-form');
                const fd = new FormData();
                const token = form?.querySelector('[name="_token"]')?.value;

                if (token) {
                    fd.append('_token', token);
                }

                fd.append('_method', 'PATCH');
                fd.append('default_parts_matrix_key', this.defaultPartsMatrixKey);

                this.matrices.forEach((matrix, matrixIndex) => {
                    fd.append(`parts_matrices[${matrixIndex}][key]`, matrix.key);
                    fd.append(`parts_matrices[${matrixIndex}][name]`, matrix.name ?? '');

                    matrix.rows.forEach((row, rowIndex) => {
                        fd.append(`parts_matrices[${matrixIndex}][rows][${rowIndex}][min_cost]`, row.min_cost ?? '');
                        fd.append(`parts_matrices[${matrixIndex}][rows][${rowIndex}][max_cost]`, row.max_cost ?? '');
                        fd.append(`parts_matrices[${matrixIndex}][rows][${rowIndex}][markup_percentage]`, row.markup_percentage ?? '');
                        fd.append(`parts_matrices[${matrixIndex}][rows][${rowIndex}][sort_order]`, String(rowIndex + 1));
                    });
                });

                return fd;
            },
            autosaveSucceeded(response) {
                // Prefer JSON Accept so validation is 422 (not a followed HTML redirect that looks OK).
                // Success remains a redirect. Chromium exposes same-origin manual redirects as
                // type "opaqueredirect" with status 0; treat that as success.
                if (response.type === 'opaqueredirect') {
                    return true;
                }

                if (response.status === 422 || response.status >= 400) {
                    return false;
                }

                return response.status === 302
                    || response.status === 303
                    || response.status === 201
                    || response.ok;
            },
            autosave(immediate = false) {
                const matrixKey = this.selectedKey;

                if (! matrixKey) {
                    return Promise.resolve();
                }

                clearTimeout(this.autosaveTimer);
                this.autosaveTimer = null;

                const run = () => {
                    const requestId = ++this.autosaveRequestId;
                    this.statusText = 'Saving...';

                    return fetch(`/app/settings/shop/parts-matrices/${matrixKey}`, {
                        method: 'POST',
                        body: this.buildPartsMatrixFormData(),
                        redirect: 'manual',
                        headers: {
                            'X-Requested-With': 'XMLHttpRequest',
                            'Accept': 'application/json',
                        },
                    }).then((response) => {
                        if (requestId !== this.autosaveRequestId) {
                            return;
                        }

                        if (! this.autosaveSucceeded(response)) {
                            throw new Error('Save failed');
                        }

                        this.statusText = 'Saved';
                    }).catch(() => {
                        if (requestId !== this.autosaveRequestId) {
                            return;
                        }

                        this.statusText = 'Save needed';
                    });
                };

                if (immediate) {
                    return run();
                }

                return new Promise((resolve) => {
                    this.autosaveTimer = setTimeout(() => {
                        this.autosaveTimer = null;
                        resolve(run());
                    }, 700);
                });
            },
        };
    };
</script>

<div
    data-settings-parts-matrices
    x-data="partsMatricesPanel(@js($partsMatricesPayload), @js($defaultPartsMatrixKey))"
>
    <div class="row g-2 mb-3">
        <div class="col-12 col-md-4">
            <div class="card card-sm h-100">
                <div class="card-body">
                    <div class="subheader">You set</div>
                    <div class="h3 mb-0">Markup %</div>
                    <div class="small text-secondary">Changes sell price policy</div>
                </div>
            </div>
        </div>
        <div class="col-12 col-md-4">
            <div class="card card-sm h-100">
                <div class="card-body">
                    <div class="subheader">Calculated</div>
                    <div class="h3 mb-0">Margin %</div>
                    <div class="small text-secondary">Parts gross profit from your markup</div>
                </div>
            </div>
        </div>
        <div class="col-12 col-md-4">
            <div class="card card-sm h-100">
                <div class="card-body">
                    <div class="subheader">Target</div>
                    <div class="h3 mb-0">{{ App\Ark\Operations\Settings\ShopSettings::SURVIVABILITY_TARGETS['parts_gp'] }}</div>
                    <div class="small text-secondary">Parts GP health</div>
                </div>
            </div>
        </div>
    </div>

    <p class="text-secondary small">
        Markup is the editable pricing policy. Margin is calculated visibility for parts GP health. Matrices affect new part sell pricing; line overrides remain operational exceptions, not hidden settings behavior.
        <a href="{{ route('operations.owner.parts-matrix-tune') }}" class="fw-medium">Matrix tune assistant</a>
        - simulate tier changes from closed part-line history before saving live policy.
    </p>

    @error('parts_matrices')
        <div class="alert alert-danger" role="alert">{{ $message }}</div>
    @enderror

    {{-- Match Shop Settings left-tab nav (same pattern Labor content sits beside): card + list-group-flush + active item. --}}
    <div class="row g-3">
        <div class="col-12 col-lg-3 col-xxl-2">
            <nav class="card sticky-lg-top" style="top: 1rem" aria-label="Parts matrices" data-parts-matrix-nav>
                <div class="card-header py-2">
                    <div class="subheader">Parts matrices</div>
                </div>
                <div class="list-group list-group-flush">
                    <template x-for="matrix in matrices" :key="matrix.key">
                        <button
                            type="button"
                            class="list-group-item list-group-item-action"
                            :class="{ 'active': selectedKey === matrix.key }"
                            :aria-current="selectedKey === matrix.key ? 'page' : null"
                            :data-parts-matrix-tab="matrix.key"
                            @click="selectMatrix(matrix.key)"
                        >
                            <span x-text="matrix.name"></span>
                            <span class="badge bg-secondary-lt ms-1" x-show="matrix.key === defaultPartsMatrixKey" x-cloak>Default</span>
                        </button>
                    </template>
                </div>
            </nav>
            <p class="small text-secondary mt-2 mb-0 d-lg-none">Select a matrix above, then edit its pricing tiers.</p>
        </div>

        <div class="col-12 col-lg-9 col-xxl-10">
            <form
                id="parts-matrix-form"
                method="POST"
                action="{{ route('operations.settings.shop.parts-matrices.update', $defaultPartsMatrixKey) }}"
                :action="updateAction"
                data-parts-matrix-editor
            >
                @csrf
                @method('PATCH')

                {{-- Non-selected matrices stay in Alpine state and are serialized on save; only the selected matrix is edited here. --}}
                <template x-for="(matrix, matrixIndex) in matrices" :key="'fields-'+matrix.key">
                    <template x-if="selectedKey === matrix.key">
                        <div :data-parts-matrix="matrix.key">
                            <div class="card card-sm">
                                <div class="card-header">
                                    <div class="row g-2 align-items-center flex-fill">
                                        <div class="col">
                                            <input type="hidden" :name="`parts_matrices[${matrixIndex}][key]`" :value="matrix.key">
                                            <label class="form-label visually-hidden" :for="'parts-matrix-name-'+matrix.key">Matrix name</label>
                                            <input
                                                :id="'parts-matrix-name-'+matrix.key"
                                                data-matrix-name-input
                                                :name="`parts_matrices[${matrixIndex}][name]`"
                                                x-model="matrix.name"
                                                @input="queueAutosave()"
                                                required
                                                maxlength="64"
                                                aria-label="Matrix name"
                                                class="form-control fw-medium"
                                            >
                                        </div>
                                        <div class="col-auto">
                                            <label class="form-check mb-0">
                                                <input
                                                    type="radio"
                                                    name="default_parts_matrix_key"
                                                    :value="matrix.key"
                                                    x-model="defaultPartsMatrixKey"
                                                    @change="queueAutosave()"
                                                    class="form-check-input"
                                                >
                                                <span class="form-check-label">Default</span>
                                            </label>
                                        </div>
                                    </div>
                                </div>

                                <div class="table-responsive">
                                    <table class="table table-vcenter card-table mb-0">
                                        <thead>
                                            <tr>
                                                <th scope="col">Cost from</th>
                                                <th scope="col">Cost to</th>
                                                <th scope="col">Markup %</th>
                                                <th scope="col">Margin</th>
                                                <th scope="col" class="w-1">Action</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <template x-for="(row, rowIndex) in matrix.rows" :key="row.uid">
                                                <tr @input="queueAutosave()" @change="queueAutosave()">
                                                    <td style="min-width: 9rem">
                                                        <input
                                                            :name="`parts_matrices[${matrixIndex}][rows][${rowIndex}][min_cost]`"
                                                            x-model="row.min_cost"
                                                            required
                                                            inputmode="decimal"
                                                            :aria-label="`Cost from, row ${rowIndex + 1}`"
                                                            class="form-control font-monospace"
                                                        >
                                                    </td>
                                                    <td style="min-width: 9rem">
                                                        <input
                                                            :name="`parts_matrices[${matrixIndex}][rows][${rowIndex}][max_cost]`"
                                                            x-model="row.max_cost"
                                                            inputmode="decimal"
                                                            placeholder="No upper limit"
                                                            :aria-label="`Cost to, row ${rowIndex + 1}`"
                                                            class="form-control font-monospace"
                                                        >
                                                    </td>
                                                    <td style="min-width: 8rem">
                                                        <input
                                                            :name="`parts_matrices[${matrixIndex}][rows][${rowIndex}][markup_percentage]`"
                                                            x-model="row.markup_percentage"
                                                            inputmode="decimal"
                                                            :aria-label="`Markup percent, row ${rowIndex + 1}`"
                                                            class="form-control font-monospace"
                                                        >
                                                    </td>
                                                    <td class="text-secondary text-nowrap">
                                                        <span x-text="row.margin_percentage ? `${row.margin_percentage}% saved` : 'Save for margin'"></span>
                                                    </td>
                                                    <td>
                                                        <input type="hidden" :name="`parts_matrices[${matrixIndex}][rows][${rowIndex}][sort_order]`" :value="rowIndex + 1">
                                                        <button
                                                            type="button"
                                                            @click="removeRow(matrixIndex, rowIndex)"
                                                            :disabled="matrix.rows.length === 1"
                                                            class="btn btn-sm btn-ghost-danger"
                                                        >
                                                            Remove
                                                        </button>
                                                    </td>
                                                </tr>
                                            </template>
                                        </tbody>
                                    </table>
                                </div>

                                <div class="card-footer">
                                    <div class="d-flex flex-wrap align-items-center justify-content-between gap-2">
                                        <div class="d-flex flex-wrap align-items-center gap-2">
                                            <button type="button" @click="addRow(matrixIndex)" class="btn btn-sm">Add Row</button>
                                            <template x-if="canDelete(matrix)">
                                                <button
                                                    type="button"
                                                    x-show="! deleteConfirming"
                                                    @click="openDeleteConfirm(matrix)"
                                                    class="btn btn-sm btn-ghost-secondary"
                                                >
                                                    Delete matrix…
                                                </button>
                                            </template>
                                            <template x-if="! canDelete(matrix) && matrix.key === defaultPartsMatrixKey && matrices.length > 1">
                                                <span class="small text-secondary">Default matrix</span>
                                            </template>
                                        </div>
                                        <div class="d-flex flex-wrap align-items-center gap-2">
                                            <span id="parts-matrix-status" class="small text-secondary" role="status" x-text="statusText">Autosaves edits</span>
                                            <button type="button" @click="autosave(true)" class="btn btn-sm">
                                                <span>Save <span x-text="matrix.name"></span></span>
                                            </button>
                                        </div>
                                    </div>
                                </div>

                                <div x-show="deleteConfirming && deleteMatrixKey === matrix.key" x-cloak class="card-footer bg-danger-lt" data-parts-matrix-delete>
                                    <p class="small mb-2">
                                        Type <strong x-text="deleteMatrixName"></strong> to permanently delete this matrix. Billing classes using it fall back to shop default. Existing part lines keep stored pricing.
                                    </p>
                                    <div class="row g-2 align-items-end">
                                        <div class="col-12 col-sm">
                                            <label :for="'parts-matrix-delete-confirm-'+matrix.key" class="form-label">Confirm matrix name</label>
                                            <input
                                                :id="'parts-matrix-delete-confirm-'+matrix.key"
                                                x-model="deleteTypedName"
                                                x-ref="deleteConfirmInput"
                                                autocomplete="off"
                                                spellcheck="false"
                                                class="form-control"
                                            >
                                        </div>
                                        <div class="col-12 col-sm-auto d-flex gap-2">
                                            <button type="button" @click="cancelDeleteConfirm()" class="btn">Cancel</button>
                                            <button
                                                type="button"
                                                :disabled="! canConfirmDelete()"
                                                @click="submitDelete()"
                                                class="btn btn-danger"
                                            >
                                                Delete permanently
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </template>
                </template>
            </form>
        </div>
    </div>
</div>
