@php
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

            @foreach ($accountMenu as $entry)
                @switch($entry['key'])
                    @case('switch-station')
                        <button type="button" class="dropdown-item" data-ark-workstation-switch>{{ $entry['label'] }}</button>
                        @break
                    @case('change-pin')
                        <button
                            type="button"
                            class="dropdown-item"
                            data-ark-workstation-change-pin
                            data-profile-pin-url="{{ $entry['url'] }}"
                        >{{ $entry['label'] }}</button>
                        @break
                    @case('profile')
                        <a href="{{ $entry['url'] }}" class="dropdown-item">
                            <x-tabler.icon name="user" class="dropdown-item-icon" />
                            {{ $entry['label'] }}
                        </a>
                        @break
                    @case('theme')
                        <button type="button" class="dropdown-item" onclick="window.arkToggleTheme()">
                            <span class="hide-theme-dark"><x-tabler.icon name="moon" class="dropdown-item-icon" />{{ $entry['label'] }}</span>
                            <span class="hide-theme-light"><x-tabler.icon name="sun" class="dropdown-item-icon" />{{ $entry['alternate_label'] }}</span>
                        </button>
                        @break
                    @case('sign-out')
                        <div class="dropdown-divider"></div>

                        <form method="POST" action="{{ $entry['url'] }}">
                            @csrf
                            <button type="submit" class="dropdown-item text-danger">
                                <x-tabler.icon name="logout" class="dropdown-item-icon" />
                                {{ $entry['label'] }}
                            </button>
                        </form>
                        @break
                    @default
                        @if ($entry['kind'] === 'link')
                            <a href="{{ $entry['url'] }}" class="dropdown-item">{{ $entry['label'] }}</a>
                        @else
                            <form method="POST" action="{{ $entry['url'] }}">
                                @csrf
                                <button type="submit" class="dropdown-item">
                                    {{ $entry['label'] }}
                                </button>
                            </form>
                        @endif
                @endswitch
            @endforeach
        </div>
    </div>
@endif
