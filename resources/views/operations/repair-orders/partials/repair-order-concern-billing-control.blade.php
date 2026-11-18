@php
    use App\Ark\Operations\EstimatePricing\CanonicalBillingClassCatalog;
    use App\Ark\Operations\RepairOrders\ConcernBillingPosture;
    use App\Ark\Operations\Settings\ShopSettings;

    $shopSettings = ShopSettings::current();
    $concern->setRelation('repairOrder', $repairOrder ?? $concern->repairOrder);
    $inheritsCustomer = $concern->inheritsCustomerBilling();
    $effectiveKey = $concern->effectiveBillingClassKey();
    $billingClassLabel = $concern->billingClassLabel();
    $billingSourceLabel = $concern->billingClassSourceLabel();
    $posture = $concern->billing_posture;
    $laborRate = $laborRate ?? null;
    $billingDetail = function (array $presentation): string {
        return '$'.$presentation['rate'].'/hr · '.$presentation['matrix_name'].' · '.$presentation['shop_fee_summary'];
    };
    $optionPosture = function (string $laborColumn) use ($shopSettings): ConcernBillingPosture {
        return ConcernBillingPosture::tryFrom($laborColumn)
            ?? ConcernBillingPosture::fromStored($shopSettings->customLaborBillingClass($laborColumn)['base_posture'] ?? null);
    };
    $laborRateFor = function (string $laborColumn) use ($shopSettings): string {
        return number_format(
            app(App\Ark\Operations\Labor\LaborAuthority::class)->policyRateCentsFor(
                $shopSettings->defaultLaborCategory()['key'],
                $laborColumn === ConcernBillingPosture::CustomerPay->value ? ConcernBillingPosture::Default : $laborColumn,
                $shopSettings,
            ) / 100,
            2,
            '.',
            '',
        );
    };
    $currentBilling = $shopSettings->billingPostureOptionPresentation($optionPosture($effectiveKey), $repairOrder ?? null);
    $currentBilling['rate'] = $laborRateFor($effectiveKey);
    $currentBilling['title'] = $billingSourceLabel.' · '.$currentBilling['title'];
    $billingOptions = CanonicalBillingClassCatalog::selectableForConcerns($shopSettings);
    $canChangeBilling = auth()->user()?->can(App\Ark\Runtime\Authorization\ArkCapability::RepairOrdersManage->value) ?? false;
@endphp

@if ($isTerminal ?? false)
    <div class="d-flex align-items-center gap-1">
        <span class="subheader">{{ $billingClassLabel }}</span>
        @if ($laborRate)
            <span class="small text-secondary">${{ $laborRate }}/hr</span>
        @endif
        <x-operations.help-tip
            :text="$posture->helpText()"
            label="Billing help for {{ $concern->summary }}"
        />
    </div>
@elseif (! $canChangeBilling)
    <span class="d-inline-flex align-items-center gap-1 small" title="{{ $currentBilling['title'] }}" data-billing-readonly="{{ $effectiveKey }}">
        <x-tabler.icon name="receipt" class="text-secondary" />
        <span class="fw-medium">{{ $billingSourceLabel }}</span>
        <span class="visually-hidden">: {{ $billingDetail($currentBilling) }}</span>
    </span>
@else
    <form
        method="POST"
        action="{{ route('operations.repair-orders.concerns.billing-posture', [$repairOrder, $concern]) }}"
        data-refresh-scope="worksheet"
        data-continuity-focus="#concern-{{ $concern->id }} [data-concern-billing-choices] .dropdown-toggle"
        @submit.prevent="submitWorksheetForm($event)"
    >
        @csrf
        @method('PATCH')
        <input type="hidden" name="{{ App\Ark\Operations\RepairOrders\RepairOrderConcurrency::FIELD }}" value="{{ $estimateVersion }}">
        <input type="hidden" name="billing_posture" value="{{ $effectiveKey }}">
        <div class="dropdown" data-concern-billing-choices>
            <button
                type="button"
                class="btn btn-sm dropdown-toggle"
                data-bs-toggle="dropdown"
                aria-expanded="false"
                aria-label="Billing for {{ $concern->summary }}: {{ $billingSourceLabel }}, {{ $billingDetail($currentBilling) }}"
                title="{{ $currentBilling['title'] }}"
                data-billing-current="{{ $effectiveKey }}"
                data-billing-source="{{ $inheritsCustomer ? 'customer' : 'override' }}"
            >
                <x-tabler.icon name="receipt" class="text-secondary me-1" />
                <span class="fw-medium">{{ $billingSourceLabel }}</span>
            </button>
            <div class="dropdown-menu">
                <h6 class="dropdown-header">Billing</h6>
                @unless ($inheritsCustomer)
                    <button
                        type="button"
                        class="dropdown-item"
                        data-billing-option="inherit"
                        @click="preserveRepairOrderConcernScroll({{ $concern->id }}); $el.form.elements.billing_posture.value = 'inherit'; $el.form.requestSubmit()"
                    >
                        <span class="d-flex flex-column me-3">
                            <span>Use customer default</span>
                            <span class="small text-secondary">Follow {{ $repairOrder->customer?->billingClassName() ?? 'Retail' }}</span>
                        </span>
                    </button>
                    <div class="dropdown-divider"></div>
                @endunless
                @foreach ($billingOptions as $option)
                    @php
                        $billingOption = $shopSettings->billingPostureOptionPresentation($optionPosture($option['labor_column_key']), $repairOrder ?? null);
                        $billingOption['rate'] = $laborRateFor($option['labor_column_key']);
                        $isCurrent = ! $inheritsCustomer && $option['labor_column_key'] === $effectiveKey;
                    @endphp
                    <button
                        type="button"
                        class="dropdown-item"
                        title="{{ $option['label'] }} · ${{ $billingOption['rate'] }}/hr"
                        data-billing-option="{{ $option['labor_column_key'] }}"
                        @if ($isCurrent)
                            aria-current="true"
                        @else
                            @click="preserveRepairOrderConcernScroll({{ $concern->id }}); $el.form.elements.billing_posture.value = @js($option['labor_column_key']); $el.form.requestSubmit()"
                        @endif
                    >
                        <span class="d-flex flex-column me-3">
                            <span>{{ $option['label'] }}</span>
                            <span class="small text-secondary">{{ $billingDetail($billingOption) }}</span>
                        </span>
                        @if ($isCurrent)
                            <x-tabler.icon name="check" class="ms-auto text-primary" />
                        @endif
                    </button>
                @endforeach
            </div>
        </div>
    </form>
@endif
