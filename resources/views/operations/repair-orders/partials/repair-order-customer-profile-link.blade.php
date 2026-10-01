@php
    $customerProfileHref = $href ?? route('operations.customers.show', $customer);
@endphp

<a
    href="{{ $customerProfileHref }}"
    class="ops-service-lane-profile-link"
    aria-label="Open customer profile"
    title="Open customer profile"
>
    <svg aria-hidden="true" class="ops-service-lane-profile-link__icon" viewBox="0 0 20 20" fill="none">
        <path d="M11 3h5v5M8 12l8-8M14 13v3a1 1 0 01-1 1H5a1 1 0 01-1-1V8a1 1 0 011-1h3" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" />
    </svg>
</a>
