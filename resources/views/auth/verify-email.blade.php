<x-guest-layout>
    <div class="mb-4">
        <h1 class="text-xl font-semibold text-slate-950">Verify your email</h1>
        <p class="mt-2 text-sm text-slate-600">
            {{ __('Before continuing in ARK, confirm your email address using the link we sent you. If you did not get the email, you can request another.') }}
        </p>
    </div>

    @if (session('status') == 'verification-link-sent')
        <div class="mb-4 font-medium text-sm text-green-600">
            {{ __('A new verification link has been sent to the email address on your account.') }}
        </div>
    @endif

    <div class="mt-4 flex items-center justify-between gap-4">
        <form method="POST" action="{{ route('verification.send') }}">
            @csrf

            <div>
                <x-primary-button>
                    {{ __('Resend verification email') }}
                </x-primary-button>
            </div>
        </form>

        <form method="POST" action="{{ route('logout') }}">
            @csrf

            <button type="submit" class="underline text-sm text-slate-600 hover:text-slate-900 rounded-md focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-cyan-700">
                {{ __('Log out') }}
            </button>
        </form>
    </div>
</x-guest-layout>
