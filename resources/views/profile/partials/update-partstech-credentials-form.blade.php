<section>
    <div class="border-b border-slate-200 pb-2">
        <p class="text-[11px] font-bold uppercase tracking-wide text-slate-400">Integrations</p>
        <h2 class="text-base font-black text-slate-950">PartsTech login</h2>
        <p class="mt-0.5 text-xs text-slate-500">
            Optional personal PartsTech seat. ARK prepares carts and pulls quotes with this login — sign into PartsTech in your browser as the same user. Leave blank to use the shop PartsTech account.
        </p>
    </div>

    <form method="post" action="{{ route('profile.partstech.update') }}" class="mt-4 max-w-2xl space-y-4">
        @csrf
        @method('patch')

        <div class="grid gap-5 sm:grid-cols-2">
            <div>
                <x-input-label for="partstech_username" value="PartsTech username" class="text-xs font-semibold uppercase tracking-wide text-slate-500" />
                <x-text-input
                    id="partstech_username"
                    name="partstech_username"
                    type="text"
                    class="mt-1 block w-full border-slate-300 text-sm text-slate-950 focus:border-slate-500 focus:ring-slate-500"
                    :value="old('partstech_username', $user->partstech_username)"
                    autocomplete="off"
                />
                <x-input-error class="mt-2" :messages="$errors->get('partstech_username')" />
            </div>

            <div>
                <x-input-label for="partstech_password" value="PartsTech password" class="text-xs font-semibold uppercase tracking-wide text-slate-500" />
                <x-text-input
                    id="partstech_password"
                    name="partstech_password"
                    type="password"
                    class="mt-1 block w-full border-slate-300 text-sm text-slate-950 focus:border-slate-500 focus:ring-slate-500"
                    placeholder="{{ $user->hasStoredPartsTechPassword() ? 'Leave blank to keep current password' : 'Required with username' }}"
                    autocomplete="new-password"
                />
                <x-input-error class="mt-2" :messages="$errors->get('partstech_password')" />
            </div>
        </div>

        @if ($user->usesPersonalPartsTechLogin())
            <p class="text-sm text-slate-600">Using personal login <strong>{{ $user->partstech_username }}</strong>. Clear username to revert to the shop default.</p>
        @endif

        <div class="flex items-center gap-4">
            <button type="submit" class="min-h-10 rounded-md bg-slate-950 px-5 text-sm font-semibold text-white hover:bg-slate-800">
                Save PartsTech login
            </button>

            @if (session('status') === 'partstech-updated')
                <p
                    x-data="{ show: true }"
                    x-show="show"
                    x-transition
                    x-init="setTimeout(() => show = false, 2000)"
                    class="text-sm text-slate-600"
                >Saved.</p>
            @endif
        </div>
    </form>
</section>
