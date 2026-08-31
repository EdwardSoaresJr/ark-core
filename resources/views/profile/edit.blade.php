<x-operations.app title="Profile Settings">
    @php
        $profileTabs = [
            'profile' => 'Profile',
            'appearance' => 'Appearance',
            'password' => 'Password',
            'workstation-pin' => 'Station PIN',
        ];
    @endphp

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
        class="ops-profile-settings space-y-3"
    >
        <div class="border border-slate-300 bg-white px-3 py-2">
            <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                <div class="min-w-0">
                    <p class="text-[10px] font-bold uppercase tracking-wide text-slate-400">User Settings</p>
                    <p class="mt-0.5 truncate text-xs font-medium text-slate-500">Staff identity, appearance, password, and station PIN — each tab saves independently.</p>
                </div>

                @if (session('status') === 'profile-updated' || session('status') === 'appearance-updated' || session('status') === 'password-updated' || session('status') === 'workstation-pin-updated')
                    <p
                        x-data="{ show: true }"
                        x-show="show"
                        x-transition
                        x-init="setTimeout(() => show = false, 2500)"
                        class="border border-emerald-200 bg-emerald-50 px-3 py-2 text-sm font-medium text-emerald-900"
                    >
                        Settings saved.
                    </p>
                @endif
            </div>
        </div>

        <div class="grid items-start gap-3 lg:grid-cols-[14rem_minmax(0,1fr)]">
            <aside class="border border-slate-300 bg-white p-3">
                <p class="text-[10px] font-bold uppercase tracking-wide text-slate-400">Signed in as</p>
                <div class="mt-3 flex items-center gap-2.5">
                    <div class="ops-user-avatar size-10 text-sm">
                        {{ str($user->name)->substr(0, 1) }}
                    </div>
                    <div class="min-w-0">
                        <p class="truncate text-sm font-semibold text-slate-950">{{ $user->name }}</p>
                        <p class="truncate text-[11px] text-slate-500">{{ $user->email }}</p>
                    </div>
                </div>

                <dl class="mt-4 space-y-2.5 border-t border-slate-200 pt-3 text-sm">
                    <div>
                        <dt class="text-[10px] font-bold uppercase tracking-wide text-slate-400">Roles</dt>
                        <dd class="mt-0.5 text-xs text-slate-700">
                            {{ $user->roles->pluck('name')->join(', ') ?: 'No assigned role' }}
                        </dd>
                    </div>
                    <div>
                        <dt class="text-[10px] font-bold uppercase tracking-wide text-slate-400">Station PIN</dt>
                        <dd class="mt-0.5 text-xs text-slate-700">
                            {{ $user->hasOperatorPin() ? 'Configured' : 'Not set — create at station or ask admin' }}
                        </dd>
                    </div>
                    <div>
                        <dt class="text-[10px] font-bold uppercase tracking-wide text-slate-400">Account</dt>
                        <dd class="mt-0.5 text-xs text-slate-700">{{ $user->created_at?->format('M j, Y') }}</dd>
                    </div>
                </dl>
            </aside>

            <div class="min-w-0 border border-slate-300 bg-white">
                <div class="grid gap-px border-b border-slate-300 bg-slate-300 text-sm sm:grid-cols-2 lg:grid-cols-4">
                    @foreach ($profileTabs as $tabKey => $tabLabel)
                        <button
                            type="button"
                            @click="setTab(@js($tabKey))"
                            :class="tab === @js($tabKey) ? 'bg-slate-950 text-white' : 'bg-white text-slate-600 hover:bg-slate-50 hover:text-slate-950'"
                            class="px-3 py-2 text-left font-semibold"
                        >{{ $tabLabel }}</button>
                    @endforeach
                </div>

                <div class="p-4">
                    <div x-show="tab === 'profile'" x-cloak>
                        @include('profile.partials.update-profile-identity-form')
                    </div>

                    <div x-show="tab === 'appearance'" x-cloak>
                        @include('profile.partials.update-profile-appearance-form')
                    </div>

                    <div x-show="tab === 'password'" x-cloak>
                        @include('profile.partials.update-password-form')
                    </div>

                    <div x-show="tab === 'workstation-pin'" x-cloak>
                        @include('profile.partials.update-workstation-pin-form')
                    </div>
                </div>
            </div>
        </div>
    </section>
</x-operations.app>
