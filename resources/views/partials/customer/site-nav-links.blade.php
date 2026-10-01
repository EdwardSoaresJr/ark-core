@foreach ($navItems as $item)
    <a
        href="{{ $item['href'] }}"
        @class([
            'customer-header__nav-link',
            'customer-header__nav-link--active' => $item['active'],
            'customer-header__nav-link--book' => $item['appointment'] ?? false,
            'customer-header__nav-link--utility' => $item['utility'] ?? false,
        ])
    >
        {{ $item['label'] }}
    </a>
@endforeach

@if (auth('portal')->check())
    <form method="POST" action="{{ route('portal.logout') }}" class="customer-header__sign-out">
        @csrf
        <button type="submit" class="customer-header__sign-out-btn">
            Sign out
        </button>
    </form>
@endif
