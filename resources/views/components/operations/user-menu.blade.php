@props([
    'entries' => [],
    'devRolePretendActive' => false,
    'avatarInitials' => null,
    'avatarColor' => null,
])

@auth
    @php
        $menuUser = auth()->user();
        $initials = $avatarInitials ?? $menuUser->presenceAvatarInitials();
        $avatarBackground = $avatarColor ?? $menuUser->presenceAvatarColor();
    @endphp
    <details class="ops-topbar-user-menu">
        <summary
            class="ops-topbar-user-menu__trigger"
            aria-label="Account menu for {{ $menuUser->name }}"
            title="{{ $menuUser->name }}{{ $menuUser->roles->isNotEmpty() ? ' · '.$menuUser->roles->pluck('name')->join(', ') : '' }}{{ $devRolePretendActive ? ' · testing as technician' : '' }}"
        >
            <span class="ops-user-avatar ops-user-avatar--topbar" style="background-color: {{ $avatarBackground }}">{{ $initials }}</span>
        </summary>

        <div class="ops-topbar-user-menu__panel" role="menu">
            <div class="ops-topbar-user-menu__identity">
                <p class="ops-topbar-user-menu__name">{{ $menuUser->name }}</p>
                <p class="ops-topbar-user-menu__email">{{ $menuUser->email }}</p>
            </div>

            @foreach ($entries as $entry)
                @switch($entry['key'])
                    @case('switch-station')
                        <button
                            type="button"
                            class="ops-topbar-user-menu__item ops-topbar-user-menu__item--button"
                            role="menuitem"
                            data-ark-workstation-switch
                        >
                            {{ $entry['label'] }}
                        </button>
                        @break
                    @case('change-pin')
                        <button
                            type="button"
                            class="ops-topbar-user-menu__item ops-topbar-user-menu__item--button"
                            role="menuitem"
                            data-ark-workstation-change-pin
                            data-profile-pin-url="{{ $entry['url'] }}"
                        >
                            {{ $entry['label'] }}
                        </button>
                        @break
                    @case('theme')
                        <button
                            type="button"
                            class="ops-topbar-user-menu__item ops-topbar-user-menu__item--button"
                            role="menuitem"
                            onclick="window.arkToggleTheme()"
                        >
                            <span class="dark:hidden">{{ $entry['label'] }}</span>
                            <span class="hidden dark:inline">{{ $entry['alternate_label'] }}</span>
                        </button>
                        @break
                    @default
                        @if ($entry['kind'] === 'link')
                            <a href="{{ $entry['url'] }}" class="ops-topbar-user-menu__item" role="menuitem">{{ $entry['label'] }}</a>
                        @else
                            <form method="POST" action="{{ $entry['url'] }}" class="ops-topbar-user-menu__form" role="none">
                                @csrf
                                <button type="submit" @class([
                                    'ops-topbar-user-menu__item ops-topbar-user-menu__item--button',
                                    'ops-topbar-user-menu__item--danger' => $entry['key'] === 'sign-out',
                                ]) role="menuitem">
                                    {{ $entry['label'] }}
                                </button>
                            </form>
                        @endif
                @endswitch
            @endforeach
        </div>
    </details>
@endauth
