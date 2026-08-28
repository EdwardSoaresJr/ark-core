@php
    $diagnosticsUrl = route('public.common-problems.show', 'car-diagnostics-colorado-springs');
    $brakesUrl = route('public.common-problems.show', 'brake-repair-colorado-springs');
    $maintenanceUrl = route('public.common-problems.show', 'tune-up-colorado-springs');
    $autoRepairUrl = route('public.common-problems.show', 'auto-repair-colorado-springs');
@endphp

<section
    class="public-cp-index-services"
    id="local-services-heading"
    aria-labelledby="public-cp-index-services-heading"
>
    <h2 id="public-cp-index-services-heading" class="public-cp-index-services__title">
        Looking for a specific service?
    </h2>
    <p class="public-cp-index-services__links">
        <a
            href="{{ $diagnosticsUrl }}"
            data-public-surface-common-problem
            data-public-surface-page="common-problems.index"
            data-public-surface-source="index_services_quiet"
            data-public-surface-target="common-problems.car-diagnostics-colorado-springs"
            class="public-cp-index-services__link"
        >Diagnostics</a>
        <span class="public-cp-index-services__sep" aria-hidden="true">·</span>
        <a
            href="{{ $brakesUrl }}"
            data-public-surface-common-problem
            data-public-surface-page="common-problems.index"
            data-public-surface-source="index_services_quiet"
            data-public-surface-target="common-problems.brake-repair-colorado-springs"
            class="public-cp-index-services__link"
        >Brakes</a>
        <span class="public-cp-index-services__sep" aria-hidden="true">·</span>
        <a
            href="{{ $maintenanceUrl }}"
            data-public-surface-common-problem
            data-public-surface-page="common-problems.index"
            data-public-surface-source="index_services_quiet"
            data-public-surface-target="common-problems.tune-up-colorado-springs"
            class="public-cp-index-services__link"
        >Maintenance</a>
        <span class="public-cp-index-services__sep" aria-hidden="true">·</span>
        <a
            href="{{ $autoRepairUrl }}"
            data-public-surface-common-problem
            data-public-surface-page="common-problems.index"
            data-public-surface-source="index_services_quiet"
            data-public-surface-target="common-problems.auto-repair-colorado-springs"
            class="public-cp-index-services__link"
        >Auto Repair</a>
    </p>
    <p class="public-cp-index-services__more">
        <a
            href="{{ $autoRepairUrl }}"
            data-public-surface-common-problem
            data-public-surface-page="common-problems.index"
            data-public-surface-source="view_all_local_services"
            data-public-surface-target="common-problems.auto-repair-colorado-springs"
            class="public-cp-index-services__view-all"
        >
            View Auto Repair Services →
        </a>
    </p>
</section>
