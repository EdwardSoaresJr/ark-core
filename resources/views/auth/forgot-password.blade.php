<x-guest-layout>
    <div class="mb-4 text-sm text-gray-600">
        Enter the email for your staff account. If it matches the recovery owner on file, ARK sends a one-time code to that recovery email.
    </div>

    <x-auth-session-status class="mb-4" :status="session('status')" />

    <form method="POST" action="{{ route('password.email') }}">
        @csrf

        <div>
            <x-input-label for="email" :value="__('Email')" />
            <x-text-input id="email" class="block mt-1 w-full" type="email" name="email" :value="old('email')" required autofocus />
            <x-input-error :messages="$errors->get('email')" class="mt-2" />
        </div>

        <div class="flex items-center justify-between mt-4 gap-4">
            <a class="underline text-sm text-gray-600 hover:text-gray-900" href="{{ route('password.offline') }}">
                This Box cannot reach Cloud
            </a>
            <x-primary-button>
                Send recovery code
            </x-primary-button>
        </div>
    </form>
</x-guest-layout>
