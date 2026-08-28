@php
    use App\Ark\Operations\Settings\ShopSettings;

    $shop ??= ShopSettings::current();
    $phoneTel = preg_replace('/\D+/', '', (string) $shop->phone) ?: '7194136227';
    $trackPublicSurfaceEvents = request()->routeIs('public.*');
    $bookUrl = \Illuminate\Support\Facades\Route::has('public.book')
        ? route('public.book')
        : null;
@endphp

{{-- Persistent mobile: Call · Book only. Text lives in the menu. --}}
<div
    id="public-mobile-contact-bar"
    class="public-mobile-contact-bar md:hidden"
    aria-hidden="true"
    hidden
>
    <div class="public-mobile-contact-bar__actions public-mobile-contact-bar__actions--duo">
        <a
            href="tel:{{ $phoneTel }}"
            @if ($trackPublicSurfaceEvents) data-public-surface-call @endif
            class="public-mobile-contact-bar__call"
        >
            Call
        </a>
        @if (filled($bookUrl))
            <a
                href="{{ $bookUrl }}"
                class="public-mobile-contact-bar__book"
            >
                Book
            </a>
        @endif
    </div>
</div>
