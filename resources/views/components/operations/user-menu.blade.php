@props([
    'devRolePretendVisible' => false,
    'devRolePretendActive' => false,
    'avatarInitials' => null,
    'avatarColor' => null,
    'showWorkstationSwitch' => false,
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

            @if ($showWorkstationSwitch)
                <button
                    type="button"
                    class="ops-topbar-user-menu__item ops-topbar-user-menu__item--button"
                    role="menuitem"
                    data-ark-workstation-switch
                >
                    Switch station
                </button>
            @endif

            @if ($menuUser->hasOperatorPin())
                <button
                    type="button"
                    class="ops-topbar-user-menu__item ops-topbar-user-menu__item--button"
                    role="menuitem"
                    data-ark-workstation-change-pin
                    data-profile-pin-url="{{ route('profile.edit', ['tab' => 'workstation-pin']) }}"
                >
                    Change station PIN
                </button>
            @endif

            <a href="{{ route('profile.edit') }}" class="ops-topbar-user-menu__item" role="menuitem">Profile</a>

            <button
                type="button"
                class="ops-topbar-user-menu__item ops-topbar-user-menu__item--button"
                role="menuitem"
                onclick="window.arkToggleTheme()"
            >
                <span class="dark:hidden">Dark mode</span>
                <span class="hidden dark:inline">Light mode</span>
            </button>

            @if ($devRolePretendVisible)
                @if ($devRolePretendActive)
                    <form method="POST" action="{{ route('dev-role-pretend.clear') }}" class="ops-topbar-user-menu__form" role="none">
                        @csrf
                        <button type="submit" class="ops-topbar-user-menu__item ops-topbar-user-menu__item--button" role="menuitem">
                            Return to admin access
                        </button>
                    </form>
                @else
                    <form method="POST" action="{{ route('dev-role-pretend.technician') }}" class="ops-topbar-user-menu__form" role="none">
                        @csrf
                        <button type="submit" class="ops-topbar-user-menu__item ops-topbar-user-menu__item--button" role="menuitem">
                            Test as technician
                        </button>
                    </form>
                @endif
            @endif

            <form method="POST" action="{{ route('logout') }}" class="ops-topbar-user-menu__form" role="none">
                @csrf
                <button type="submit" class="ops-topbar-user-menu__item ops-topbar-user-menu__item--button ops-topbar-user-menu__item--danger" role="menuitem">
                    Sign out
                </button>
            </form>
        </div>
    </details>
@endauth
