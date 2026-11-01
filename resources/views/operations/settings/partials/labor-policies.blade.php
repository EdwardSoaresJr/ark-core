{{-- Labor Policies - Settings → Financial Rules --}}
@php
    /** @var array $laborPoliciesMatrix */
    /** @var array $laborPolicyPreview */
    $matrix = $laborPoliciesMatrix;
    $preview = $laborPolicyPreview;
    $shopDefault = $matrix['shop_default'] ?? null;
@endphp

<div
    data-settings-labor-policies
    x-data="{
        editing: null,
        openEditor(classKey, className, posture, postureLabel, rateCents, effectiveFrom) {
            const rate = rateCents === null || rateCents === undefined
                ? ''
                : (Number(rateCents) / 100).toFixed(2);
            this.editing = {
                operation_class_key: classKey,
                operation_class_name: className,
                billing_posture: posture,
                posture_label: postureLabel,
                hourly_rate: rate,
                effective_from: effectiveFrom || @js(now()->toDateString()),
                change_reason: '',
            };
        },
        closeEditor() {
            this.editing = null;
        },
    }"
>
    <h3 class="mb-1">Labor Policies</h3>
    <p class="text-secondary small">
        Rows are operation classes. Columns are billing types. Each cell is an independent policy rate.
        Click a cell to edit one policy. The shop currently keeps
        <strong>RepairPal, Warranty, Comeback, and Internal</strong>
        equal across classes by configuration - the matrix does not force that.
    </p>

    @if ($shopDefault)
        <div class="card card-sm mb-3">
            <div class="card-body">
                <div class="subheader">Default labor category</div>
                <div class="fw-medium">
                    {{ $shopDefault['labor_category_name'] }}
                    @if ($shopDefault['operation_class_name'])
                        <span class="text-secondary fw-normal">→</span>
                        {{ $shopDefault['operation_class_name'] }}
                    @endif
                </div>
                <div class="small text-secondary">
                    Owned by Labor Categories. Pricing only displays it.
                    <button type="button" class="btn btn-link p-0 align-baseline small" @click="setFinancialTab('labor')">Change under Financial Rules → Labor</button>.
                </div>
            </div>
        </div>
    @endif

    @error('billing_posture')
        <div class="alert alert-danger" role="alert">{{ $message }}</div>
    @enderror
    @error('hourly_rate')
        <div class="alert alert-danger" role="alert">{{ $message }}</div>
    @enderror
    @error('effective_from')
        <div class="alert alert-danger" role="alert">{{ $message }}</div>
    @enderror

    <div class="card card-sm mb-3">
        <div class="table-responsive">
            <table class="table table-vcenter table-bordered card-table mb-0">
                <thead>
                    <tr>
                        <th scope="col">Operation Class</th>
                        @foreach ($matrix['postures'] as $posture)
                            <th scope="col">{{ $posture['label'] }}</th>
                        @endforeach
                    </tr>
                </thead>
                <tbody>
                    @foreach ($matrix['operation_classes'] as $class)
                        <tr @class(['table-active' => ! empty($class['is_shop_default'])])>
                            <th scope="row" class="text-nowrap">
                                {{ $class['name'] }}
                                @if (! empty($class['is_shop_default']))
                                    <span class="badge bg-secondary-lt ms-1">Default</span>
                                @endif
                            </th>
                            @foreach ($matrix['postures'] as $posture)
                                @php
                                    $cell = $matrix['cells'][$class['key']][$posture['value']] ?? null;
                                @endphp
                                <td class="p-0">
                                    <button
                                        type="button"
                                        class="btn btn-ghost-secondary w-100 justify-content-start rounded-0 font-monospace text-body"
                                        aria-label="Edit {{ $class['name'] }} · {{ $posture['label'] }} rate, now {{ $cell['rate_display'] ?? 'not set' }}"
                                        @click="openEditor(
                                            @js($class['key']),
                                            @js($class['name']),
                                            @js($posture['value']),
                                            @js($posture['label']),
                                            @js($cell['hourly_rate_cents'] ?? null),
                                            @js($cell['effective_from'] ?? null)
                                        )"
                                    >
                                        {{ $cell['rate_display'] ?? '-' }}
                                    </button>
                                </td>
                            @endforeach
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>

    <div class="row g-3">
        <div class="col-12 col-lg-6" x-show="editing" x-cloak>
            <div class="card card-sm h-100" data-labor-policy-editor>
                <template x-if="editing">
                    <form method="POST" action="{{ route('operations.settings.shop.labor-policies.update') }}">
                        @csrf
                        @method('PATCH')
                        <input type="hidden" name="billing_posture" :value="editing.billing_posture">
                        <input type="hidden" name="operation_class_key" :value="editing.operation_class_key">

                        <div class="card-header">
                            <div>
                                <div class="subheader">Edit policy</div>
                                <h4 class="card-title">
                                    <span x-text="editing.operation_class_name"></span>
                                    <span class="text-secondary fw-normal"> · </span>
                                    <span x-text="editing.posture_label"></span>
                                </h4>
                            </div>
                        </div>
                        <div class="card-body">
                            <div class="mb-3" style="max-width: 16rem">
                                <label for="labor-policy-rate" class="form-label">Hourly Rate</label>
                                <div class="input-group">
                                    <span class="input-group-text">$</span>
                                    <input
                                        id="labor-policy-rate"
                                        name="hourly_rate"
                                        x-model="editing.hourly_rate"
                                        required
                                        inputmode="decimal"
                                        class="form-control font-monospace"
                                    >
                                </div>
                            </div>
                            <div class="mb-3" style="max-width: 16rem">
                                <label for="labor-policy-effective" class="form-label">Effective Date</label>
                                <input
                                    id="labor-policy-effective"
                                    type="date"
                                    name="effective_from"
                                    x-model="editing.effective_from"
                                    required
                                    class="form-control"
                                >
                            </div>
                            <div>
                                <label for="labor-policy-reason" class="form-label">Reason <span class="text-secondary fw-normal">(optional)</span></label>
                                <input
                                    id="labor-policy-reason"
                                    name="change_reason"
                                    x-model="editing.change_reason"
                                    maxlength="255"
                                    class="form-control"
                                >
                            </div>
                        </div>
                        <div class="card-footer d-flex justify-content-end gap-2">
                            <button type="button" @click="closeEditor()" class="btn">Cancel</button>
                            <button type="submit" class="btn btn-primary">Save</button>
                        </div>
                    </form>
                </template>
            </div>
        </div>

        <div class="col-12 col-lg-6">
            <div class="card card-sm h-100" data-labor-policy-preview>
                <div class="card-header">
                    <div>
                        <h4 class="card-title">Rate preview</h4>
                        <div class="card-subtitle">Pick a billing type and operation class to see the hourly rate an estimate will use. Nothing is saved here.</div>
                    </div>
                </div>
                <div class="card-body">
                    <form method="GET" action="{{ route('operations.settings.shop.edit') }}">
                        <input type="hidden" name="section" value="financial">
                        <input type="hidden" name="financial-tab" value="labor-policies">

                        <div class="mb-3">
                            <label for="labor-policy-preview-posture" class="form-label">Billing type</label>
                            <select id="labor-policy-preview-posture" name="lp_posture" class="form-select" onchange="this.form.submit()">
                                @foreach ($matrix['postures'] as $posture)
                                    <option value="{{ $posture['value'] }}" @selected($preview['billing_posture'] === $posture['value'])>{{ $posture['label'] }}</option>
                                @endforeach
                            </select>
                        </div>

                        <div class="mb-3">
                            <label for="labor-policy-preview-class" class="form-label">Operation Class</label>
                            <select id="labor-policy-preview-class" name="lp_class" class="form-select" onchange="this.form.submit()">
                                @foreach ($matrix['operation_classes'] as $class)
                                    <option value="{{ $class['key'] }}" @selected($preview['operation_class_key'] === $class['key'])>
                                        {{ $class['name'] }}@if (! empty($class['is_shop_default'])) (Default)@endif
                                    </option>
                                @endforeach
                            </select>
                        </div>
                    </form>

                    <div class="border-top pt-3">
                        <div class="subheader">Hourly rate</div>
                        @if ($preview['error'])
                            <div class="text-danger fw-medium">{{ $preview['error'] }}</div>
                        @else
                            <div class="h1 mb-0 font-monospace" data-labor-policy-preview-rate>{{ $preview['rate_display'] }}</div>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
