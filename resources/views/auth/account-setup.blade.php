<x-guest-layout>
    <div class="mb-6">
        <h1 class="text-xl font-semibold text-slate-950">Set your password</h1>
        <p class="mt-1 text-sm text-slate-600">Choose a password for your ARK operations login. Two-factor authentication can be added here later.</p>
    </div>

    <form method="POST" action="{{ route('account.setup.store') }}">
        @csrf

        <div>
            <x-input-label for="password" :value="__('Password')" />
            <x-text-input id="password" class="block mt-1 w-full" type="password" name="password" required autofocus autocomplete="new-password" />
            <x-input-error :messages="$errors->get('password')" class="mt-2" />
        </div>

        <div class="mt-4">
            <x-input-label for="password_confirmation" :value="__('Confirm Password')" />
            <x-text-input id="password_confirmation" class="block mt-1 w-full" type="password" name="password_confirmation" required autocomplete="new-password" />
            <x-input-error :messages="$errors->get('password_confirmation')" class="mt-2" />
        </div>

        <div class="flex items-center justify-end mt-4">
            <x-primary-button>
                {{ __('Continue to ARK') }}
            </x-primary-button>
        </div>
    </form>
</x-guest-layout>
