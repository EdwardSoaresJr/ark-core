@php
    /** @var \App\Models\User|null $menuUser */
    /** @var \App\Models\User|null $avatarUser */
    $roleNames = $menuUser?->roles->pluck('name')->filter()->values() ?? collect();
    $roleLabel = $roleNames->isNotEmpty() ? $roleNames->join(', ') : null;
@endphp

@if ($menuUser)
    <div class="nav-item dropdown">
        <a
            href="#"
            class="nav-link dropdown-toggle d-flex align-items-center lh-1 p-0 px-2"
            data-bs-toggle="dropdown"
            aria-expanded="false"
            aria-haspopup="true"
            aria-label="Account menu for {{ $menuUser->name }}"
            title="{{ $menuUser->name }}{{ $roleLabel ? ' · '.$roleLabel : '' }}{{ ($devRolePretendActive ?? false) ? ' · testing as technician' : '' }}"
        >
            <span class="avatar avatar-sm text-white flex-shrink-0" style="background-color: {{ $avatarUser?->presenceAvatarColor() }}">{{ $avatarUser?->presenceAvatarInitials() }}</span>
            <span class="d-none d-xl-block ps-2 text-start" style="max-width: 12rem">
                <span class="d-block text-truncate">{{ $menuUser->name }}</span>
            </span>
        </a>
        <div class="dropdown-menu dropdown-menu-end dropdown-menu-arrow" role="menu">
            <div class="dropdown-item-text">
                <div class="fw-medium text-truncate">{{ $menuUser->name }}</div>
                <div class="small text-secondary text-truncate">{{ $menuUser->email }}</div>
                @if ($roleLabel)
                    <div class="small text-secondary text-truncate">{{ $roleLabel }}</div>
                @endif
            </div>
            <div class="dropdown-divider"></div>

            @foreach ($accountMenu as $entry)
                @switch($entry['key'])
                    @case('switch-station')
                        <button type="button" class="dropdown-item" role="menuitem" data-ark-workstation-switch>{{ $entry['label'] }}</button>
                        @break
                    @case('change-pin')
                        <button
                            type="button"
                            class="dropdown-item"
                            role="menuitem"
                            data-ark-workstation-change-pin
                            data-profile-pin-url="{{ $entry['url'] }}"
                        >{{ $entry['label'] }}</button>
                        @break
                    @case('profile')
                        <a href="{{ $entry['url'] }}" class="dropdown-item" role="menuitem">
                            <x-tabler.icon name="user" class="dropdown-item-icon" />
                            {{ $entry['label'] }}
                        </a>
                        @break
                    @case('theme')
                        <button type="button" class="dropdown-item" role="menuitem" onclick="window.arkToggleTheme()">
                            <span class="hide-theme-dark"><x-tabler.icon name="moon" class="dropdown-item-icon" />{{ $entry['label'] }}</span>
                            <span class="hide-theme-light"><x-tabler.icon name="sun" class="dropdown-item-icon" />{{ $entry['alternate_label'] }}</span>
                        </button>
                        @break
                    @case('sign-out')
                        <div class="dropdown-divider"></div>

                        <form method="POST" action="{{ $entry['url'] }}">
                            @csrf
                            <button type="submit" class="dropdown-item text-danger" role="menuitem">
                                <x-tabler.icon name="logout" class="dropdown-item-icon" />
                                {{ $entry['label'] }}
                            </button>
                        </form>
                        @break
                    @default
                        @if ($entry['kind'] === 'link')
                            <a href="{{ $entry['url'] }}" class="dropdown-item" role="menuitem">{{ $entry['label'] }}</a>
                        @else
                            <form method="POST" action="{{ $entry['url'] }}">
                                @csrf
                                <button type="submit" class="dropdown-item" role="menuitem">
                                    {{ $entry['label'] }}
                                </button>
                            </form>
                        @endif
                @endswitch
            @endforeach
        </div>
    </div>
@endif
