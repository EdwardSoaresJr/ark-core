@php
    use App\Ark\Operations\EstimatePricing\EffectiveWarrantyResolver;

    $concern->setRelation('repairOrder', $repairOrder ?? $concern->repairOrder);
    $effectiveWarranty = app(EffectiveWarrantyResolver::class)->forConcern($concern);
    $inheritsWarranty = $concern->inheritsClassWarranty();
    $warrantySummary = (string) ($effectiveWarranty['summary'] ?? 'No warranty');
    $warrantySource = (string) ($effectiveWarranty['source'] ?? 'none');
    $canChangeWarranty = auth()->user()?->can(App\Ark\Runtime\Authorization\ArkCapability::RepairOrdersManage->value) ?? false;
    $override = $concern->warrantyOverridePayload() ?? [
        'months' => $effectiveWarranty['warranty']['months'] ?? null,
        'miles' => $effectiveWarranty['warranty']['miles'] ?? null,
        'covers_parts' => (bool) ($effectiveWarranty['warranty']['covers_parts'] ?? false),
        'covers_labor' => (bool) ($effectiveWarranty['warranty']['covers_labor'] ?? false),
        'description' => (string) ($effectiveWarranty['warranty']['description'] ?? ''),
        'exclusions' => (string) ($effectiveWarranty['warranty']['exclusions'] ?? ''),
        'third_party' => (bool) ($effectiveWarranty['warranty']['third_party'] ?? false),
    ];
@endphp

@if ($isTerminal ?? false)
    <div class="d-flex align-items-center gap-1" data-concern-warranty-readonly>
        <span class="subheader">Warranty</span>
        <span class="small text-secondary">{{ $warrantySummary }}</span>
    </div>
@elseif (! $canChangeWarranty)
    <span class="d-inline-flex align-items-center gap-1 small" title="{{ $warrantySummary }}" data-concern-warranty-readonly>
        <x-tabler.icon name="circle-check" class="text-secondary" />
        <span class="fw-medium">{{ $inheritsWarranty ? 'Class warranty' : 'Warranty override' }}</span>
        <span class="visually-hidden">: {{ $warrantySummary }}</span>
    </span>
@else
    <div class="dropdown" data-concern-warranty-choices>
        <button
            type="button"
            class="btn btn-sm dropdown-toggle"
            data-bs-toggle="dropdown"
            data-bs-auto-close="outside"
            aria-expanded="false"
            aria-label="Warranty for {{ $concern->summary }}: {{ $warrantySummary }}"
            title="{{ $warrantySummary }}"
            data-warranty-source="{{ $warrantySource }}"
            data-warranty-inherits="{{ $inheritsWarranty ? '1' : '0' }}"
        >
            <x-tabler.icon name="circle-check" class="text-secondary me-1" />
            <span class="fw-medium">{{ $inheritsWarranty ? 'Warranty' : 'Warranty override' }}</span>
            <span class="small text-secondary ms-1 d-none d-xl-inline">{{ \Illuminate\Support\Str::limit($warrantySummary, 28) }}</span>
        </button>
        <div class="dropdown-menu dropdown-menu-card p-3" style="min-width: 18rem;">
            <div class="mb-2">
                <div class="subheader mb-1">Effective warranty</div>
                <div class="small">{{ $warrantySummary }}</div>
                <div class="form-hint mb-0">
                    @if ($inheritsWarranty)
                        Inherited from the billing class on this repair order’s frozen pricing.
                    @else
                        Concern override — restore inheritance to follow the class again.
                    @endif
                </div>
            </div>

            @unless ($inheritsWarranty)
                <form
                    method="POST"
                    action="{{ route('operations.repair-orders.concerns.warranty', [$repairOrder, $concern]) }}"
                    class="mb-2"
                    data-refresh-scope="worksheet"
                    data-continuity-focus="#concern-{{ $concern->id }} [data-concern-warranty-choices] .dropdown-toggle"
                    @submit.prevent="submitWorksheetForm($event)"
                >
                    @csrf
                    @method('PATCH')
                    <input type="hidden" name="{{ App\Ark\Operations\RepairOrders\RepairOrderConcurrency::FIELD }}" value="{{ $estimateVersion }}">
                    <input type="hidden" name="action" value="inherit">
                    <button type="submit" class="btn btn-outline-primary btn-sm w-100" data-warranty-action="inherit">
                        Restore class inheritance
                    </button>
                </form>
            @endunless

            <form
                method="POST"
                action="{{ route('operations.repair-orders.concerns.warranty', [$repairOrder, $concern]) }}"
                data-refresh-scope="worksheet"
                data-continuity-focus="#concern-{{ $concern->id }} [data-concern-warranty-choices] .dropdown-toggle"
                @submit.prevent="submitWorksheetForm($event)"
            >
                @csrf
                @method('PATCH')
                <input type="hidden" name="{{ App\Ark\Operations\RepairOrders\RepairOrderConcurrency::FIELD }}" value="{{ $estimateVersion }}">
                <input type="hidden" name="action" value="override">
                <div class="subheader mb-2">{{ $inheritsWarranty ? 'Override for this concern' : 'Edit override' }}</div>
                <div class="row g-2 mb-2">
                    <div class="col-6">
                        <label class="form-label mb-1">Months</label>
                        <input type="number" min="0" name="warranty[months]" class="form-control form-control-sm" value="{{ $override['months'] }}">
                    </div>
                    <div class="col-6">
                        <label class="form-label mb-1">Miles</label>
                        <input type="number" min="0" name="warranty[miles]" class="form-control form-control-sm" value="{{ $override['miles'] }}">
                    </div>
                    <div class="col-12">
                        <label class="form-check">
                            <input class="form-check-input" type="checkbox" name="warranty[covers_parts]" value="1" @checked($override['covers_parts'])>
                            <span class="form-check-label">Covers parts</span>
                        </label>
                        <label class="form-check">
                            <input class="form-check-input" type="checkbox" name="warranty[covers_labor]" value="1" @checked($override['covers_labor'])>
                            <span class="form-check-label">Covers labor</span>
                        </label>
                        <label class="form-check">
                            <input class="form-check-input" type="checkbox" name="warranty[third_party]" value="1" @checked($override['third_party'])>
                            <span class="form-check-label">Third-party</span>
                        </label>
                    </div>
                    <div class="col-12">
                        <label class="form-label mb-1">Description</label>
                        <textarea name="warranty[description]" class="form-control form-control-sm" rows="2">{{ $override['description'] }}</textarea>
                    </div>
                    <div class="col-12">
                        <label class="form-label mb-1">Exclusions</label>
                        <textarea name="warranty[exclusions]" class="form-control form-control-sm" rows="2">{{ $override['exclusions'] }}</textarea>
                    </div>
                </div>
                <button type="submit" class="btn btn-primary btn-sm w-100" data-warranty-action="override">
                    Save concern warranty
                </button>
            </form>
        </div>
    </div>
@endif
