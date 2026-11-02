@php
    use App\Ark\Operations\Settings\OperationalProfile;
    $currentProfile = $settings->operationalProfile();
@endphp

<div data-settings-operational-profile>
    <h3 class="mb-1">Shop profile</h3>
    <p class="text-secondary small">
        Defaults only - not a different product. Applying a profile updates appointments, Check In visit mode, and printing.
        Staff-based solo behavior still comes from who is on the roster.
    </p>

    <form method="POST" action="{{ route('operations.settings.shop.operational-profile.apply') }}">
        @csrf
        <div class="form-selectgroup form-selectgroup-boxes d-flex flex-column flex-md-row">
            @foreach (OperationalProfile::cases() as $profile)
                <label class="form-selectgroup-item flex-fill">
                    <input
                        type="radio"
                        name="operational_profile"
                        value="{{ $profile->value }}"
                        @checked(old('operational_profile', $currentProfile?->value) === $profile->value)
                        class="form-selectgroup-input"
                        required
                    >
                    <span class="form-selectgroup-label d-flex align-items-start p-3 h-100">
                        <span class="me-3">
                            <span class="form-selectgroup-check"></span>
                        </span>
                        <span class="form-selectgroup-label-content text-start">
                            <span class="d-block fw-medium text-body">
                                {{ $profile->label() }}
                                @if ($currentProfile === $profile)
                                    <span class="badge bg-primary-lt ms-1">Current</span>
                                @endif
                            </span>
                            <span class="d-block small text-secondary mt-1">{{ $profile->summary() }}</span>
                        </span>
                    </span>
                </label>
            @endforeach
        </div>

        @error('operational_profile')
            <div class="invalid-feedback d-block">{{ $message }}</div>
        @enderror

        <div class="d-flex justify-content-end border-top pt-3 mt-3">
            <button type="submit" class="btn btn-primary">
                Apply profile defaults
            </button>
        </div>
    </form>
</div>
