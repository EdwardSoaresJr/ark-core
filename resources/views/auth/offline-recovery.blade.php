<x-guest-layout>
    <div class="mb-4 text-sm text-gray-600">
        This Box stays offline. On another device with internet, open the Cloud recovery page, prove control of the recovery owner email, then carry the authorization back here.
    </div>

    <div class="mb-4 rounded-sm border border-gray-200 bg-gray-50 p-3 text-sm">
        <div class="text-xs font-semibold uppercase tracking-wide text-gray-500">Cloud recovery page</div>
        <div class="mt-1 break-all font-mono text-xs">{{ $cloudUrl }}</div>
        <div class="mt-3 text-xs font-semibold uppercase tracking-wide text-gray-500">Installation</div>
        <div class="mt-1 break-all font-mono">{{ $installationUuid }}</div>
        <div class="mt-3 text-xs font-semibold uppercase tracking-wide text-gray-500">Challenge</div>
        <div class="mt-1 break-all font-mono">{{ $challenge }}</div>
    </div>

    <form method="POST" action="{{ route('password.offline.store') }}">
        @csrf

        <div>
            <x-input-label for="authorization" value="Authorization from Cloud" />
            <textarea id="authorization" name="authorization" rows="8" required class="mt-1 block w-full font-mono text-xs" spellcheck="false">{{ old('authorization') }}</textarea>
            <x-input-error :messages="$errors->get('authorization')" class="mt-2" />
        </div>

        <div class="mt-4">
            <x-input-label for="password" :value="__('Password')" />
            <x-text-input id="password" class="block mt-1 w-full" type="password" name="password" required autocomplete="new-password" />
            <x-input-error :messages="$errors->get('password')" class="mt-2" />
        </div>

        <div class="mt-4">
            <x-input-label for="password_confirmation" :value="__('Confirm Password')" />
            <x-text-input id="password_confirmation" class="block mt-1 w-full" type="password" name="password_confirmation" required autocomplete="new-password" />
            <x-input-error :messages="$errors->get('password_confirmation')" class="mt-2" />
        </div>

        <div class="flex items-center justify-end mt-4">
            <x-primary-button>
                Reset password
            </x-primary-button>
        </div>
    </form>
</x-guest-layout>
