@php
    $breadcrumb = app(\App\Ark\Customer\CustomerSurfaceBreadcrumbProjection::class)->forCurrentRequest();
@endphp

@if ($breadcrumb !== [])
    <div class="customer-subheader-zone customer-page-inset">
        <div class="customer-subheader">
            @include('partials.customer.breadcrumb-trail', ['breadcrumb' => $breadcrumb])
        </div>
    </div>
@endif
