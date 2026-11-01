<x-operations.app title="Profile Settings" ui="tabler">
    @php
        $profileTabs = [
            'profile' => 'Profile',
            'appearance' => 'Appearance',
            'password' => 'Password',
            'workstation-pin' => 'Station PIN',
            'partstech' => 'PartsTech',
            'catalogs' => 'Catalogs',
        ];
    @endphp

    <x-tabler.page
        layout="focused"
        pretitle="User settings"
        title="Profile settings"
        subtitle="Staff identity, appearance, password, station PIN, PartsTech seat, and catalog defaults - each tab saves independently."
    >
        <section
            x-data="{
                tab: @js($initialTab),
                init() {
                    if (window.location.hash === '#workstation-pin') {
                        this.tab = 'workstation-pin';
                        this.syncUrl();
                    }
                },
                setTab(nextTab) {
                    this.tab = nextTab;
                    this.syncUrl();
                },
                syncUrl() {
                    const url = new URL(window.location.href);
                    url.searchParams.set('tab', this.tab);
                    url.hash = '';
                    window.history.replaceState({}, '', url);
                },
            }"
            class="d-flex flex-column gap-3"
            data-profile-settings
        >
            @if (session('status') === 'profile-updated' || session('status') === 'appearance-updated' || session('status') === 'password-updated' || session('status') === 'partstech-updated' || session('status') === 'workstation-pin-updated' || session('status') === 'catalogs-updated')
                <div
                    x-data="{ show: true }"
                    x-show="show"
                    x-transition
                    x-init="setTimeout(() => show = false, 2500)"
                    class="alert alert-success mb-0"
                    role="status"
                >
                    Settings saved.
                </div>
            @endif

            <div class="row g-3 align-items-start">
                <div class="col-12 col-lg-4 col-xl-3">
                    <aside class="card" aria-label="Signed in as">
                        <div class="card-body p-3">
                            <div class="subheader">Signed in as</div>
                            <div class="d-flex align-items-center gap-2 mt-2">
                                <span class="avatar bg-primary-lt" aria-hidden="true">{{ str($user->name)->substr(0, 1) }}</span>
                                <div style="min-width: 0">
                                    <div class="fw-bold text-truncate">{{ $user->name }}</div>
                                    <div class="small text-secondary text-truncate">{{ $user->email }}</div>
                                </div>
                            </div>
                        </div>
                        <div class="card-body p-3 border-top">
                            <dl class="datagrid mb-0" style="--tblr-datagrid-item-width: 9rem">
                                <div class="datagrid-item">
                                    <dt class="datagrid-title">Roles</dt>
                                    <dd class="datagrid-content mb-0">{{ $user->roles->pluck('name')->join(', ') ?: 'No assigned role' }}</dd>
                                </div>
                                <div class="datagrid-item">
                                    <dt class="datagrid-title">PartsTech</dt>
                                    <dd class="datagrid-content mb-0">
                                        @if ($user->usesPersonalPartsTechLogin())
                                            Personal · {{ $user->partstech_username }}
                                        @else
                                            Shop default login
                                        @endif
                                    </dd>
                                </div>
                                <div class="datagrid-item">
                                    <dt class="datagrid-title">Station PIN</dt>
                                    <dd class="datagrid-content mb-0">{{ $user->hasOperatorPin() ? 'Configured' : 'Not set - create at station or ask admin' }}</dd>
                                </div>
                                <div class="datagrid-item">
                                    <dt class="datagrid-title">Account</dt>
                                    <dd class="datagrid-content mb-0">{{ $user->created_at?->format('M j, Y') }}</dd>
                                </div>
                            </dl>
                        </div>
                    </aside>
                </div>

                <div class="col-12 col-lg-8 col-xl-9">
                    <div class="card">
                        <div class="card-header d-md-none">
                            <select
                                class="form-select"
                                aria-label="Settings section"
                                x-on:change="setTab($event.target.value)"
                            >
                                @foreach ($profileTabs as $tabKey => $tabLabel)
                                    <option value="{{ $tabKey }}" x-bind:selected="tab === @js($tabKey)" @selected($initialTab === $tabKey)>{{ $tabLabel }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="card-header d-none d-md-flex">
                            <ul class="nav nav-tabs card-header-tabs" role="tablist">
                                @foreach ($profileTabs as $tabKey => $tabLabel)
                                    <li class="nav-item" role="presentation">
                                        <button
                                            type="button"
                                            class="nav-link"
                                            id="profile-tab-{{ $tabKey }}"
                                            role="tab"
                                            aria-controls="profile-panel-{{ $tabKey }}"
                                            :class="{ 'active': tab === @js($tabKey) }"
                                            :aria-selected="tab === @js($tabKey) ? 'true' : 'false'"
                                            @click="setTab(@js($tabKey))"
                                        >{{ $tabLabel }}</button>
                                    </li>
                                @endforeach
                            </ul>
                        </div>

                        <div class="card-body p-2 p-md-3">
                            <div x-show="tab === 'profile'" x-cloak id="profile-panel-profile" role="tabpanel" aria-labelledby="profile-tab-profile">
                                @include('profile.partials.update-profile-identity-form')
                            </div>

                            <div x-show="tab === 'appearance'" x-cloak id="profile-panel-appearance" role="tabpanel" aria-labelledby="profile-tab-appearance">
                                @include('profile.partials.update-profile-appearance-form')
                            </div>

                            <div x-show="tab === 'password'" x-cloak id="profile-panel-password" role="tabpanel" aria-labelledby="profile-tab-password">
                                @include('profile.partials.update-password-form')
                            </div>

                            <div x-show="tab === 'workstation-pin'" x-cloak id="profile-panel-workstation-pin" role="tabpanel" aria-labelledby="profile-tab-workstation-pin">
                                @include('profile.partials.update-workstation-pin-form')
                            </div>

                            <div x-show="tab === 'partstech'" x-cloak id="profile-panel-partstech" role="tabpanel" aria-labelledby="profile-tab-partstech">
                                @include('profile.partials.update-partstech-credentials-form')
                            </div>

                            <div x-show="tab === 'catalogs'" x-cloak id="profile-panel-catalogs" role="tabpanel" aria-labelledby="profile-tab-catalogs">
                                @include('profile.partials.update-parts-catalogs-form')
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>
    </x-tabler.page>
</x-operations.app>
