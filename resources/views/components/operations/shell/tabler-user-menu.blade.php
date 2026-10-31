@php
    // Same actions as components/operations/user-menu.blade.php. Change both together.
    /** @var \App\Models\User|null $menuUser */
    /** @var \App\Models\User|null $avatarUser */
@endphp

@if ($menuUser)
    <div class="nav-item dropdown">
        <a
            href="#"
            class="nav-link d-flex lh-1 p-0 px-2"
            data-bs-toggle="dropdown"
            aria-expanded="false"
            aria-label="Account menu for {{ $menuUser->name }}"
            title="{{ $menuUser->name }}{{ $menuUser->roles->isNotEmpty() ? ' · '.$menuUser->roles->pluck('name')->join(', ') : '' }}{{ $devRolePretendActive ? ' · testing as technician' : '' }}"
        >
            <span class="avatar avatar-sm text-white" style="background-color: {{ $avatarUser?->presenceAvatarColor() }}">{{ $avatarUser?->presenceAvatarInitials() }}</span>
            <span class="d-none d-xl-block ps-2">
                <span class="d-block">{{ $menuUser->name }}</span>
                @if ($menuUser->roles->isNotEmpty())
                    <span class="d-block mt-1 small text-secondary">{{ $menuUser->roles->pluck('name')->join(', ') }}</span>
                @endif
            </span>
        </a>
        <div class="dropdown-menu dropdown-menu-end dropdown-menu-arrow">
            <span class="dropdown-item-text small text-secondary">{{ $menuUser->email }}</span>

            @if ($workstationPresence->workstation !== null)
                <button type="button" class="dropdown-item" data-ark-workstation-switch>Switch station</button>
            @endif

            @if ($menuUser->hasOperatorPin())
                <button
                    type="button"
                    class="dropdown-item"
                    data-ark-workstation-change-pin
                    data-profile-pin-url="{{ route('profile.edit', ['tab' => 'workstation-pin']) }}"
                >Change station PIN</button>
            @endif

            <a href="{{ route('profile.edit') }}" class="dropdown-item">
                <x-tabler.icon name="user" class="dropdown-item-icon" />
                Profile
            </a>

            <button type="button" class="dropdown-item" onclick="window.arkToggleTheme()">
                <span class="hide-theme-dark"><x-tabler.icon name="moon" class="dropdown-item-icon" />Dark mode</span>
                <span class="hide-theme-light"><x-tabler.icon name="sun" class="dropdown-item-icon" />Light mode</span>
            </button>

            @if ($devRolePretendVisible)
                <form method="POST" action="{{ $devRolePretendActive ? route('dev-role-pretend.clear') : route('dev-role-pretend.technician') }}">
                    @csrf
                    <button type="submit" class="dropdown-item">
                        {{ $devRolePretendActive ? 'Return to admin access' : 'Test as technician' }}
                    </button>
                </form>
            @endif

            <div class="dropdown-divider"></div>

            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <button type="submit" class="dropdown-item text-danger">
                    <x-tabler.icon name="logout" class="dropdown-item-icon" />
                    Sign out
                </button>
            </form>
        </div>
    </div>
@endif
