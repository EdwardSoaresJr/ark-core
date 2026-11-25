@php
    use App\Ark\Operations\Inspections\InspectionRequirement;
    use App\Ark\Operations\Inspections\InspectionRequirementResolver;

    $current = old(
        'inspection_lifecycle_policy',
        $settings->{InspectionRequirementResolver::SHOP_SETTING_KEY} ?? InspectionRequirement::Optional->value,
    );
    if (! is_string($current) || InspectionRequirement::tryFrom($current) === null) {
        $current = InspectionRequirement::Optional->value;
    }
@endphp

<form method="POST" action="{{ route('operations.settings.shop.inspection-lifecycle-policy.update') }}" class="mb-4">
    @csrf
    @method('PATCH')

    <div class="hr-text mt-0">When inspections are required</div>
    <p class="text-secondary small mb-3">
        Creating a repair order does not obligate an inspection. Opening a checklist or recording one finding does not make the rest mandatory.
        Choose how this shop treats unfinished inspections at closeout.
    </p>

    <div class="form-selectgroup form-selectgroup-boxes d-flex flex-column flex-lg-row">
        @foreach (InspectionRequirement::cases() as $policy)
            <label class="form-selectgroup-item flex-fill">
                <input
                    type="radio"
                    name="inspection_lifecycle_policy"
                    value="{{ $policy->value }}"
                    class="form-selectgroup-input"
                    @checked($current === $policy->value)
                    required
                >
                <span class="form-selectgroup-label d-flex align-items-start p-3 h-100">
                    <span class="me-2 mt-1">
                        <span class="form-selectgroup-check"></span>
                    </span>
                    <span class="form-selectgroup-label-content text-start">
                        <span class="d-flex align-items-center gap-2 mb-1">
                            <span class="fw-medium">{{ $policy->label() }}</span>
                            @if ($policy === InspectionRequirement::Optional)
                                <span class="badge bg-secondary-lt">Default</span>
                            @endif
                        </span>
                        <span class="text-secondary small d-block">{{ $policy->description() }}</span>
                    </span>
                </span>
            </label>
        @endforeach
    </div>

    @error('inspection_lifecycle_policy')
        <div class="invalid-feedback d-block mt-2">{{ $message }}</div>
    @enderror

    <div class="mt-3">
        <button type="submit" class="btn btn-primary">Save inspection policy</button>
        <span class="form-hint d-inline-block ms-2 mb-0">
            Changing this does not rewrite open repair orders that already have their own Required / Optional override or waiver.
        </span>
    </div>
</form>
