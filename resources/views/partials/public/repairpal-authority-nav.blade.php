@php
    $current = $current ?? '';
    $shopName = $shop->displayName();
@endphp

<nav class="public-repairpal-nav mt-6" aria-label="RepairPal pages">
    <p class="public-repairpal-nav__label">RepairPal at {{ $shopName }}</p>
    <ul class="public-repairpal-nav__list">
        <li>
            <a
                href="{{ route('public.repairpal.certified') }}"
                @if ($current === 'certified') aria-current="page" @endif
                @class([
                    'public-repairpal-nav__link',
                    'public-repairpal-nav__link--current' => $current === 'certified',
                ])
            >Certified</a>
        </li>
        <li>
            <a
                href="{{ route('public.repairpal.reviews') }}"
                @if ($current === 'reviews') aria-current="page" @endif
                @class([
                    'public-repairpal-nav__link',
                    'public-repairpal-nav__link--current' => $current === 'reviews',
                ])
            >Reviews</a>
        </li>
        <li>
            <a
                href="{{ route('public.repairpal.warranty') }}"
                @if ($current === 'warranty') aria-current="page" @endif
                @class([
                    'public-repairpal-nav__link',
                    'public-repairpal-nav__link--current' => $current === 'warranty',
                ])
            >Warranty</a>
        </li>
        <li>
            <a
                href="{{ route('public.repairpal') }}"
                @if ($current === 'hub') aria-current="page" @endif
                @class([
                    'public-repairpal-nav__link',
                    'public-repairpal-nav__link--current' => $current === 'hub',
                ])
            >Overview</a>
        </li>
    </ul>
</nav>
