<section>
    <div class="border-b border-slate-200 pb-2">
        <p class="text-[11px] font-bold uppercase tracking-wide text-slate-400">Security</p>
        <h2 class="text-base font-black text-slate-950">{{ __('Update Password') }}</h2>
        <p class="mt-0.5 text-xs text-slate-500">
            {{ __('Use a long password that is not shared with any other shop system.') }}
        </p>
    </div>

    <form method="post" action="{{ route('password.update') }}" class="mt-4 max-w-2xl space-y-4">
        @csrf
        @method('put')

        <div>
            <x-input-label for="update_password_current_password" :value="__('Current Password')" class="text-xs font-semibold uppercase tracking-wide text-slate-500" />
            <x-text-input id="update_password_current_password" name="current_password" type="password" class="mt-1 block w-full border-slate-300 text-sm text-slate-950 focus:border-slate-500 focus:ring-slate-500" autocomplete="current-password" />
            <x-input-error :messages="$errors->updatePassword->get('current_password')" class="mt-2" />
        </div>

        <div>
            <x-input-label for="update_password_password" :value="__('New Password')" class="text-xs font-semibold uppercase tracking-wide text-slate-500" />
            <x-text-input id="update_password_password" name="password" type="password" class="mt-1 block w-full border-slate-300 text-sm text-slate-950 focus:border-slate-500 focus:ring-slate-500" autocomplete="new-password" />
            <x-input-error :messages="$errors->updatePassword->get('password')" class="mt-2" />
        </div>

        <div>
            <x-input-label for="update_password_password_confirmation" :value="__('Confirm Password')" class="text-xs font-semibold uppercase tracking-wide text-slate-500" />
            <x-text-input id="update_password_password_confirmation" name="password_confirmation" type="password" class="mt-1 block w-full border-slate-300 text-sm text-slate-950 focus:border-slate-500 focus:ring-slate-500" autocomplete="new-password" />
            <x-input-error :messages="$errors->updatePassword->get('password_confirmation')" class="mt-2" />
        </div>

        <div class="flex items-center gap-4">
            <button type="submit" class="min-h-10 rounded-md bg-slate-950 px-5 text-sm font-semibold text-white hover:bg-slate-800">
                {{ __('Save Password') }}
            </button>

            @if (session('status') === 'password-updated')
                <p
                    x-data="{ show: true }"
                    x-show="show"
                    x-transition
                    x-init="setTimeout(() => show = false, 2000)"
                    class="text-sm text-slate-600"
                >{{ __('Saved.') }}</p>
            @endif
        </div>
    </form>
</section>
